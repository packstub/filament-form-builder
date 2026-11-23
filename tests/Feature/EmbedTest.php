<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Packstub\FormBuilder\Filament\Resources\FormResource;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use Packstub\FormBuilder\Models\Form;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Route::middleware('web')->get('/contact-us', fn () => Blade::render('<x-form-builder::form form="contact" />'));
    Route::middleware('web')->get('/prefilled', fn () => Blade::render('<x-form-builder::form form="contact" :values="[\'topic\' => \'support\']" />'));
});

it('serves the embed script with the definition URL and the stylesheet', function (): void {
    contactForm();

    $response = $this->get('/forms/contact/embed.js')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/javascript')
        ->and($response->getContent())->toContain('window.PackstubFormBuilder', '"definition":"'.url('/forms/contact/definition').'"', '.fb-form{', 'data-form-builder')
        ->and($response->getContent())->not->toContain('__FB_EMBED_CONFIG__');

    $this->get('/forms/missing/embed.js')->assertNotFound();
});

it('renders the bare layout for an iframe', function (): void {
    contactForm(['description' => 'Say hello']);

    $html = $this->get('/forms/contact?embed=1')->assertOk()->getContent();

    expect($html)->toContain('form-builder:resize', 'name="email"', 'name="robots" content="noindex"')
        ->and($html)->not->toContain('<h1', 'Say hello');

    $snippet = FormResource::iframeSnippet(Form::query()->firstOrFail());

    expect($snippet)->toContain('<iframe src="'.url('/forms/contact').'?embed=1"', 'form-builder:resize', 'data-form-builder-frame="contact"');
});

it('prefills fields from the page URL and the values attribute', function (): void {
    contactForm();

    $html = $this->get('/contact-us?email=ada%40example.com&name=Ada&unknown=x&topic=sales')->assertOk()->getContent();

    expect($html)->toContain('value="ada@example.com"', 'value="Ada"', '<option value="sales" selected>');

    $html = $this->get('/prefilled?topic=sales')->assertOk()->getContent();

    // The attribute wins over the query string.
    expect($html)->toContain('<option value="support" selected>');

    // A form can refuse query prefill.
    Form::query()->update(['settings' => ['prefill' => false]]);
    $html = $this->get('/contact-us?name=Ada')->getContent();
    expect($html)->not->toContain('value="Ada"');
});

it('puts the page meta, the logo, the brand colour and the custom code on the hosted page', function (): void {
    contactForm(['settings' => [
        'page_title' => 'Talk to us',
        'page_description' => 'We answer fast.',
        'page_image' => 'https://example.com/og.png',
        'page_logo' => 'https://example.com/logo.svg',
        'brand_color' => '#ff6600',
        'custom_css' => '.fb-form .fb-submit { border-radius: 999px }</style><script>alert(1)</script>',
        'custom_js' => "form.dataset.ready = '1';</script>",
        'layout' => 'horizontal',
    ]]);

    $html = $this->get('/forms/contact')->assertOk()->getContent();

    expect($html)
        ->toContain('<title>Talk to us</title>', 'content="We answer fast."', 'property="og:image" content="https://example.com/og.png"', 'src="https://example.com/logo.svg"')
        ->toContain('style="--fb-color-primary: #ff6600"', 'fb-form--horizontal', '.fb-submit { border-radius: 999px }', "form.dataset.ready = '1';")
        ->and($html)->not->toContain('</style><script>alert(1)</script>')
        ->and(substr_count($html, '<style'))->toBe(3);
});

it('describes the protection in the definition', function (): void {
    contactForm(['settings' => ['password' => 'x']]);

    $json = $this->getJson('/forms/contact/definition')->assertOk()->json();

    expect($json['protection']['password'])->toBeTrue()
        ->and($json['protection']['unlock_url'])->toBe(url('/forms/contact/unlock'))
        ->and($json['protection']['captcha'])->toBeNull()
        ->and($json['validate_url'])->toBe(url('/forms/contact/validate'))
        ->and($json['layout'])->toBe('stacked')
        ->and($json['fields'][0]['section'])->toBe('section_1');
});

it('prints no custom code when the config turns it off', function (string $key, string $kept, string $dropped): void {
    config()->set("packstub-form-builder.frontend.{$key}", false);
    $form = contactForm(['settings' => [
        'custom_css' => '.fb-form .fb-submit { border-radius: 999px }',
        'custom_js' => "form.dataset.ready = '1';",
    ]]);

    expect($this->get('/forms/contact')->assertOk()->getContent())->toContain($kept)->not->toContain($dropped)
        ->and(Blade::render('<x-form-builder::form form="contact" />'))->toContain($kept)->not->toContain($dropped);

    livewire('form-builder', ['form' => $form])->assertSeeHtml($kept)->assertDontSeeHtml($dropped);
})->with([
    'css' => ['custom_css', "form.dataset.ready = '1';", '.fb-submit { border-radius: 999px }'],
    'js' => ['custom_js', '.fb-submit { border-radius: 999px }', "form.dataset.ready = '1';"],
]);

it('takes the custom code fields off the editor when the config turns them off', function (): void {
    Filament::setCurrentPanel('admin');
    $this->actingAs(createUser());
    $form = contactForm(['settings' => ['custom_css' => '.fb-form { color: red }', 'custom_js' => 'form.hidden = true;']]);

    livewire(EditForm::class, ['record' => $form->getRouteKey()])
        ->assertFormFieldVisible('settings.custom_css')
        ->assertFormFieldVisible('settings.custom_js');

    config()->set('packstub-form-builder.frontend.custom_js', false);

    livewire(EditForm::class, ['record' => $form->getRouteKey()])
        ->assertFormFieldVisible('settings.custom_css')
        ->assertFormFieldHidden('settings.custom_js')
        ->fillForm(['name' => 'Renamed'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($form->refresh()->name)->toBe('Renamed')
        ->and($form->customCss())->toBe('.fb-form { color: red }')
        ->and($form->customJs())->toBeNull()
        // Saved without the field: the stored code is gone, not kept out of sight.
        ->and($form->setting('custom_js'))->toBeNull();
});

it('keeps custom CSS inside its style element', function (): void {
    $form = contactForm(['settings' => [
        'custom_css' => '.fb-form::after { content: "<b>" } </st</styleyle><img src=x onerror=alert(1)>',
    ]]);

    foreach ([
        $this->get('/forms/contact')->assertOk()->getContent(),
        Blade::render('<x-form-builder::form form="contact" />'),
        livewire('form-builder', ['form' => $form])->html(),
    ] as $html) {
        expect($html)->not->toContain('<img src=x')
            ->toContain('content: "\3C b>"')
            ->toContain('\3C /st\3C /styleyle>\3C img src=x onerror=alert(1)>');
    }
});

it('keeps custom JavaScript inside its script element', function (): void {
    $form = contactForm(['settings' => [
        'custom_js' => "form.dataset.tag = '</script><img src=x onerror=alert(1)>';",
    ]]);

    foreach ([
        Blade::render('<x-form-builder::form form="contact" />'),
        livewire('form-builder', ['form' => $form])->html(),
    ] as $html) {
        expect($html)->not->toContain('</script><img')
            ->toContain("form.dataset.tag = '<\\/script><img src=x onerror=alert(1)>';");
    }
});

it('turns custom JavaScript off by default with tenancy', function (?bool $setting, bool $printed): void {
    $this->rebootWith([
        'packstub-form-builder.tenancy.enabled' => true,
        'packstub-form-builder.frontend.custom_js' => $setting,
    ]);
    $form = contactForm(['settings' => ['custom_js' => "form.dataset.ready = '1';"]]);

    expect(str_contains(Blade::render('<x-form-builder::form form="contact" />'), "form.dataset.ready = '1';"))->toBe($printed)
        ->and($form->customJs() !== null)->toBe($printed);
})->with([
    'default' => [null, false],
    'turned on' => [true, true],
]);

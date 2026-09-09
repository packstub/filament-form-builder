<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Packstub\FormBuilder\Http\FormState;
use Packstub\FormBuilder\Models\Form;

beforeEach(function (): void {
    Route::middleware('web')->get('/contact-us', fn () => Blade::render('<x-form-builder::form form="contact" />'));
    Route::get('/stateless', fn () => Blade::render('<x-form-builder::form form="contact" :styles="false" :enhance="false" />'));
});

it('renders every field with labels, hints, choices and protection inputs', function (): void {
    contactForm(['submit_label' => 'Send it', 'fields' => [
        field('text', 'Name', ['required' => true, 'placeholder' => 'Your name', 'hint' => 'As on your ID', 'width' => 'half']),
        field('email', 'Email'),
        field('select', 'Topic', ['choices' => ['sales' => 'Sales'], 'default' => 'sales']),
        field('radio', 'Priority', ['choices' => ['low' => 'Low', 'high' => 'High']]),
        field('checkboxes', 'Interests', ['choices' => ['php' => 'PHP']]),
        field('checkbox', 'Newsletter'),
        field('date', 'When', ['min' => '2026-01-01']),
        field('number', 'Guests', ['min' => 1, 'max' => 10]),
        field('hidden', 'Source', ['key' => 'source', 'default' => 'website']),
        field('heading', 'Almost done', ['level' => 'h2']),
        field('paragraph', 'Info', ['text' => 'We reply fast.']),
    ]]);

    $html = $this->get('/contact-us')->assertOk()->getContent();

    expect($html)
        ->toContain('id="form-contact"', 'data-fb-form="contact"', 'action="'.url('/forms/contact').'"', 'name="_token"', 'name="_fb_token"', 'name="_fb_website"', 'name="_fb_return"')
        ->toContain('class="fb-field fb-field--half fb-field--text"', 'placeholder="Your name"', 'As on your ID', 'required aria-required="true"', '<span class="fb-required"')
        ->toContain('<option value="sales" selected>Sales</option>', 'type="radio"', 'value="high"', 'name="interests[]"', 'name="newsletter" value="1"')
        ->toContain('type="date"', 'min="2026-01-01"', 'type="number"', 'max="10"', 'type="hidden" id="form-contact-source" name="source" value="website"')
        ->toContain('<h2 class="fb-heading"', 'Almost done', '<p class="fb-paragraph"', 'We reply fast.', '>Send it</button>')
        ->toContain('<style', '.fb-form{', '<script', 'form-builder:submitted');
});

it('shows the closed state instead of the form', function (): void {
    contactForm(['is_active' => false]);

    $this->get('/contact-us')->assertOk()->assertSee('This form is closed.')->assertDontSee('<form', false);
});

it('shows the flashed success message and errors from the session', function (): void {
    $form = contactForm();

    $this->withSession([FormState::successKey($form) => 'All good!'])->get('/contact-us')
        ->assertSee('All good!')
        ->assertDontSee('<form', false);

    $errors = new ViewErrorBag;
    $errors->put(FormState::errorBag($form), new MessageBag(['email' => ['Bad email.']]));

    $this->flushSession();

    $html = $this->withSession(['errors' => $errors, '_old_input' => ['name' => 'Ada', 'email' => 'nope']])->get('/contact-us')->getContent();

    expect($html)->toContain('Bad email.', 'value="Ada"', 'value="nope"', 'aria-invalid="true"', 'fb-field--error')
        ->and(substr_count($html, 'role="alert" data-fb-alert>'))->toBe(1);
});

it('renders without a CSRF field, styles or script when asked', function (): void {
    contactForm();

    $html = $this->get('/stateless')->assertOk()->getContent();

    expect($html)->not->toContain('<input type="hidden" name="_token"', '<style', '<script')
        ->and($html)->toContain('data-fb-enhance="false"');
});

it('renders nothing for a missing form', function (): void {
    expect(trim(Blade::render('<x-form-builder::form form="missing" />')))->toBe('');
});

it('accepts a model, an id or a slug', function (): void {
    $form = contactForm();

    expect(Blade::render('<x-form-builder::form :form="$form" :styles="false" :enhance="false" />', ['form' => $form]))->toContain('id="form-contact"')
        ->and(Blade::render('<x-form-builder::form :form="1" :styles="false" :enhance="false" />'))->toContain('id="form-contact"');
});

it('inlines the stylesheet and the script once per page', function (): void {
    contactForm();
    Form::query()->create(['name' => 'Other', 'slug' => 'other', 'fields' => [field('text', 'Name')]]);

    $html = Blade::render('<x-form-builder::form form="contact" /><x-form-builder::form form="other" />');

    expect(substr_count($html, '<style'))->toBe(1)
        ->and(substr_count($html, '<script'))->toBe(1)
        ->and(substr_count($html, '<form '))->toBe(2);
});

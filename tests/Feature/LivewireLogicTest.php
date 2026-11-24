<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Packstub\FormBuilder\Livewire\FormBuilderForm;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;

use function Pest\Livewire\livewire;

it('renders sections as cards and steps as a wizard', function (): void {
    $form = wizardForm(['mode' => 'single']);

    livewire(FormBuilderForm::class, ['form' => 'registration'])
        ->assertOk()
        ->assertSee('Attendee')
        ->assertSee('Who is coming')
        ->assertSee('fi-sc-section', false)
        ->assertDontSee('fi-sc-wizard', false);

    $form->update(['settings' => ['mode' => 'wizard', 'next_label' => 'Continue']]);

    livewire(FormBuilderForm::class, ['form' => 'registration'])
        ->assertOk()
        ->assertSee('fi-sc-wizard', false)
        ->assertSee('Continue')
        ->fillForm(['name' => 'Ada', 'email' => 'ada@example.com', 'type' => 'free'])
        ->call('submit')
        ->assertHasNoFormErrors()
        ->assertSet('submitted', true);

    expect(FormSubmission::query()->first()->value('name'))->toBe('Ada');
});

it('applies the conditions live and on submit', function (): void {
    conditionalForm();

    $component = livewire(FormBuilderForm::class, ['form' => 'conditional'])
        ->assertFormFieldExists('attendance')
        ->assertFormFieldExists('tshirt', 'form', fn ($field): bool => $field->isHidden())
        ->fillForm(['attendance' => 'in_person'])
        ->assertFormFieldExists('tshirt', 'form', fn ($field): bool => $field->isVisible())
        ->assertFormFieldExists('allergies', 'form', fn ($field): bool => $field->isHidden())
        ->fillForm(['tshirt' => 'm'])
        ->assertFormFieldExists('allergies', 'form', fn ($field): bool => $field->isVisible())
        ->assertFormFieldExists('company', 'form', fn ($field): bool => ! $field->isRequired());

    $component->fillForm(['attendance' => 'virtual'])
        ->assertFormFieldExists('tshirt', 'form', fn ($field): bool => $field->isHidden())
        ->assertFormFieldExists('company', 'form', fn ($field): bool => $field->isRequired())
        ->call('submit')
        ->assertHasFormErrors(['company' => 'required']);

    $component->fillForm(['company' => 'Acme'])
        ->call('submit')
        ->assertHasNoFormErrors()
        ->assertSet('submitted', true);

    expect(FormSubmission::query()->first()->data)->toBe(['attendance' => 'virtual', 'tshirt' => null, 'allergies' => null, 'company' => 'Acme']);
});

it('asks for the password and unlocks', function (): void {
    contactForm(['settings' => ['password' => 'open']]);

    livewire(FormBuilderForm::class, ['form' => 'contact'])
        ->assertSet('locked', true)
        ->assertSee('This form is protected.')
        ->assertDontSee('wire:submit="submit"', false)
        ->set('password', 'nope')
        ->call('unlock')
        ->assertSet('locked', true)
        ->assertSet('passwordError', 'That password is not right.')
        ->set('password', 'open')
        ->call('unlock')
        ->assertSet('locked', false)
        ->assertSee('wire:submit="submit"', false)
        ->fillForm(['name' => 'Ada', 'email' => 'ada@example.com', 'message' => 'Hi'])
        ->call('submit')
        ->assertHasNoFormErrors()
        ->assertSet('submitted', true);

    expect(FormSubmission::query()->count())->toBe(1);
});

it('prefills from the values attribute and the query string', function (): void {
    contactForm();
    Route::middleware('web')->get('/embed', fn () => Blade::render('<livewire:form-builder form="contact" :values="[\'name\' => \'Ada\']" />'));

    $html = $this->get('/embed?email=ada%40example.com')->assertOk()->getContent();

    expect($html)->toContain('Ada', 'ada@example.com');
});

it('previews a definition without storing anything', function (): void {
    $definition = ['name' => 'Preview', 'slug' => 'preview', 'fields' => [field('text', 'Name', ['required' => true])], 'success_message' => 'Looks good'];

    livewire(FormBuilderForm::class, ['form' => $definition, 'preview' => true])
        ->assertOk()
        ->assertSee('Name')
        ->call('submit')
        ->assertHasFormErrors(['name' => 'required'])
        ->fillForm(['name' => 'Ada'])
        ->call('submit')
        ->assertHasNoFormErrors()
        ->assertSet('submitted', true)
        ->assertSee('Looks good');

    expect(FormSubmission::query()->count())->toBe(0)->and(Form::query()->count())->toBe(0);
});

it('renders a form from an array on the page', function (): void {
    $definition = ['name' => 'Inline', 'fields' => [field('email', 'Email', ['required' => true])]];
    Route::middleware('web')->get('/inline', fn () => Blade::render('<livewire:form-builder :form="$definition" />', ['definition' => $definition]));

    $this->get('/inline')->assertOk()->assertSee('Email');
});

it('submits the captcha token with the form', function (): void {
    config()->set('packstub-form-builder.captcha.hcaptcha', ['site_key' => 'site', 'secret' => 'secret']);
    Http::fake(['api.hcaptcha.com/*' => Http::response(['success' => true])]);
    contactForm(['settings' => ['captcha' => 'hcaptcha']]);

    livewire(FormBuilderForm::class, ['form' => 'contact'])
        ->assertSee('h-captcha', false)
        ->assertSee('js.hcaptcha.com', false)
        ->fillForm(['name' => 'Ada', 'email' => 'ada@example.com', 'message' => 'Hi'])
        ->call('submit')
        ->assertHasErrors(['captcha'])
        ->call('submit', 'token')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    Http::assertSent(fn ($request): bool => $request['response'] === 'token');
});

it('rate limits unlocking and submitting like the HTTP routes', function (): void {
    config(['packstub-form-builder.submissions.throttle' => '2,1']);
    contactForm(['settings' => ['password' => 'open']]);

    $component = livewire(FormBuilderForm::class, ['form' => 'contact'])->set('password', 'nope');
    $component->call('unlock')->assertSet('passwordError', 'That password is not right.');
    $component->call('unlock')->assertSet('passwordError', 'That password is not right.');
    $component->set('password', 'open')->call('unlock')
        ->assertSet('passwordError', 'Too many attempts. Please wait a minute and try again.')
        ->assertSet('locked', true);

    contactForm(['slug' => 'other']);
    $other = livewire(FormBuilderForm::class, ['form' => 'other']);
    $other->call('submit');
    $other->call('submit');
    $other->call('submit')->assertSet('error', 'Too many attempts. Please wait a minute and try again.');
});

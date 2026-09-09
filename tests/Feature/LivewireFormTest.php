<?php

use Illuminate\Support\Facades\Event;
use Packstub\FormBuilder\Events\SubmissionReceived;
use Packstub\FormBuilder\Livewire\FormBuilderForm;
use Packstub\FormBuilder\Models\FormSubmission;

use function Pest\Livewire\livewire;

it('renders the fields as Filament components and submits through the pipeline', function (): void {
    Event::fake([SubmissionReceived::class]);
    contactForm();

    livewire(FormBuilderForm::class, ['form' => 'contact'])
        ->assertOk()
        ->assertSee('Message')
        ->assertSee('Almost done')
        ->assertFormFieldExists('email')
        ->fillForm([
            'name' => 'Ada',
            'email' => 'ADA@example.com',
            'topic' => 'support',
            'message' => 'Hi',
            'newsletter' => true,
            'interests' => ['js'],
        ])
        ->call('submit')
        ->assertHasNoFormErrors()
        ->assertSet('submitted', true)
        ->assertSee('Thank you, your message has been sent.');

    $submission = FormSubmission::query()->firstOrFail();

    expect($submission->channel)->toBe('livewire')
        ->and($submission->data['email'])->toBe('ada@example.com')
        ->and($submission->data['interests'])->toBe(['js'])
        ->and($submission->data['source'])->toBe('website');

    Event::assertDispatched(SubmissionReceived::class);
});

it('shows validation errors in place', function (): void {
    contactForm();

    livewire(FormBuilderForm::class, ['form' => 'contact'])
        ->fillForm(['name' => 'Ada', 'email' => 'nope', 'message' => ''])
        ->call('submit')
        ->assertHasFormErrors(['email' => 'email', 'message' => 'required']);

    expect(FormSubmission::query()->count())->toBe(0);
});

it('redirects when the form has a redirect URL', function (): void {
    contactForm(['redirect_url' => 'https://example.com/thanks']);

    livewire(FormBuilderForm::class, ['form' => 'contact'])
        ->fillForm(['name' => 'Ada', 'email' => 'ada@example.com', 'message' => 'Hi'])
        ->call('submit')
        ->assertRedirect('https://example.com/thanks');
});

it('shows the closed state', function (): void {
    contactForm(['is_active' => false]);

    livewire(FormBuilderForm::class, ['form' => 'contact'])
        ->assertSee('This form is closed.')
        ->assertDontSee('wire:submit');
});

it('404s for an unknown form', function (): void {
    livewire(FormBuilderForm::class, ['form' => 'missing'])->assertNotFound();
});

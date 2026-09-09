<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Packstub\FormBuilder\Events\SpamDetected;
use Packstub\FormBuilder\Events\SubmissionReceived;
use Packstub\FormBuilder\Exceptions\FormClosedException;
use Packstub\FormBuilder\Facades\FormBuilder;
use Packstub\FormBuilder\Mail\SubmissionNotification;
use Packstub\FormBuilder\Models\FormSubmission;
use Packstub\FormBuilder\Submissions\ProtectionToken;
use Packstub\FormBuilder\Submissions\SpamGuard;
use Packstub\FormBuilder\Submissions\SubmissionContext;
use Packstub\FormBuilder\Submissions\Submitter;
use Packstub\FormBuilder\Tests\Fixtures\RecordingSink;

it('validates, normalises and stores a submission', function (): void {
    Event::fake([SubmissionReceived::class]);
    $form = contactForm();

    $result = app(Submitter::class)->submit($form, contactInput($form, ['newsletter' => 'on']), new SubmissionContext(
        ip: '10.0.0.1',
        userAgent: 'Pest',
        sourceUrl: 'https://example.com/contact',
    ));

    expect($result->spam)->toBeFalse()
        ->and($result->message())->toBe('Thank you, your message has been sent.')
        ->and($result->submission)->toBeInstanceOf(FormSubmission::class)
        ->and($result->submission->exists)->toBeTrue()
        ->and($result->submission->data)->toBe([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'topic' => 'sales',
            'message' => 'Hello there',
            'newsletter' => true,
            'interests' => ['php'],
            'source' => 'website',
        ])
        ->and($result->submission->fields['email'])->toBe(['label' => 'Email', 'type' => 'email'])
        ->and($result->submission->ip)->toBe('10.0.0.1')
        ->and($result->submission->source_url)->toBe('https://example.com/contact')
        ->and($result->submission->read_at)->toBeNull();

    Event::assertDispatched(SubmissionReceived::class, fn (SubmissionReceived $event): bool => $event->form->is($form));
});

it('rejects invalid input with messages keyed by field', function (): void {
    $form = contactForm();

    try {
        app(Submitter::class)->submit($form, contactInput($form, ['email' => 'nope', 'message' => '', 'topic' => 'other', 'interests' => ['php', 'hacked']]));
        $this->fail('Expected a validation exception.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKeys(['email', 'message', 'topic', 'interests.1'])
            ->and($e->errors()['message'][0])->toBe('The Message field is required.');
    }

    expect(FormSubmission::query()->count())->toBe(0);
});

it('drops submissions that fill the honeypot', function (): void {
    Event::fake([SpamDetected::class, SubmissionReceived::class]);
    $form = contactForm();

    $result = app(Submitter::class)->submit($form, contactInput($form, [app(SpamGuard::class)->honeypotField() => 'http://spam']));

    expect($result->spam)->toBeTrue()
        ->and($result->submission)->toBeNull()
        ->and(FormSubmission::query()->count())->toBe(0);

    Event::assertDispatched(SpamDetected::class, fn (SpamDetected $event): bool => $event->reason === 'honeypot');
    Event::assertNotDispatched(SubmissionReceived::class);
});

it('drops submissions posted too fast or without a token', function (): void {
    config()->set('packstub-form-builder.spam.min_seconds', 3);
    Event::fake([SpamDetected::class]);
    $form = contactForm();
    $tokens = app(ProtectionToken::class);

    $fast = app(Submitter::class)->submit($form, contactInput($form));
    $missing = app(Submitter::class)->submit($form, contactInput($form, [$tokens->field() => null]));
    $foreign = app(Submitter::class)->submit($form, contactInput($form, [$tokens->field() => $tokens->make(contactForm(['slug' => 'other']), time() - 10)]));
    $old = app(Submitter::class)->submit($form, contactInput($form, [$tokens->field() => $tokens->make($form, time() - 10)]));

    expect($fast->spam)->toBeTrue()
        ->and($missing->spam)->toBeTrue()
        ->and($foreign->spam)->toBeTrue()
        ->and($old->spam)->toBeFalse();

    Event::assertDispatched(SpamDetected::class, 3);
    Event::assertDispatched(SpamDetected::class, fn (SpamDetected $event): bool => $event->reason === 'too_fast');
    Event::assertDispatched(SpamDetected::class, fn (SpamDetected $event): bool => $event->reason === 'no_token');
});

it('honours per-form spam settings', function (): void {
    config()->set('packstub-form-builder.spam.min_seconds', 3);
    $form = contactForm(['settings' => ['honeypot' => false, 'min_seconds' => 0]]);

    $result = app(Submitter::class)->submit($form, contactInput($form, [
        app(SpamGuard::class)->honeypotField() => 'filled',
        app(ProtectionToken::class)->field() => null,
    ]));

    expect($result->spam)->toBeFalse()->and($result->submission->exists)->toBeTrue();
});

it('refuses closed forms and forms that require a login', function (): void {
    $form = contactForm(['is_active' => false]);

    expect(fn () => app(Submitter::class)->submit($form, contactInput($form)))
        ->toThrow(FormClosedException::class, 'This form is closed.');

    $form->update(['is_active' => true, 'settings' => ['require_login' => true]]);

    expect(fn () => app(Submitter::class)->submit($form, contactInput($form)))
        ->toThrow(FormClosedException::class, 'Please sign in to use this form.');

    $result = app(Submitter::class)->submit($form, contactInput($form), new SubmissionContext(userId: createUser()->id));

    expect($result->submission->user_id)->toBe(1);
});

it('emails the notification addresses and calls the sinks', function (): void {
    Mail::fake();
    config()->set('packstub-form-builder.sinks', [RecordingSink::class]);
    $form = contactForm(['notification_emails' => ['inbox@example.com', 'sales@example.com']]);

    app(Submitter::class)->submit($form, contactInput($form));

    Mail::assertQueued(SubmissionNotification::class, function (SubmissionNotification $mail): bool {
        return $mail->hasTo('inbox@example.com') && $mail->hasTo('sales@example.com')
            && $mail->envelope()->subject === 'New submission: Contact';
    });

    expect(RecordingSink::$received)->toHaveCount(1)
        ->and(RecordingSink::$received[0]->value('email'))->toBe('ada@example.com');
});

it('renders the notification email with every value', function (): void {
    $form = contactForm(['notification_emails' => ['inbox@example.com']]);
    $result = app(Submitter::class)->submit($form, contactInput($form, ['interests' => ['php', 'js']]));

    $html = (new SubmissionNotification($result->submission))->render();

    expect($html)->toContain('New submission for Contact', 'Ada Lovelace', 'PHP, JavaScript', 'Sales');
});

it('passes unsaved submissions along when the form does not store them', function (): void {
    Mail::fake();
    FormBuilder::sink(new RecordingSink);
    $form = contactForm(['store_submissions' => false, 'notification_emails' => ['inbox@example.com']]);

    $result = app(Submitter::class)->submit($form, contactInput($form));

    expect($result->submission->exists)->toBeFalse()
        ->and(FormSubmission::query()->count())->toBe(0)
        ->and(RecordingSink::$received)->toHaveCount(1)
        ->and(RecordingSink::$received[0]->form->is($form))->toBeTrue();

    Mail::assertSent(SubmissionNotification::class);
});

it('submits from code through the facade', function (): void {
    config()->set('packstub-form-builder.spam.min_seconds', 5);
    contactForm();

    $result = FormBuilder::submit('contact', ['name' => 'Ada', 'email' => 'ada@example.com', 'message' => 'Hi']);

    expect($result->submission->exists)->toBeTrue()
        ->and($result->submission->channel)->toBe('code')
        ->and($result->submission->meta)->toBeNull();
});

it('does not store the ip or user agent when configured', function (): void {
    config()->set('packstub-form-builder.submissions.store_ip', false);
    config()->set('packstub-form-builder.submissions.store_user_agent', false);
    $form = contactForm();

    $result = app(Submitter::class)->submit($form, contactInput($form), new SubmissionContext(ip: '10.0.0.1', userAgent: 'Pest'));

    expect($result->submission->ip)->toBeNull()->and($result->submission->user_agent)->toBeNull();
});

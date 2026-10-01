<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Packstub\FormBuilder\Facades\FormBuilder;
use Packstub\FormBuilder\Models\WebhookDelivery;
use Packstub\FormBuilder\Testing\FormBuilderFake;
use Packstub\FormBuilder\Tests\Fixtures\RecordingSink;
use PHPUnit\Framework\ExpectationFailedException;

it('records submissions and skips every side effect', function (): void {
    Mail::fake();
    Http::fake();
    $fake = FormBuilder::fake();
    FormBuilder::sink(RecordingSink::class);

    $form = contactForm(['notification_emails' => ['team@example.com'], 'settings' => [
        'autoresponder' => true, 'autoresponder_field' => 'email',
        'webhook_url' => 'https://hooks.example.com/forms',
        'channels' => [['provider' => 'slack', 'url' => 'https://hooks.slack.com/services/T/B/x']],
    ]]);

    $this->post('/forms/contact', FormBuilder::validInput('contact', ['name' => 'Ada', 'email' => 'ada@example.com', 'message' => 'Hi']))
        ->assertRedirect();

    expect($fake)->toBeInstanceOf(FormBuilderFake::class)
        ->and(FormBuilder::faking())->toBeTrue()
        ->and($form->submissions()->count())->toBe(1);

    FormBuilder::assertSubmitted('contact')
        ->assertSubmitted($form, fn ($submission, array $data): bool => $data['name'] === 'Ada')
        ->assertNotSubmitted('contact', fn ($submission, array $data): bool => $data['name'] === 'Bob')
        ->assertSubmittedCount($form, 1)
        ->assertSubmittedCount('other', 0);

    Mail::assertNothingQueued();
    Mail::assertNothingSent();
    Http::assertNothingSent();

    expect(WebhookDelivery::query()->count())->toBe(0)
        ->and(RecordingSink::$received)->toBe([]);
});

it('records spam and fails an assertion that does not hold', function (): void {
    FormBuilder::fake();
    contactForm();

    $this->post('/forms/contact', FormBuilder::validInput('contact', ['name' => 'Bot', '_fb_website' => 'spam']));

    FormBuilder::assertNothingSubmitted()->assertSpamDetected('contact', 'honeypot');

    expect(fn () => FormBuilder::assertSubmitted('contact'))->toThrow(ExpectationFailedException::class);
});

it('is off unless asked for', function (): void {
    expect(FormBuilder::faking())->toBeFalse();
});

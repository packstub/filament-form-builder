<?php

use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Mail;
use Packstub\FormBuilder\Mail\Autoresponder;
use Packstub\FormBuilder\Mail\SubmissionNotification;
use Packstub\FormBuilder\Submissions\Submitter;

beforeEach(fn () => Mail::fake());

function brandedSubmission(array $settings)
{
    $form = contactForm(['notification_emails' => ['team@example.com'], 'settings' => $settings]);

    return app(Submitter::class)->submit($form, contactInput($form))->submission;
}

it('puts the logo in the header and the brand colour on buttons and links', function (): void {
    $submission = brandedSubmission(['page_logo' => 'https://cdn.example.com/acme.png', 'brand_color' => '#e11d48']);

    $mail = new SubmissionNotification($submission);
    $html = $mail->render();

    expect($mail->theme)->toBe('packstub-form-builder::mail.theme')
        ->and($html)->toContain('src="https://cdn.example.com/acme.png"')
        ->and($html)->toMatch('/class="button button-primary"[^>]*style="[^"]*background-color: #e11d48/')
        ->and($html)->toContain('Contact');
});

it('prints the app name, not an image tag, in the text part', function (): void {
    $submission = brandedSubmission(['page_logo' => 'https://cdn.example.com/acme.png', 'brand_color' => '#e11d48']);

    $mail = new Autoresponder($submission);
    $mail->render();
    $text = (string) app(Markdown::class)->renderText('packstub-form-builder::mail.autoresponder', [
        ...$mail->content()->with,
    ]);

    expect($text)->not->toContain('<img')->toContain(config('app.name'));
});

it('leaves the emails as they were without a logo or colour', function (): void {
    $submission = brandedSubmission([]);

    $mail = new SubmissionNotification($submission);
    $html = $mail->render();

    expect($mail->theme)->toBeNull()
        ->and($html)->not->toContain('max-height: 48px')
        ->and($html)->toContain(config('app.name'));
});

it('ignores a colour or logo that is not a plain hex value or URL', function (): void {
    $submission = brandedSubmission(['page_logo' => 'javascript:alert(1)', 'brand_color' => 'red; } body { display: none']);

    $mail = new Autoresponder($submission);
    $html = $mail->render();

    expect($mail->theme)->toBeNull()
        ->and($html)->not->toContain('javascript:')
        ->and($html)->not->toContain('display: none');
});

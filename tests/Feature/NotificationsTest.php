<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Packstub\FormBuilder\Mail\Autoresponder;
use Packstub\FormBuilder\Mail\SubmissionNotification;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Notifications\MergeTags;
use Packstub\FormBuilder\Submissions\SubmissionContext;
use Packstub\FormBuilder\Submissions\Submitter;

it('addresses the team email from the form settings with merge tags', function (): void {
    Mail::fake();
    $form = contactForm(['notification_emails' => ['inbox@example.com'], 'settings' => [
        'notify_subject' => '[{form_name}] #{submission_number} from {name} on {domain}',
        'notify_from_email' => 'forms@example.com',
        'notify_from_name' => 'Forms',
        'notify_reply_to' => 'respondent',
        'notify_cc' => ['cc@example.com'],
        'notify_bcc' => ['bcc@example.com', 'not-an-email'],
    ]]);

    $submission = app(Submitter::class)->submit($form, contactInput($form))->submission;

    Mail::assertQueued(SubmissionNotification::class, function (SubmissionNotification $mail): bool {
        $envelope = $mail->envelope();

        return $envelope->subject === '[Contact] #1 from Ada Lovelace on localhost'
            && $envelope->from->address === 'forms@example.com'
            && $envelope->from->name === 'Forms'
            && $envelope->replyTo[0]->address === 'ada@example.com'
            && $mail->hasCc('cc@example.com')
            && $mail->hasBcc('bcc@example.com')
            && count($envelope->bcc) === 1;
    });

    expect(MergeTags::render('Hi {{ name }}, {{ missing }} {date}', $submission))->toStartWith('Hi Ada Lovelace, {{ missing }} 20');

    $html = (new SubmissionNotification($submission))->render();
    expect($html)->toContain('New submission for Contact #1');
});

it('sends the confirmation to the visitor', function (): void {
    Mail::fake();
    $form = contactForm(['settings' => [
        'autoresponder' => true,
        'autoresponder_subject' => 'Thanks {name}',
        'autoresponder_body' => "Hello {{ name }},\n\nWe got your message about **{{ topic }}**.",
        'autoresponder_include_values' => true,
    ]]);

    $submission = app(Submitter::class)->submit($form, contactInput($form))->submission;

    Mail::assertQueued(Autoresponder::class, fn (Autoresponder $mail): bool => $mail->hasTo('ada@example.com') && $mail->envelope()->subject === 'Thanks Ada Lovelace');

    $html = (new Autoresponder($submission))->render();
    expect($html)->toContain('Hello Ada Lovelace', 'Hello there')->toMatch('/<strong[^>]*>Sales<\/strong>/');

    // Off by default, and never to an invalid address.
    Mail::fake();
    $plain = contactForm(['slug' => 'plain']);
    app(Submitter::class)->submit($plain, contactInput($plain));
    Mail::assertNotQueued(Autoresponder::class);

    $picked = contactForm(['slug' => 'picked', 'settings' => ['autoresponder' => true, 'autoresponder_field' => 'name']]);
    app(Submitter::class)->submit($picked, contactInput($picked));
    Mail::assertNotQueued(Autoresponder::class);
});

it('attaches uploaded files to the team email when asked', function (): void {
    Storage::fake('local');
    config()->set('packstub-form-builder.uploads.attach_max_kb', 30);
    $form = Form::query()->create([
        'name' => 'Files',
        'slug' => 'files',
        'notification_emails' => ['inbox@example.com'],
        'settings' => ['notify_attach_files' => true],
        'fields' => [field('file', 'Docs', ['key' => 'docs', 'multiple' => true])],
    ]);

    $submission = app(Submitter::class)->submit($form, [
        'docs' => [UploadedFile::fake()->createWithContent('one.pdf', str_repeat('a', 10240)), UploadedFile::fake()->createWithContent('two.pdf', str_repeat('a', 10240)), UploadedFile::fake()->createWithContent('three.pdf', str_repeat('a', 25600))],
    ], (new SubmissionContext)->trusted())->submission;

    $attachments = (new SubmissionNotification($submission))->attachments();

    expect($attachments)->toHaveCount(2)
        ->and($attachments[0]->as)->toBe('one.pdf');
});

it('notifies the picked users in the panel', function (): void {
    Schema::create('notifications', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('type');
        $table->morphs('notifiable');
        $table->text('data');
        $table->timestamp('read_at')->nullable();
        $table->timestamps();
    });
    $ada = createUser();
    $grace = createUser();
    createUser();
    $form = contactForm(['settings' => ['notify_users' => [$ada->id, $grace->id]]]);

    app(Submitter::class)->submit($form, contactInput($form));

    expect(DB::table('notifications')->count())->toBe(2)
        ->and(DB::table('notifications')->where('notifiable_id', $ada->id)->value('data'))->toContain('New submission: Contact', 'Ada Lovelace');
});

it('puts what the visitor typed into the emails as text, never as HTML or a link', function (): void {
    Mail::fake();
    $form = contactForm(['notification_emails' => ['inbox@example.com'], 'settings' => [
        'autoresponder' => true,
        'autoresponder_body' => "Hi {name},\n\nThanks for writing.",
        'autoresponder_include_values' => true,
    ]]);

    $submission = app(Submitter::class)->submit($form, contactInput($form, [
        'name' => '<a href="https://evil.test/login">Verify your account</a>',
        'message' => "[Open](https://evil.test/x) ![x](https://evil.test/p.png)\n# Big **bold**",
    ]))->submission;

    foreach ([(new Autoresponder($submission))->render(), (new SubmissionNotification($submission))->render()] as $html) {
        expect($html)->not->toContain('<a href="https://evil.test')
            ->not->toContain('<img src="https://evil.test')
            ->toContain('# Big')
            ->toContain('[Open](https://evil.test/x)');
    }

    expect((new Autoresponder($submission))->render())->toContain('Hi &lt;a href="https://evil.test/login"&gt;Verify your account&lt;/a&gt;');
});

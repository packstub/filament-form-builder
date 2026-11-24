<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Packstub\FormBuilder\Events\SpamDetected;
use Packstub\FormBuilder\Exceptions\FormClosedException;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;
use Packstub\FormBuilder\Submissions\Captcha;
use Packstub\FormBuilder\Submissions\PasswordGate;
use Packstub\FormBuilder\Submissions\ProtectionToken;
use Packstub\FormBuilder\Submissions\SpamGuard;
use Packstub\FormBuilder\Submissions\SubmissionContext;
use Packstub\FormBuilder\Submissions\Submitter;

it('accepts a token once', function (): void {
    Event::fake([SpamDetected::class]);
    $form = contactForm();
    $input = contactInput($form);

    expect(app(Submitter::class)->submit($form, $input)->spam)->toBeFalse()
        ->and(app(Submitter::class)->submit($form, $input)->spam)->toBeTrue()
        ->and(app(Submitter::class)->submit($form, contactInput($form))->spam)->toBeFalse()
        ->and(FormSubmission::query()->count())->toBe(2);

    Event::assertDispatched(SpamDetected::class, fn (SpamDetected $event): bool => $event->reason === 'reused_token');
});

it('numbers submissions sequentially per form', function (): void {
    $form = contactForm();
    $other = contactForm(['slug' => 'other']);

    $first = app(Submitter::class)->submit($form, contactInput($form))->submission;
    $second = app(Submitter::class)->submit($form, contactInput($form))->submission;
    $elsewhere = app(Submitter::class)->submit($other, contactInput($other))->submission;

    expect($first->number)->toBe(1)
        ->and($second->number)->toBe(2)
        ->and($elsewhere->number)->toBe(1)
        ->and($second->reference())->toBe('#2');
});

it('limits to one submission per person', function (): void {
    $form = contactForm(['settings' => ['one_per_person' => true]]);
    $context = new SubmissionContext(ip: '10.0.0.1', userAgent: 'Pest');

    app(Submitter::class)->submit($form, contactInput($form), $context);

    expect(fn () => app(Submitter::class)->submit($form, contactInput($form), $context))
        ->toThrow(FormClosedException::class, 'You have already submitted this form.');

    // Another browser is another person; a signed-in user is matched by id.
    app(Submitter::class)->submit($form, contactInput($form), new SubmissionContext(ip: '10.0.0.2', userAgent: 'Pest'));
    $user = new SubmissionContext(ip: '10.0.0.3', userAgent: 'Pest', userId: createUser()->id);
    app(Submitter::class)->submit($form, contactInput($form), $user);

    expect(fn () => app(Submitter::class)->submit($form, contactInput($form), new SubmissionContext(ip: '10.0.0.9', userAgent: 'Other', userId: 1)))
        ->toThrow(FormClosedException::class);

    expect(FormSubmission::query()->count())->toBe(3)
        ->and(FormSubmission::query()->first()->fingerprint)->toBe(hash('xxh128', '10.0.0.1|Pest'));
});

it('closes the form at the submission limit', function (): void {
    $form = contactForm(['settings' => ['max_submissions' => 2, 'full_message' => 'All seats are taken.']]);

    app(Submitter::class)->submit($form, contactInput($form));
    app(Submitter::class)->submit($form, contactInput($form));

    expect($form->closedReason())->toBe('All seats are taken.');

    expect(fn () => app(Submitter::class)->submit($form, contactInput($form)))->toThrow(FormClosedException::class, 'All seats are taken.');

    $this->get('/forms/contact')->assertOk()->assertSee('All seats are taken.');
});

it('uses the custom closed message', function (): void {
    contactForm(['is_active' => false, 'settings' => ['closed_message' => 'See you next year.']]);

    $this->get('/forms/contact')->assertOk()->assertSee('See you next year.');
});

it('drops submissions from blocked words, email domains, IPs and origins', function (): void {
    Event::fake([SpamDetected::class]);
    config()->set('packstub-form-builder.spam.blocklist', ['words' => ['casino'], 'email_domains' => ['spam.test'], 'ips' => ['10.0.0.*']]);
    config()->set('packstub-form-builder.spam.allowed_origins', ['example.com', '*.example.org']);
    $form = contactForm();
    $submitter = app(Submitter::class);
    $ok = new SubmissionContext(ip: '192.168.1.1', origin: 'https://www.example.org/page');

    expect($submitter->submit($form, contactInput($form, ['message' => 'Best CASINO deals']), $ok)->spam)->toBeTrue()
        ->and($submitter->submit($form, contactInput($form, ['email' => 'bot@mail.spam.test']), $ok)->spam)->toBeTrue()
        ->and($submitter->submit($form, contactInput($form), new SubmissionContext(ip: '10.0.0.7', origin: 'https://example.com'))->spam)->toBeTrue()
        ->and($submitter->submit($form, contactInput($form), new SubmissionContext(ip: '192.168.1.1', origin: 'https://evil.test'))->spam)->toBeTrue()
        ->and($submitter->submit($form, contactInput($form), $ok)->spam)->toBeFalse()
        ->and($submitter->submit($form, contactInput($form), new SubmissionContext(ip: '192.168.1.1'))->spam)->toBeFalse();

    foreach (['blocked_word', 'blocked_email', 'blocked_ip', 'origin'] as $reason) {
        Event::assertDispatched(SpamDetected::class, fn (SpamDetected $event): bool => $event->reason === $reason);
    }
});

it('verifies the captcha on the server', function (): void {
    config()->set('packstub-form-builder.captcha.turnstile', ['site_key' => 'site', 'secret' => 'secret']);
    $form = contactForm(['settings' => ['captcha' => 'turnstile']]);

    Http::fake([
        'challenges.cloudflare.com/*' => Http::sequence()
            ->push(['success' => false])
            ->push(['success' => true]),
    ]);

    expect(fn () => app(Submitter::class)->submit($form, contactInput($form)))
        ->toThrow(ValidationException::class, 'Please complete the captcha.');

    try {
        app(Submitter::class)->submit($form, contactInput($form, ['cf-turnstile-response' => 'bad']));
        $this->fail('Expected a failed verification.');
    } catch (ValidationException $e) {
        expect($e->errors()['captcha'][0])->toBe('The captcha could not be verified. Please try again.');
    }

    expect(app(Submitter::class)->submit($form, contactInput($form, ['cf-turnstile-response' => 'good']))->submission->exists)->toBeTrue()
        ->and(app(Submitter::class)->submit($form, contactInput($form), new SubmissionContext(trusted: true))->submission->exists)->toBeTrue();

    Http::assertSent(fn ($request): bool => $request['secret'] === 'secret' && $request['response'] === 'good');

    // The widget is on the page, with the provider's script.
    $html = $this->get('/forms/contact')->getContent();

    expect($html)->toContain('class="cf-turnstile fb-captcha" data-sitekey="site"', 'challenges.cloudflare.com/turnstile/v0/api.js');

    // Without keys the form ignores the setting.
    config()->set('packstub-form-builder.captcha.turnstile.secret', null);
    expect(app(Submitter::class)->submit($form, contactInput($form))->submission->exists)->toBeTrue();
});

it('asks for the password before the form and checks it on submit', function (): void {
    $form = contactForm(['settings' => ['password' => 'open-sesame']]);
    $gate = app(PasswordGate::class);

    $html = $this->get('/forms/contact')->assertOk()->getContent();

    expect($html)->toContain('data-fb-unlock', 'type="password"', url('/forms/contact/unlock'))
        ->and($html)->not->toContain('name="email"');

    expect(fn () => app(Submitter::class)->submit($form, contactInput($form)))
        ->toThrow(FormClosedException::class, 'This form needs a password.');

    expect(app(Submitter::class)->submit($form, contactInput($form, [PasswordGate::FIELD => $gate->key($form)]))->submission->exists)->toBeTrue();

    // Wrong password: back to the page with the error; right one: unlocked in the session.
    $this->from('/forms/contact')->post('/forms/contact/unlock', ['password' => 'nope', '_fb_return' => url('/forms/contact')])
        ->assertRedirect(url('/forms/contact?fb_locked=contact').'#form-contact');
    $this->get('/forms/contact?fb_locked=contact')->assertSee('That password is not right.');

    $this->post('/forms/contact/unlock', ['password' => 'open-sesame', '_fb_return' => url('/forms/contact')])
        ->assertRedirect(url('/forms/contact').'#form-contact')
        ->assertSessionHas($gate->sessionKey($form));

    $html = $this->get('/forms/contact')->assertOk()->getContent();
    expect($html)->toContain('name="email"', 'name="_fb_key"');

    $this->post('/forms/contact', contactInput($form, ['_fb_return' => url('/forms/contact')]))->assertSessionHasNoErrors();
    expect(FormSubmission::query()->count())->toBe(2);

    // JSON clients get the key from the unlock endpoint.
    $this->flushSession();
    $this->postJson('/forms/contact/unlock', ['password' => 'nope'])->assertStatus(422);
    $key = $this->postJson('/forms/contact/unlock', ['password' => 'open-sesame'])->assertOk()->json('key');
    $this->postJson('/forms/contact', contactInput($form, [PasswordGate::FIELD => $key]))->assertOk();
    $this->flushSession();
    $this->postJson('/forms/contact', contactInput($form))->assertForbidden();
});

it('serves private forms through a signed share link only', function (): void {
    $form = contactForm(['settings' => ['visibility' => 'private']]);

    $this->get('/forms/contact')->assertForbidden();
    $this->getJson('/forms/contact/definition')->assertForbidden();

    $link = $form->shareUrl();
    $expiring = $form->shareUrl(now()->addDay());
    $expired = $form->shareUrl(now()->subMinute());

    expect($link)->toContain('signature=')->and($expiring)->toContain('expires=');

    $this->get($link)->assertOk()->assertSee('name="email"', false);
    $this->get($expiring)->assertOk();
    $this->get($expired)->assertForbidden();
    $this->get($link.'&embed=1')->assertOk()->assertSee('form-builder:resize');
});

it('anonymises and prunes old submissions', function (): void {
    config()->set('packstub-form-builder.submissions.anonymize_after_days', 10);
    $form = contactForm(['settings' => ['retention_days' => 30]]);
    $other = contactForm(['slug' => 'other']);

    $old = app(Submitter::class)->submit($form, contactInput($form), new SubmissionContext(ip: '1.1.1.1', userAgent: 'Old'))->submission;
    $recent = app(Submitter::class)->submit($form, contactInput($form), new SubmissionContext(ip: '2.2.2.2', userAgent: 'Recent'))->submission;
    $kept = app(Submitter::class)->submit($other, contactInput($other), new SubmissionContext(ip: '3.3.3.3'))->submission;
    $old->forceFill(['created_at' => now()->subDays(40)])->save();
    $recent->forceFill(['created_at' => now()->subDays(20)])->save();
    $kept->forceFill(['created_at' => now()->subDays(400)])->save();

    $this->artisan('form-builder:prune', ['--dry-run' => true])->expectsOutputToContain('Would delete 1 submission(s), would anonymise 3.')->assertSuccessful();
    expect(FormSubmission::query()->count())->toBe(3);

    $this->artisan('form-builder:prune')->assertSuccessful();

    expect(FormSubmission::query()->find($old->id))->toBeNull()
        ->and($recent->refresh()->ip)->toBeNull()
        ->and($recent->user_agent)->toBeNull()
        ->and($kept->refresh()->exists)->toBeTrue()
        ->and($kept->ip)->toBeNull();
});

it('still rejects tokens of other forms and unsigned ones', function (): void {
    $form = contactForm();
    $tokens = app(ProtectionToken::class);

    expect($tokens->isUsed($form, 'garbage'))->toBeFalse()
        ->and($tokens->age($form, $tokens->make(contactForm(['slug' => 'b']))))->toBeNull();
});

it('asks to reload the page once the token is older than token_ttl', function (): void {
    config(['packstub-form-builder.spam.token_ttl' => 60]);
    $form = contactForm(['settings' => ['visibility' => 'private']]);
    $tokens = app(ProtectionToken::class);
    $old = $tokens->make($form, time() - 61 * 60);

    expect($tokens->isExpired($form, $old))->toBeTrue()
        ->and($tokens->isExpired($form, $tokens->make($form)))->toBeFalse();

    $this->postJson('/forms/contact', contactInput($form, ['_fb_token' => $old]))
        ->assertStatus(422)
        ->assertJsonPath('message', 'This page has expired. Please reload it and send the form again.');
    $this->postJson('/forms/contact/validate', ['_fb_fields' => ['name'], 'name' => 'Ada', '_fb_token' => $old])->assertStatus(422);

    expect(FormSubmission::query()->count())->toBe(0);

    $this->postJson('/forms/contact', contactInput($form))->assertOk();
    expect(FormSubmission::query()->count())->toBe(1);
});

it('claims a token before storing, so a racing second post is dropped', function (): void {
    $form = contactForm();
    $input = contactInput($form);

    // The other request claimed the token after this one's spam check passed.
    app()->instance(SpamGuard::class, new class(app(ProtectionToken::class)) extends SpamGuard
    {
        public function reason(Form $form, array $input, SubmissionContext $context): ?string
        {
            app(ProtectionToken::class)->claim($form, $input['_fb_token']);

            return null;
        }
    });

    $result = app(Submitter::class)->submit($form, $input, new SubmissionContext);

    expect($result->spam)->toBeTrue()
        ->and(FormSubmission::query()->count())->toBe(0);
});

it('checks the submission limit again with the form locked, so a racing post can\'t take the last place', function (): void {
    $form = contactForm(['settings' => ['max_submissions' => 1, 'full_message' => 'All seats are taken.']]);

    // Another request stores the last place after this one passed the first check.
    app()->instance(SpamGuard::class, new class(app(ProtectionToken::class)) extends SpamGuard
    {
        public function reason(Form $form, array $input, SubmissionContext $context): ?string
        {
            FormSubmission::query()->create(['form_id' => $form->getKey(), 'number' => 1, 'data' => []]);

            return null;
        }
    });

    expect(fn () => app(Submitter::class)->submit($form, contactInput($form), new SubmissionContext))
        ->toThrow(FormClosedException::class, 'All seats are taken.')
        ->and(FormSubmission::query()->count())->toBe(1);
});

it('refuses a captcha solved on another site or for another reCAPTCHA action', function (): void {
    config()->set('packstub-form-builder.captcha.turnstile', ['site_key' => 'site', 'secret' => 'secret']);
    config()->set('packstub-form-builder.captcha.recaptcha', ['site_key' => 'site', 'secret' => 'secret', 'min_score' => 0.5]);
    config()->set('packstub-form-builder.spam.allowed_origins', ['*.acme.test']);
    $form = contactForm(['settings' => ['captcha' => 'turnstile']]);
    $captcha = app(Captcha::class);

    Http::fake([
        'challenges.cloudflare.com/*' => Http::sequence()
            ->push(['success' => true, 'hostname' => 'evil.example'])
            ->push(['success' => true, 'hostname' => 'www.acme.test'])
            ->push(['success' => true, 'hostname' => 'localhost']),
        'www.google.com/*' => Http::sequence()
            ->push(['success' => true, 'score' => 0.9, 'action' => 'login', 'hostname' => 'localhost'])
            ->push(['success' => true, 'score' => 0.9, 'action' => 'submit', 'hostname' => 'localhost']),
    ]);

    expect($captcha->verify($form, ['cf-turnstile-response' => 'x']))->toBe('The captcha could not be verified. Please try again.')
        ->and($captcha->verify($form, ['cf-turnstile-response' => 'x']))->toBeNull()
        ->and($captcha->verify($form, ['cf-turnstile-response' => 'x']))->toBeNull();

    $form->settings = ['captcha' => 'recaptcha'];

    expect($captcha->verify($form, ['g-recaptcha-response' => 'x']))->toBe('The captcha could not be verified. Please try again.')
        ->and($captcha->verify($form, ['g-recaptcha-response' => 'x']))->toBeNull();
});

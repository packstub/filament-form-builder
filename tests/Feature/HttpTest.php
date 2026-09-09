<?php

use Illuminate\Support\Facades\Crypt;
use Packstub\FormBuilder\Http\FormState;
use Packstub\FormBuilder\Models\FormSubmission;
use Packstub\FormBuilder\Submissions\ProtectionToken;

it('accepts a plain POST and redirects back with a flashed success message', function (): void {
    $form = contactForm();

    $response = $this->from('/contact-us')->post('/forms/contact', contactInput($form, ['_fb_return' => url('/contact-us?tab=2')]));

    $response->assertRedirect(url('/contact-us?tab=2').'#form-contact')
        ->assertSessionHas(FormState::successKey($form), 'Thank you, your message has been sent.');

    expect(FormSubmission::query()->count())->toBe(1)
        ->and(FormSubmission::query()->first()->source_url)->toBe(url('/contact-us?tab=2'))
        ->and(FormSubmission::query()->first()->channel)->toBe('web');
});

it('redirects back with errors in the form bag and the old input', function (): void {
    $form = contactForm();

    $response = $this->from('/contact-us')->post('/forms/contact', contactInput($form, ['email' => 'nope']));

    $response->assertRedirect(url('/contact-us').'#form-contact')
        ->assertSessionHasErrorsIn(FormState::errorBag($form), ['email'])
        ->assertSessionHasInput('name', 'Ada Lovelace')
        ->assertSessionMissing('_input.'.app(ProtectionToken::class)->field());

    expect(FormSubmission::query()->count())->toBe(0);
});

it('redirects to the configured URL after a successful POST', function (): void {
    $form = contactForm(['redirect_url' => 'https://example.com/thanks']);

    $this->post('/forms/contact', contactInput($form))->assertRedirect('https://example.com/thanks');
});

it('ignores return URLs on other hosts', function (): void {
    $form = contactForm();

    $this->post('/forms/contact', contactInput($form, ['_fb_return' => 'https://evil.test/phish']))
        ->assertRedirect(url('/forms/contact').'#form-contact');
});

it('answers JSON clients with the result or the errors', function (): void {
    $form = contactForm(['redirect_url' => '/thanks']);

    $this->postJson('/forms/contact', contactInput($form))
        ->assertOk()
        ->assertJson(['ok' => true, 'message' => 'Thank you, your message has been sent.', 'redirect' => '/thanks', 'id' => 1]);

    $this->postJson('/forms/contact', contactInput($form, ['email' => '']))
        ->assertStatus(422)
        ->assertJson(['ok' => false, 'message' => 'Please check the highlighted fields.'])
        ->assertJsonValidationErrors(['email']);

    expect(FormSubmission::query()->first()->channel)->toBe('json');
});

it('answers 403 for closed forms', function (): void {
    $form = contactForm(['is_active' => false]);

    $this->postJson('/forms/contact', contactInput($form))
        ->assertForbidden()
        ->assertJson(['ok' => false, 'message' => 'This form is closed.', 'errors' => ['form' => ['This form is closed.']]]);

    $this->from('/contact-us')->post('/forms/contact', contactInput($form))
        ->assertRedirect(url('/contact-us').'#form-contact')
        ->assertSessionHasErrorsIn(FormState::errorBag($form), ['form']);
});

it('serves the definition with a fresh protection token', function (): void {
    contactForm();

    $response = $this->getJson('/forms/contact/definition')->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    $json = $response->json();

    expect($json['slug'])->toBe('contact')
        ->and($json['fields'])->toHaveCount(8)
        ->and($json['protection']['token_field'])->toBe('_fb_token')
        ->and($json['protection']['honeypot_field'])->toBe('_fb_website')
        ->and($json['protection']['token'])->toBeString();

    $this->getJson('/forms/missing/definition')->assertNotFound();
});

it('renders the hosted page', function (): void {
    contactForm(['description' => 'We answer within a day.']);

    $this->get('/forms/contact')
        ->assertOk()
        ->assertSee('<h1 class="fb-page__title">Contact</h1>', false)
        ->assertSee('We answer within a day.')
        ->assertSee('name="email"', false)
        ->assertSee('name="_token"', false);
});

it('works without a session, carrying the state in the query string', function (): void {
    $this->rebootWith([
        'packstub-form-builder.routes.middleware' => [],
        'packstub-form-builder.routes.page_middleware' => [],
    ]);
    $form = contactForm();

    $success = $this->post('/forms/contact', contactInput($form, ['_fb_return' => url('/contact-us')]));
    $success->assertRedirect(url('/contact-us?fb_success=contact').'#form-contact');

    $failure = $this->post('/forms/contact', contactInput($form, ['_fb_return' => url('/contact-us'), 'email' => 'nope']));
    $target = $failure->headers->get('Location');
    parse_str((string) parse_url($target, PHP_URL_QUERY), $query);

    $state = FormState::fromQuery($form, $query['fb_state']);

    expect($target)->toStartWith(url('/contact-us?fb_state='))
        ->and($state->hasErrors())->toBeTrue()
        ->and($state->error('email'))->toBe('The Email field must be a valid email address.')
        ->and($state->old('name'))->toBe('Ada Lovelace')
        ->and($state->input)->not->toHaveKey('_fb_token');

    // The hosted page shows that state and never a CSRF field.
    $this->get('/forms/contact?'.http_build_query(['fb_state' => $query['fb_state']]))
        ->assertOk()
        ->assertDontSee('<input type="hidden" name="_token"', false)
        ->assertSee('The Email field must be a valid email address.')
        ->assertSee('value="Ada Lovelace"', false);
    $this->get('/forms/contact?fb_success=contact')
        ->assertOk()
        ->assertSee('Thank you, your message has been sent.');
});

it('ignores tampered query state', function (): void {
    $form = contactForm();

    expect(FormState::fromQuery($form, 'garbage')->hasErrors())->toBeFalse()
        ->and(FormState::fromQuery($form, Crypt::encryptString('{"f":"other","e":{"x":["y"]}}'))->hasErrors())->toBeFalse();
});

it('rate limits the submit endpoint', function (): void {
    $this->rebootWith(['packstub-form-builder.submissions.throttle' => '2,1']);
    $form = contactForm();

    $this->postJson('/forms/contact', contactInput($form))->assertOk();
    $this->postJson('/forms/contact', contactInput($form))->assertOk();
    $this->postJson('/forms/contact', contactInput($form))->assertStatus(429);
});

it('can turn the routes off', function (): void {
    $this->rebootWith(['packstub-form-builder.routes.enabled' => false]);
    contactForm();

    $this->get('/forms/contact')->assertNotFound();
});

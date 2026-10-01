<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Testing\TestResponse;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use Packstub\FormBuilder\Filament\Resources\FormResource\RelationManagers\ShareLinksRelationManager;
use Packstub\FormBuilder\Filament\Resources\FormResource\RelationManagers\SubmissionsRelationManager;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\ShareLink;
use Packstub\FormBuilder\Submissions\ProtectionToken;

use function Pest\Livewire\livewire;

function privateForm(): Form
{
    return contactForm(['settings' => ['visibility' => 'private']]);
}

function postThroughLink(Form $form, ?string $token): TestResponse
{
    return test()->postJson('/forms/'.$form->slug, [...contactInput($form), '_fb_link' => $token]);
}

it('opens a private form through its short link and records the link on the submission', function (): void {
    $form = privateForm();
    $link = $form->shareLinks()->create(['label' => 'Acme team']);

    expect($link->token)->toMatch('/^[a-z0-9]{20}$/')
        ->and($link->url())->toBe(url('/f/'.$link->token));

    $this->get('/forms/contact')->assertForbidden();

    $this->get('/f/'.$link->token)
        ->assertOk()
        ->assertSee('name="_fb_link" value="'.$link->token.'"', false);

    $this->get('/f/'.$link->token.'?embed=1')->assertOk();

    postThroughLink($form, $link->token)->assertOk()->assertJson(['ok' => true]);

    expect($form->submissions()->first()->share_link_id)->toBe($link->id)
        ->and($link->submissions()->count())->toBe(1);
});

it('closes a revoked, expired or full link without touching the others', function (): void {
    $form = privateForm();
    $revoked = $form->shareLinks()->create(['label' => 'Leaked']);
    $expired = $form->shareLinks()->create(['expires_at' => now()->subMinute()]);
    $full = $form->shareLinks()->create(['max_submissions' => 1]);
    $other = $form->shareLinks()->create();

    $revoked->revoke();

    $this->get('/f/'.$revoked->token)->assertForbidden()->assertSee('This link is no longer valid.');
    $this->get('/f/'.$expired->token)->assertForbidden();
    $this->get('/f/'.$other->token)->assertOk();
    $this->get('/f/unknowntoken')->assertNotFound();

    postThroughLink($form, $revoked->token)->assertForbidden()->assertJson(['message' => 'This link is no longer valid.']);
    postThroughLink($form, 'nope')->assertForbidden();
    postThroughLink($form, $full->token)->assertOk();
    postThroughLink($form, $full->token)->assertForbidden();

    expect($full->status())->toBe(ShareLink::FULL)
        ->and($revoked->status())->toBe(ShareLink::REVOKED)
        ->and($expired->status())->toBe(ShareLink::EXPIRED)
        ->and($other->isActive())->toBeTrue();
});

it('lets a headless client read a private definition with a link token', function (): void {
    $form = privateForm();
    $link = $form->shareLinks()->create();

    $this->getJson('/forms/contact/definition')->assertForbidden();
    $this->getJson('/forms/contact/definition?link='.$link->token)->assertOk()->assertJson(['slug' => 'contact']);

    $link->revoke();

    $this->getJson('/forms/contact/definition?link='.$link->token)->assertForbidden();
});

it('keeps the signed links of 1.2 working', function (): void {
    $form = privateForm();

    $this->get($form->shareUrl())->assertOk();
});

it('keeps submissions when a link is deleted', function (): void {
    $form = privateForm();
    $link = $form->shareLinks()->create();
    postThroughLink($form, $link->token)->assertOk();

    $link->delete();

    expect($form->submissions()->count())->toBe(1)
        ->and($form->submissions()->first()->share_link_id)->toBeNull();
});

function renderedToken(TestResponse $page): string
{
    preg_match('/name="'.app(ProtectionToken::class)->field().'" value="([^"]+)"/', $page->getContent(), $match);

    return html_entity_decode($match[1] ?? '');
}

it('refuses a private form posted without a render, and holds a rendered form to its link', function (): void {
    $form = privateForm();
    $link = $form->shareLinks()->create();
    $input = contactInput($form, [app(ProtectionToken::class)->field() => null]);

    $this->postJson('/forms/contact', $input)->assertForbidden()->assertJson(['message' => 'This form is private.']);

    $token = renderedToken($this->get('/f/'.$link->token)->assertOk());
    $link->revoke();

    // Dropping "_fb_link" does not get around the revoked link.
    $this->postJson('/forms/contact', [...$input, app(ProtectionToken::class)->field() => $token])
        ->assertForbidden()
        ->assertJson(['message' => 'This link is no longer valid.']);

    $signed = renderedToken($this->get($form->shareUrl())->assertOk());

    $this->postJson('/forms/contact', [...$input, app(ProtectionToken::class)->field() => $signed])->assertOk();

    expect($form->submissions()->count())->toBe(1);
});

it('binds the definition token of a headless client to its link', function (): void {
    $form = privateForm();
    $link = $form->shareLinks()->create();
    $token = $this->getJson('/forms/contact/definition?link='.$link->token)->json('protection.token');

    expect(app(ProtectionToken::class)->link($form, $token))->toBe($link->token);

    $link->revoke();

    $this->postJson('/forms/contact', contactInput($form, [app(ProtectionToken::class)->field() => $token]))->assertForbidden();
});

it('shows the success message on a single-use link after its submission without JavaScript', function (): void {
    $form = privateForm();
    $link = $form->shareLinks()->create(['max_submissions' => 1]);
    $page = url('/f/'.$link->token);

    $this->post('/forms/contact', [...contactInput($form), '_fb_link' => $link->token, '_fb_return' => $page])
        ->assertRedirect($page.'#form-contact');

    $this->get($page)->assertOk()->assertSee($form->successMessage());
    $this->get($page)->assertForbidden();
    $this->get($page.'?fb_success=contact')->assertOk()->assertDontSee('name="name"', false);
});

describe('panel', function (): void {
    beforeEach(function (): void {
        Filament::setCurrentPanel('admin');
        $this->actingAs(createUser());
    });

    it('creates a share link from the Share action on a private form', function (): void {
        $form = privateForm();

        livewire(EditForm::class, ['record' => $form->getRouteKey()])
            ->callAction('share', ['label' => 'Board', 'expires_at' => now()->addWeek()->toDateTimeString(), 'max_submissions' => 5])
            ->assertHasNoActionErrors()
            ->assertNotified(__('packstub-form-builder::form-builder.share.created'));

        $link = $form->shareLinks()->firstOrFail();

        expect($link->label)->toBe('Board')
            ->and($link->max_submissions)->toBe(5)
            ->and($link->expires_at)->not->toBeNull()
            ->and($link->user_id)->toBe(auth()->id());
    });

    it('offers a submission cap on a link only when the form stores submissions', function (): void {
        $form = privateForm();

        livewire(EditForm::class, ['record' => $form->getRouteKey()])
            ->mountAction('share')
            ->assertSchemaComponentVisible('max_submissions', 'mountedActionSchema0');

        $form->update(['store_submissions' => false]);

        livewire(EditForm::class, ['record' => $form->getRouteKey()])
            ->mountAction('share')
            ->assertSchemaComponentHidden('max_submissions', 'mountedActionSchema0');
    });

    it('lists, creates and revokes links in the relation manager', function (): void {
        $form = privateForm();
        $link = $form->shareLinks()->create(['label' => 'Acme']);

        livewire(ShareLinksRelationManager::class, ['ownerRecord' => $form, 'pageClass' => EditForm::class])
            ->assertCanSeeTableRecords([$link])
            ->assertSee(url('/f/'.$link->token))
            ->callTableAction('revoke', $link)
            ->callAction(TestAction::make('createLink')->table(), ['label' => 'Second']);

        expect($link->refresh()->revoked_at)->not->toBeNull()
            ->and($form->shareLinks()->where('label', 'Second')->exists())->toBeTrue();
    });

    it('shows the link a submission came through', function (): void {
        $form = privateForm();
        $link = $form->shareLinks()->create(['label' => 'Acme']);
        postThroughLink($form, $link->token)->assertOk();

        livewire(SubmissionsRelationManager::class, ['ownerRecord' => $form, 'pageClass' => EditForm::class])
            ->assertTableColumnExists('shareLink.label')
            ->assertSee('Acme')
            ->filterTable('share_link_id', $link->id)
            ->assertCountTableRecords(1);
    });
});

<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Testing\TestResponse;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use Packstub\FormBuilder\Filament\Resources\FormResource\RelationManagers\ShareLinksRelationManager;
use Packstub\FormBuilder\Filament\Resources\FormResource\RelationManagers\SubmissionsRelationManager;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\ShareLink;

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

<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Packstub\FormBuilder\Filament\Resources\FormResource;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\CreateForm;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\ListForms;
use Packstub\FormBuilder\Filament\Resources\FormResource\RelationManagers\SubmissionsRelationManager;
use Packstub\FormBuilder\Filament\SubmissionsCsv;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;
use Packstub\FormBuilder\Submissions\Submitter;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->actingAs(createUser());
});

it('lists forms with submission counts', function (): void {
    $form = contactForm();
    app(Submitter::class)->submit($form, contactInput($form));
    app(Submitter::class)->submit($form, contactInput($form));
    FormSubmission::query()->first()->markRead();

    $this->get(FormResource::getUrl('index'))->assertOk()->assertSee('Contact');

    livewire(ListForms::class)
        ->assertCanSeeTableRecords([$form])
        ->assertTableColumnStateSet('submissions_count', 2, $form)
        ->assertTableColumnFormattedStateSet('unread_submissions_count', '1', $form);

    expect(FormResource::getNavigationBadge())->toBe('1');
});

it('creates a form from the builder', function (): void {
    livewire(CreateForm::class)
        ->fillForm([
            'name' => 'Newsletter signup',
            'slug' => '',
            'fields' => [
                ['type' => 'email', 'data' => ['label' => 'Your email', 'key' => '', 'required' => true, 'width' => 'half']],
                ['type' => 'select', 'data' => ['label' => 'Frequency', 'key' => 'freq', 'choices' => ['weekly' => 'Weekly', 'daily' => 'Daily']]],
                ['type' => 'heading', 'data' => ['label' => 'Thanks', 'level' => 'h3']],
            ],
            'notification_emails' => ['inbox@example.com'],
            'settings' => ['honeypot' => true, 'min_seconds' => 3],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $form = Form::query()->where('name', 'Newsletter signup')->firstOrFail();

    expect($form->slug)->toBe('newsletter-signup')
        ->and($form->inputFields()->keys()->all())->toBe(['your_email', 'freq'])
        ->and($form->field('freq')->choices())->toBe(['weekly' => 'Weekly', 'daily' => 'Daily'])
        ->and($form->notificationEmails())->toBe(['inbox@example.com'])
        ->and($form->minSeconds())->toBe(3);
});

it('rejects duplicate field keys and invalid notification emails', function (): void {
    livewire(CreateForm::class)
        ->fillForm([
            'name' => 'Dupes',
            'fields' => [
                ['type' => 'text', 'data' => ['label' => 'Name', 'key' => 'name']],
                ['type' => 'email', 'data' => ['label' => 'Other', 'key' => 'name']],
            ],
            'notification_emails' => ['not-an-email'],
        ])
        ->call('create')
        ->assertHasFormErrors(['fields', 'notification_emails.0']);
});

it('edits a form and shows the embed snippets', function (): void {
    $form = contactForm();

    livewire(EditForm::class, ['record' => $form->getRouteKey()])
        ->assertOk()
        ->assertSee('<x-form-builder::form form="contact" />')
        ->assertSee('<livewire:form-builder form="contact" />')
        ->assertSee(url('/forms/contact/definition'))
        ->fillForm(['submit_label' => 'Go', 'is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($form->refresh()->submit_label)->toBe('Go')->and($form->is_active)->toBeFalse();
});

it('lists, views, marks and deletes submissions', function (): void {
    $form = contactForm();
    $first = app(Submitter::class)->submit($form, contactInput($form))->submission;
    $second = app(Submitter::class)->submit($form, contactInput($form, ['name' => 'Grace Hopper']))->submission;

    $manager = livewire(SubmissionsRelationManager::class, ['ownerRecord' => $form, 'pageClass' => EditForm::class])
        ->assertCanSeeTableRecords([$first, $second])
        ->assertSee('Grace Hopper')
        ->callTableAction('view', $second)
        ->assertSee('ada@example.com');

    expect($second->refresh()->isRead())->toBeTrue()->and($first->refresh()->isRead())->toBeFalse();

    $manager->callTableAction('toggleRead', $second);
    expect($second->refresh()->isRead())->toBeFalse();

    $manager->callTableBulkAction('markRead', [$first, $second]);
    expect(FormSubmission::query()->unread()->count())->toBe(0);

    $manager->filterTable('read_at', false)->assertCanNotSeeTableRecords([$first, $second]);

    $manager->resetTableFilters()->callTableAction('delete', $first);
    expect(FormSubmission::query()->count())->toBe(1);
});

it('exports submissions as CSV', function (): void {
    $form = contactForm();
    app(Submitter::class)->submit($form, contactInput($form, ['interests' => ['php', 'js'], 'newsletter' => '1']));

    // A submission with a key the form no longer has.
    FormSubmission::query()->create(['form_id' => $form->id, 'data' => ['name' => 'Old', 'legacy' => 'x'], 'fields' => ['legacy' => ['label' => 'Legacy', 'type' => 'text']]]);

    $response = livewire(SubmissionsRelationManager::class, ['ownerRecord' => $form, 'pageClass' => EditForm::class])
        ->callTableAction('export')
        ->assertFileDownloaded();

    $handle = fopen('php://memory', 'w+');
    SubmissionsCsv::write($form, $form->submissions()->getQuery(), $handle);
    rewind($handle);
    $rows = array_map('str_getcsv', array_filter(explode("\n", stream_get_contents($handle))));

    expect($rows[0])->toBe(['id', 'submitted_at', 'Name', 'Email', 'Topic', 'Message', 'Newsletter', 'Interests', 'Source', 'legacy', 'source_url', 'ip', 'user_id'])
        ->and($rows[1][2])->toBe('Ada Lovelace')
        ->and($rows[1][6])->toBe('Yes')
        ->and($rows[1][7])->toBe('PHP, JavaScript')
        ->and($rows[2][2])->toBe('Old')
        ->and($rows[2][9])->toBe('x');
});

it('hides the resource when the plugin says no', function (): void {
    $this->rebootWith(['packstub-form-builder.gate' => 'manage-forms']);
    Filament::setCurrentPanel('admin');
    $this->actingAs(createUser());

    expect(FormResource::canAccess())->toBeFalse();

    Gate::define('manage-forms', fn (): bool => true);

    expect(FormResource::canAccess())->toBeTrue();
});

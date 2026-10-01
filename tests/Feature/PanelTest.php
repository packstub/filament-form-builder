<?php

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader;
use Packstub\FormBuilder\Filament\FormActions;
use Packstub\FormBuilder\Filament\Resources\FormResource;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\CreateForm;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\ListForms;
use Packstub\FormBuilder\Filament\Resources\FormResource\RelationManagers\SubmissionsRelationManager;
use Packstub\FormBuilder\Filament\Resources\FormResource\RelationManagers\WebhookDeliveriesRelationManager;
use Packstub\FormBuilder\Filament\SubmissionsExport;
use Packstub\FormBuilder\FormBuilderPlugin;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\WebhookDelivery;
use Packstub\FormBuilder\Submissions\SubmissionContext;
use Packstub\FormBuilder\Submissions\Submitter;
use Packstub\FormBuilder\Templates\Templates;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->actingAs(createUser());
});

it('saves a form with sections, conditions, picked rules and every new setting', function (): void {
    livewire(CreateForm::class)
        ->fillForm([
            'name' => 'Registration',
            'fields' => [
                ['type' => 'section', 'data' => ['label' => 'Attendee', 'fields' => [
                    ['type' => 'text', 'data' => ['label' => 'Name', 'required' => true, 'width' => 'third', 'validation' => [['rule' => 'min', 'value' => '2']], 'message' => 'Name please']],
                    ['type' => 'radio', 'data' => ['label' => 'Attendance', 'key' => 'attendance', 'choices' => ['in_person' => 'In person', 'virtual' => 'Virtual']]],
                    ['type' => 'select', 'data' => ['label' => 'T-shirt', 'key' => 'tshirt', 'choices' => ['s' => 'S'], 'visibility' => 'when', 'visibility_logic' => 'any', 'visibility_rules' => [['field' => 'attendance', 'operator' => 'equals', 'value' => 'in_person']]]],
                    ['type' => 'text', 'data' => ['label' => 'Old', 'hidden' => true]],
                ]]],
                ['type' => 'rating', 'data' => ['label' => 'Score', 'max' => 10]],
            ],
            'settings' => [
                'mode' => 'wizard', 'layout' => 'horizontal', 'wizard_progress' => false, 'next_label' => 'Continue',
                'visibility' => 'private', 'password' => 'secret', 'one_per_person' => true, 'max_submissions' => 100,
                'captcha' => 'none', 'custom_css' => '.fb-form { color: red }', 'page_title' => 'Register',
                'notify_subject' => '{form_name} #{submission_number}', 'notify_reply_to' => 'respondent', 'notify_cc' => ['cc@example.com'],
                'autoresponder' => true, 'autoresponder_subject' => 'Thanks',
                'webhook_url' => 'https://hooks.example.com/x', 'webhook_secret' => 'whsec_abc', 'webhook_headers' => ['X-A' => 'b'],
                'retention_days' => 90,
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $form = Form::query()->where('slug', 'registration')->firstOrFail();

    expect($form->sections())->toHaveCount(2)
        ->and($form->sections()[0]->key)->toBe('attendee')
        ->and($form->inputFields()->keys()->all())->toBe(['name', 'attendance', 'tshirt', 'score'])
        ->and($form->allInputFields()->keys()->all())->toBe(['name', 'attendance', 'tshirt', 'old', 'score'])
        ->and($form->field('name')->width)->toBe('third')
        ->and($form->field('name')->rules())->toContain('min:2')
        ->and($form->field('name')->message)->toBe('Name please')
        ->and($form->field('tshirt')->visibility()->logic)->toBe('any')
        ->and($form->field('tshirt')->visibility()->rules[0]['value'])->toBe('in_person')
        ->and($form->isWizard())->toBeTrue()
        ->and($form->layout())->toBe('horizontal')
        ->and($form->showsProgress())->toBeFalse()
        ->and($form->nextLabel())->toBe('Continue')
        ->and($form->isPrivate())->toBeTrue()
        ->and($form->password())->toBe('secret')
        ->and($form->onePerPerson())->toBeTrue()
        ->and($form->maxSubmissions())->toBe(100)
        ->and($form->captcha())->toBeNull()
        ->and($form->customCss())->toBe('.fb-form { color: red }')
        ->and($form->pageTitle())->toBe('Register')
        ->and($form->setting('webhook_headers'))->toBe(['X-A' => 'b'])
        ->and($form->retentionDays())->toBe(90);
});

it('offers the other fields to a condition, including ones added in the same session', function (): void {
    $items = [
        ['type' => 'text', 'data' => ['label' => 'Name', 'key' => 'name']],
        ['type' => 'section', 'data' => ['label' => 'More', 'fields' => [
            ['type' => 'email', 'data' => ['label' => 'Work email']],
            ['type' => 'file', 'data' => ['label' => 'CV', 'key' => 'cv']],
            ['type' => 'heading', 'data' => ['label' => 'Title']],
        ]]],
    ];

    $options = FormResource::fieldOptions($items);

    expect($options)->toBe(['name' => 'Name (name)', 'work_email' => 'Work email (work_email)', 'cv' => 'CV (cv)'])
        ->and(FormResource::fieldOptions($items, ['email']))->toBe(['work_email' => 'Work email (work_email)']);
});

it('shares a form and updates its window', function (): void {
    $form = contactForm();

    livewire(EditForm::class, ['record' => $form->getRouteKey()])
        ->assertActionExists('share')
        ->callAction('share', ['opens_at' => '2026-10-01 09:00:00', 'closes_at' => '2026-10-31 18:00:00'])
        ->assertHasNoActionErrors()
        ->assertNotified(url('/forms/contact'));

    expect($form->refresh()->opens_at?->toDateTimeString())->toBe('2026-10-01 09:00:00')
        ->and($form->closes_at?->toDateTimeString())->toBe('2026-10-31 18:00:00');

    $form->update(['settings' => ['visibility' => 'private']]);

    expect(FormActions::linkFor($form, '2026-12-31 23:59:00'))->toContain('expires=', 'signature=')
        ->and(FormActions::linkFor($form, null))->not->toContain('expires=');
});

it('duplicates, exports and imports a form', function (): void {
    $form = contactForm(['settings' => ['mode' => 'single', 'password' => 'x']]);

    livewire(EditForm::class, ['record' => $form->getRouteKey()])
        ->callAction('duplicate', ['name' => 'Contact copy'])
        ->assertHasNoActionErrors()
        ->assertRedirect();

    $copy = Form::query()->where('name', 'Contact copy')->firstOrFail();

    expect($copy->slug)->toBe('contact-copy')
        ->and($copy->is_active)->toBeFalse()
        ->and($copy->fields)->toBe($form->fields)
        ->and($copy->password())->toBe('x');

    livewire(EditForm::class, ['record' => $form->getRouteKey()])
        ->callAction('exportJson')
        ->assertFileDownloaded('contact.form.json');

    $json = json_encode(['packstub-form-builder' => 1, 'form' => [...$form->toPortable(), 'name' => 'Imported', 'slug' => 'contact']], JSON_THROW_ON_ERROR);

    livewire(ListForms::class)
        ->callAction('importJson', ['file' => UploadedFile::fake()->createWithContent('contact.form.json', $json)])
        ->assertHasNoActionErrors()
        ->assertRedirect();

    $imported = Form::query()->where('name', 'Imported')->firstOrFail();

    expect($imported->slug)->toBe('contact-2')
        ->and($imported->inputFields()->keys()->all())->toBe($form->inputFields()->keys()->all());

    livewire(ListForms::class)
        ->callAction('importJson', ['file' => UploadedFile::fake()->createWithContent('bad.json', '{"nope": true}')])
        ->assertNotified('The file is not a form export.');
});

it('creates a form from a template', function (): void {
    expect(Templates::all())->toHaveCount(20)
        ->and(Templates::options())->toHaveKey('contact-form')
        ->and(Templates::find('event-registration')['form']['settings']['mode'])->toBe('wizard')
        ->and(Templates::find('nope'))->toBeNull();

    foreach (Templates::all() as $template) {
        $form = Form::fromArray($template['form']);
        expect($form->inputFields()->count())->toBeGreaterThan(0, $template['key']);
    }

    livewire(ListForms::class)
        ->callAction('useTemplate', ['template' => 'support-ticket', 'name' => 'Help desk'])
        ->assertHasNoActionErrors()
        ->assertRedirect();

    $form = Form::query()->where('name', 'Help desk')->firstOrFail();

    expect($form->slug)->toBe('help-desk')
        ->and($form->field('severity')->choices())->toHaveKey('critical')
        ->and($form->successMessage())->toBe('Ticket received. We reply within one business day.');
});

it('builds a preview definition from the unsaved builder state', function (): void {
    $form = contactForm(['settings' => ['password' => 'secret', 'captcha' => 'turnstile']]);

    $component = livewire(EditForm::class, ['record' => $form->getRouteKey()])
        ->fillForm(['name' => 'Renamed', 'fields' => [['type' => 'text', 'data' => ['label' => 'Only', 'key' => 'only']]]]);

    $definition = FormActions::previewDefinition($component->instance());

    expect($definition['name'])->toBe('Renamed')
        ->and($definition['slug'])->toBe('preview-contact')
        ->and($definition['store_submissions'])->toBeFalse()
        ->and($definition['settings']['password'])->toBeNull()
        ->and($definition['settings']['captcha'])->toBe('none')
        ->and(Form::fromArray($definition)->inputFields()->keys()->all())->toBe(['only']);

    $component->assertActionExists('preview');
});

it('shows a column per field, filters by choice and edits a submission', function (): void {
    $form = contactForm();
    $sales = app(Submitter::class)->submit($form, contactInput($form))->submission;
    $support = app(Submitter::class)->submit($form, contactInput($form, ['name' => 'Grace Hopper', 'topic' => 'support', 'newsletter' => '1', 'interests' => ['js']]))->submission;

    $manager = livewire(SubmissionsRelationManager::class, ['ownerRecord' => $form, 'pageClass' => EditForm::class])
        ->assertCanSeeTableRecords([$sales, $support])
        ->assertTableColumnExists('data.name')
        ->assertTableColumnExists('data.topic')
        ->assertTableColumnStateSet('number', 2, $support)
        ->assertSee('Grace Hopper');

    $manager->filterTable('field_topic', ['support'])
        ->assertCanSeeTableRecords([$support])
        ->assertCanNotSeeTableRecords([$sales]);

    $manager->resetTableFilters()->filterTable('field_interests', ['js'])
        ->assertCanSeeTableRecords([$support])
        ->assertCanNotSeeTableRecords([$sales]);

    $manager->resetTableFilters()->filterTable('field_newsletter', true)
        ->assertCanSeeTableRecords([$support])
        ->assertCanNotSeeTableRecords([$sales]);

    $manager->resetTableFilters()->searchTable('Grace')
        ->assertCanSeeTableRecords([$support])
        ->assertCanNotSeeTableRecords([$sales]);

    $manager->searchTable('')->sortTable('data.name', 'desc')
        ->assertCanSeeTableRecords([$support, $sales], inOrder: true);

    $manager->callTableAction('edit', $sales, ['name' => 'Ada King', 'email' => 'ADA@king.test', 'interests' => ['php', 'js']])
        ->assertHasNoTableActionErrors()
        ->assertNotified('Submission saved.');

    expect($sales->refresh()->value('name'))->toBe('Ada King')
        ->and($sales->value('email'))->toBe('ada@king.test')
        ->and($sales->value('interests'))->toBe(['php', 'js'])
        ->and($sales->value('topic'))->toBe('sales');
});

it('exports submissions as Excel when OpenSpout is around', function (): void {
    $form = contactForm();
    app(Submitter::class)->submit($form, contactInput($form));

    expect(SubmissionsExport::hasXlsx())->toBeTrue();

    livewire(SubmissionsRelationManager::class, ['ownerRecord' => $form, 'pageClass' => EditForm::class])
        ->callTableAction('exportXlsx')
        ->assertFileDownloaded('contact-submissions-'.now()->format('Y-m-d').'.xlsx');

    $path = tempnam(sys_get_temp_dir(), 'fb').'.xlsx';
    SubmissionsExport::writeXlsx($form, $form->submissions()->getQuery(), $path);

    $reader = new Reader;
    $reader->open($path);
    $rows = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }

    $reader->close();
    unlink($path);

    expect($rows[0][3])->toBe('Name')->and($rows[1][3])->toBe('Ada Lovelace')->and($rows[1][0])->toBe(1);
});

it('lists the webhook deliveries with a retry', function (): void {
    Http::fake(['hooks.example.com/*' => Http::sequence()->push('down', 503)->push('ok', 200)]);
    config()->set('packstub-form-builder.webhooks.attempts', 1);
    $form = contactForm(['settings' => ['webhook_url' => 'https://hooks.example.com/x']]);
    app(Submitter::class)->submit($form, contactInput($form));

    $delivery = WebhookDelivery::query()->firstOrFail();
    expect($delivery->status)->toBe('failed');

    expect(WebhookDeliveriesRelationManager::canViewForRecord($form, EditForm::class))->toBeTrue()
        ->and(WebhookDeliveriesRelationManager::canViewForRecord(contactForm(['slug' => 'plain']), EditForm::class))->toBeFalse()
        ->and(WebhookDeliveriesRelationManager::getBadge($form, EditForm::class))->toBe('1');

    livewire(WebhookDeliveriesRelationManager::class, ['ownerRecord' => $form, 'pageClass' => EditForm::class])
        ->assertCanSeeTableRecords([$delivery])
        ->callTableAction('retry', $delivery);

    expect($delivery->refresh()->status)->toBe('delivered')->and($delivery->attempts)->toBe(1);
});

it('shows file downloads in the submission details', function (): void {
    Storage::fake('local');
    $form = Form::query()->create(['name' => 'Files', 'slug' => 'files', 'fields' => [field('file', 'Doc', ['key' => 'doc'])]]);
    $submission = app(Submitter::class)->submit($form, ['doc' => [UploadedFile::fake()->createWithContent('brief.pdf', 'x')]], (new SubmissionContext)->trusted())->submission;

    $manager = livewire(SubmissionsRelationManager::class, ['ownerRecord' => $form, 'pageClass' => EditForm::class])
        ->callTableAction('view', $submission)
        ->assertHasNoTableActionErrors()
        ->assertSee('brief.pdf');

    // The modal body is not in the snapshot: read the entry the details schema builds.
    $method = new ReflectionMethod($manager->instance(), 'detailsSchema');
    $html = Schema::make($manager->instance())
        ->record($submission->refresh())
        ->components($method->invoke($manager->instance(), $submission))
        ->toHtml();

    expect($html)->toContain('<a href="'.url('/forms/files/'.$submission->id.'/doc/0'), 'brief.pdf</a>');
});

it('summarises conditions and rules in the block sections and hides requirement rules until they apply', function (): void {
    $plain = contactForm();

    livewire(EditForm::class, ['record' => $plain->getRouteKey()])
        ->assertSee('Always visible')
        ->assertSee('No extra rules')
        ->assertDontSee('All conditions')
        ->assertDontSee('Add condition');

    $logic = contactForm([
        'slug' => 'logic',
        'fields' => [
            field('select', 'Topic', ['choices' => ['sales' => 'Sales', 'support' => 'Support']]),
            field('text', 'Company', [
                'required' => true,
                'visibility' => 'when',
                'visibility_rules' => [['field' => 'topic', 'operator' => 'equals', 'value' => 'sales'], ['field' => 'topic', 'operator' => 'is_not_empty']],
                'requirement' => 'unless',
                'requirement_rules' => [['field' => 'topic', 'operator' => 'equals', 'value' => 'support']],
                'validation' => [['rule' => 'min', 'value' => '2']],
                'message' => 'Tell us the company.',
                'rules' => ['max:100'],
            ]),
        ],
    ]);

    livewire(EditForm::class, ['record' => $logic->getRouteKey()])
        ->assertSee('Shown when 2 conditions hold · Not required when 1 condition holds')
        ->assertSee('2 rules · custom message')
        ->assertSee('Add condition')
        ->assertSeeHtml('/forms/logic/definition<br />');
});

it('is found on its panel while a panel without it is current', function (): void {
    // Filament registers every panel's routes while the default panel is current.
    Filament::registerPanel(Panel::make()->id('plain')->path('plain'));
    Filament::setCurrentPanel('plain');

    expect(FormBuilderPlugin::get())->toBe(Filament::getPanel('admin')->getPlugin(FormBuilderPlugin::ID))
        ->and(ListForms::getResource())->toBe(FormResource::class);
});

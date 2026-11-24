<?php

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Packstub\FormBuilder\Facades\FormBuilder;
use Packstub\FormBuilder\Fields\ChoiceSources;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\ListForms;
use Packstub\FormBuilder\FormBuilderPlugin;
use Packstub\FormBuilder\Livewire\FormBuilderForm;
use Packstub\FormBuilder\Mail\SubmissionNotification;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Submissions\SubmissionContext;
use Packstub\FormBuilder\Submissions\Submitter;
use Packstub\FormBuilder\Tests\Fixtures\Team;
use Packstub\FormBuilder\Tests\Fixtures\TeamPanelProvider;
use Packstub\FormBuilder\Tests\Fixtures\User;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->rebootWith(['packstub-form-builder.tenancy.enabled' => true], TeamPanelProvider::class);

    $this->ada = createUser(['name' => 'Ada']);
    $this->bob = createUser(['name' => 'Bob']);
    $this->acme = createTeam('acme', $this->ada);
    $this->globex = createTeam('globex', $this->bob);
});

it('links to the submissions of a form outside its workspace', function (): void {
    Filament::setTenant($this->acme, isQuiet: true);
    $form = contactForm();
    Filament::setTenant(null, isQuiet: true);

    expect(FormBuilderPlugin::submissionsUrl($form))->toBe('http://localhost/admin/acme/forms/contact/edit#submissions');
});

it('knows the tenant model and the tenant of a form from the panel', function (): void {
    Filament::setTenant($this->globex, isQuiet: true);
    $form = contactForm();

    expect(FormBuilder::tenantModel())->toBe(Team::class)
        ->and(FormBuilder::tenantOf($form)?->is($this->globex))->toBeTrue()
        ->and($form->tenant->is($this->globex))->toBeTrue()
        ->and(FormBuilder::tenantOf(new Form))->toBeNull();
});

it('has no panel link for a form without a tenant', function (): void {
    $form = contactForm();

    expect($form->tenant_id)->toBeNull()
        ->and(FormBuilderPlugin::submissionsUrl($form))->toBeNull();
});

it('puts the panel link in the team email and the panel notification of a public submission', function (): void {
    Mail::fake();
    Schema::create('notifications', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('type');
        $table->morphs('notifiable');
        $table->text('data');
        $table->timestamp('read_at')->nullable();
        $table->timestamps();
    });
    Filament::setTenant($this->acme, isQuiet: true);
    $form = contactForm(['notification_emails' => ['inbox@example.com'], 'settings' => ['notify_users' => [$this->ada->id]]]);
    Filament::setTenant(null, isQuiet: true);

    $this->post('/forms/contact', contactInput($form))->assertRedirect();

    $submission = $form->submissions()->sole();

    expect((new SubmissionNotification($submission))->render())->toContain('http://localhost/admin/acme/forms/contact/edit#submissions')
        ->and($this->ada->notifications()->sole()->data['actions'][0]['url'])->toBe('http://localhost/admin/acme/forms/contact/edit#submissions');
});

it('lists the forms of the current workspace only', function (): void {
    Filament::setCurrentPanel('admin');
    Filament::setTenant($this->acme, isQuiet: true);
    $acme = contactForm(['slug' => 'acme-contact']);

    Filament::setTenant($this->globex, isQuiet: true);
    $globex = contactForm(['slug' => 'globex-contact']);

    $this->actingAs($this->bob);

    livewire(ListForms::class)
        ->assertCanSeeTableRecords([$globex])
        ->assertCanNotSeeTableRecords([$acme]);
});

/**
 * A form of $team with a dropdown fed by a source that lists the members of
 * the current workspace, as a tenant-scoped query would.
 */
function memberPickerForm(Team $team): Form
{
    FormBuilder::choices('members', fn (): array => Filament::getTenant() instanceof Team
        ? Filament::getTenant()->users()->pluck('name', 'users.id')->all()
        : User::query()->pluck('name', 'id')->all());

    Filament::setTenant($team, isQuiet: true);
    $form = Form::query()->create([
        'name' => 'Pick',
        'slug' => 'pick',
        'fields' => [field('select', 'Member', ['key' => 'member', 'choices_source' => 'members', 'required' => true])],
    ]);
    Filament::setTenant(null, isQuiet: true);

    return $form;
}

it('runs a choice source inside the form\'s workspace on public pages', function (): void {
    $form = memberPickerForm($this->acme);

    $html = Blade::render('<x-form-builder::form form="pick" />');
    expect($html)->toContain('Ada')->not->toContain('Bob')
        ->and(Filament::getTenant())->toBeNull();

    app(ChoiceSources::class)->flush();
    $this->getJson('/forms/pick/definition')->assertOk()
        ->assertJsonPath('fields.0.choices', [(string) $this->ada->id => 'Ada']);

    app(ChoiceSources::class)->flush();
    livewire(FormBuilderForm::class, ['form' => 'pick'])->assertSee('Ada')->assertDontSee('Bob');

    app(ChoiceSources::class)->flush();
    expect(fn () => app(Submitter::class)->submit($form, ['member' => (string) $this->bob->id], (new SubmissionContext)->trusted()))
        ->toThrow(ValidationException::class)
        ->and(Filament::getTenant())->toBeNull();
});

it('keeps the current workspace when one is set', function (): void {
    $form = memberPickerForm($this->acme);
    Filament::setTenant($this->globex, isQuiet: true);

    expect($form->field('member')->choices())->toBe([(string) $this->bob->id => 'Bob'])
        ->and(Filament::getTenant()->is($this->globex))->toBeTrue();
});

it('takes the tenant model of the current panel when panels differ', function (): void {
    $other = Filament::getPanel('admin')->getId();
    $panel = Panel::make()->id('people')->path('people')->tenant(User::class)->plugin(FormBuilderPlugin::make());
    Filament::registerPanel($panel);

    expect(FormBuilder::tenantModel())->toBe(Team::class);

    Filament::setCurrentPanel($panel);

    expect(FormBuilder::tenantModel())->toBe(User::class);

    Filament::setCurrentPanel($other);
});

<?php

use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Packstub\FormBuilder\Facades\FormBuilder;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\ListForms;
use Packstub\FormBuilder\FormBuilderPlugin;
use Packstub\FormBuilder\Mail\SubmissionNotification;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Tests\Fixtures\Team;
use Packstub\FormBuilder\Tests\Fixtures\TeamPanelProvider;

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

<?php

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Packstub\FormBuilder\Facades\FormBuilder;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use Packstub\FormBuilder\Submissions\Submitter;
use Packstub\FormBuilder\Tests\Fixtures\TeamPanelProvider;

use function Pest\Livewire\livewire;

it('offers every user to notify outside tenancy', function (): void {
    $ada = createUser(['name' => 'Ada']);
    $bob = createUser(['name' => 'Bob']);

    expect(FormBuilder::panelUsers()->modelKeys())->toBe([$ada->id, $bob->id]);

    FormBuilder::panelUsersUsing(fn (Builder $query, ?Model $tenant): Builder => $query->where('name', 'Bob'));

    expect(FormBuilder::panelUsers()->modelKeys())->toBe([$bob->id]);
});

describe('in a panel with tenancy', function (): void {
    beforeEach(function (): void {
        $this->rebootWith(['packstub-form-builder.tenancy.enabled' => true], TeamPanelProvider::class);

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        $this->ada = createUser(['name' => 'Ada']);
        $this->bob = createUser(['name' => 'Bob']);
        $this->grace = createUser(['name' => 'Grace']);
        $this->acme = createTeam('acme', $this->ada, $this->grace);
        $this->globex = createTeam('globex', $this->bob);
    });

    it('offers the members of the workspace only', function (): void {
        expect(FormBuilder::panelUsers($this->acme)->modelKeys())->toBe([$this->ada->id, $this->grace->id])
            ->and(FormBuilder::panelUsers($this->globex)->modelKeys())->toBe([$this->bob->id])
            ->and(FormBuilder::panelUsers($this->acme, limit: 1)->modelKeys())->toBe([$this->ada->id]);

        // A narrowed query still passes the membership check.
        FormBuilder::panelUsersUsing(fn (Builder $query, ?Model $tenant): Builder => $query->whereIn('name', ['Bob', 'Grace']));

        expect(FormBuilder::panelUsers($this->acme)->modelKeys())->toBe([$this->grace->id]);
    });

    it('shows the members of the current workspace in the picker and refuses anyone else', function (): void {
        Filament::setCurrentPanel('admin');
        Filament::setTenant($this->acme, isQuiet: true);
        $form = contactForm();
        $this->actingAs($this->ada);

        livewire(EditForm::class, ['record' => $form->getRouteKey()])
            ->assertFormFieldExists('settings.notify_users', fn (Select $field): bool => $field->getOptions() === [
                $this->ada->id => 'Ada',
                $this->grace->id => 'Grace',
            ])
            ->fillForm(['settings.notify_users' => [$this->bob->id]])
            ->call('save')
            ->assertHasFormErrors(['settings.notify_users.0']);

        expect($form->refresh()->setting('notify_users'))->toBeEmpty();
    });

    it('does not notify a picked user who is no member of the form\'s workspace', function (): void {
        Filament::setTenant($this->acme, isQuiet: true);
        $form = contactForm(['settings' => ['notify_users' => [$this->ada->id, $this->bob->id, $this->grace->id]]]);
        Filament::setTenant(null, isQuiet: true);

        // Grace left the workspace after she was picked.
        $this->acme->users()->detach($this->grace->id);

        app(Submitter::class)->submit($form, contactInput($form));

        expect(DB::table('notifications')->pluck('notifiable_id')->all())->toBe([$this->ada->id]);
    });
});

describe('members of a large workspace', function (): void {
    beforeEach(function (): void {
        $this->rebootWith(['packstub-form-builder.tenancy.enabled' => true], TeamPanelProvider::class);

        $this->ada = createUser(['name' => 'Ada']);
        $this->bob = createUser(['name' => 'Bob']);
        $this->acme = createTeam('acme', $this->ada);
        createTeam('globex', $this->bob);
    });

    it('finds the members through the tenant relationship in one query', function (): void {
        DB::enableQueryLog();

        expect(FormBuilder::panelUsers($this->acme)->modelKeys())->toBe([$this->ada->id])
            ->and(DB::getQueryLog())->toHaveCount(1);
    });

    it('asks each user, up to a limit, when the tenant has no members relationship', function (): void {
        config()->set('packstub-form-builder.tenancy.members', 'nothing');

        expect(FormBuilder::panelUsers($this->acme)->modelKeys())->toBe([$this->ada->id]);
    });
});

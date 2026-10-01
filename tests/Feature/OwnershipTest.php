<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Packstub\FormBuilder\Filament\Resources\FormResource;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\ListForms;
use Packstub\FormBuilder\Models\Form;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->ada = createUser(['name' => 'Ada']);
    $this->bob = createUser(['name' => 'Bob']);

    $this->actingAs($this->ada);
    $this->adas = contactForm(['slug' => 'adas']);

    $this->actingAs($this->bob);
    $this->bobs = contactForm(['slug' => 'bobs', 'name' => 'Bob form']);
});

it('records the user who created a form', function (): void {
    expect($this->adas->user_id)->toBe($this->ada->id)
        ->and($this->bobs->owner->name)->toBe('Bob');

    auth()->logout();

    expect(contactForm(['slug' => 'anonymous'])->user_id)->toBeNull();
});

it('shows every form by default, with a My forms filter', function (): void {
    livewire(ListForms::class)
        ->assertCanSeeTableRecords([$this->adas, $this->bobs])
        ->filterTable('mine')
        ->assertCanSeeTableRecords([$this->bobs])
        ->assertCanNotSeeTableRecords([$this->adas]);
});

it('limits users to their own forms with ownership.only_own', function (): void {
    config()->set('packstub-form-builder.ownership.only_own', true);

    livewire(ListForms::class)
        ->assertCanSeeTableRecords([$this->bobs])
        ->assertCanNotSeeTableRecords([$this->adas]);

    livewire(EditForm::class, ['record' => $this->adas->getRouteKey()])->assertNotFound();

    expect(FormResource::getEloquentQuery()->pluck('slug')->all())->toBe(['bobs']);
});

it('lets users with the see_all ability see every form', function (): void {
    config()->set('packstub-form-builder.ownership.only_own', true);
    config()->set('packstub-form-builder.ownership.see_all', 'manage-all-forms');
    Gate::define('manage-all-forms', fn ($user): bool => $user->name === 'Bob');

    expect(FormResource::getEloquentQuery()->pluck('slug')->sort()->values()->all())->toBe(['adas', 'bobs']);

    $this->actingAs($this->ada);

    expect(FormResource::getEloquentQuery()->pluck('slug')->all())->toBe(['adas']);
});

it('keeps the public routes open to every form', function (): void {
    config()->set('packstub-form-builder.ownership.only_own', true);

    $this->get('/forms/adas')->assertOk();
    expect(Form::query()->count())->toBe(2);
});

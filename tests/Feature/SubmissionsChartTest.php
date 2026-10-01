<?php

use Filament\Facades\Filament;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use Packstub\FormBuilder\Filament\Widgets\SubmissionsChart;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->actingAs(createUser());
    $this->travelTo(now()->setDate(2026, 9, 30)->setTime(12, 0));
});

function submissionAt(Form $form, string $when, bool $read = false): void
{
    $submission = FormSubmission::query()->create(['form_id' => $form->id, 'data' => ['name' => 'x'], 'read_at' => $read ? now() : null]);
    $submission->forceFill(['created_at' => $when])->save();
}

it('counts submissions per day over the chosen range, with totals', function (): void {
    $form = contactForm();
    submissionAt($form, '2026-09-30 08:00:00');
    submissionAt($form, '2026-09-30 09:00:00', read: true);
    submissionAt($form, '2026-09-28 10:00:00', read: true);
    submissionAt($form, '2026-08-01 10:00:00', read: true);

    $widget = livewire(SubmissionsChart::class, ['record' => $form])
        ->assertSee('4 in total · 3 in the last 7 days · 1 unread');

    $counts = $widget->instance()->perDay();

    expect($counts)->toHaveCount(30)
        ->and($counts['2026-09-30'])->toBe(2)
        ->and($counts['2026-09-28'])->toBe(1)
        ->and(array_sum($counts))->toBe(3);

    $widget->set('filter', '90');

    expect(array_sum($widget->instance()->perDay()))->toBe(4)
        ->and($widget->instance()->perDay())->toHaveCount(90);
});

it('shows the chart on the edit page once the form has submissions', function (): void {
    $form = contactForm();

    livewire(EditForm::class, ['record' => $form->getRouteKey()])->assertDontSeeLivewire(SubmissionsChart::class);

    submissionAt($form, '2026-09-29 10:00:00');

    livewire(EditForm::class, ['record' => $form->getRouteKey()])->assertSeeLivewire(SubmissionsChart::class);
});

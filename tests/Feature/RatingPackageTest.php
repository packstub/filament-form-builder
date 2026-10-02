<?php

use Filament\Facades\Filament;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Livewire\Features\SupportTesting\Testable;
use Packstub\FilamentRating\Columns\RatingColumn;
use Packstub\FilamentRating\Components\Rating;
use Packstub\FilamentRating\Entries\RatingEntry;
use Packstub\FilamentRating\Filters\RatingFilter;
use Packstub\FilamentRating\Summarizers\RatingAverage;
use Packstub\FilamentRating\Summarizers\RatingDistribution;
use Packstub\FormBuilder\Fields\Types\RatingField;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use Packstub\FormBuilder\Filament\Resources\FormResource\RelationManagers\SubmissionsRelationManager;
use Packstub\FormBuilder\Livewire\FormBuilderForm;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;
use Packstub\FormBuilder\Submissions\SubmissionContext;
use Packstub\FormBuilder\Submissions\Submitter;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->actingAs(createUser());

    $this->form = Form::query()->create(['name' => 'Survey', 'slug' => 'survey', 'fields' => [
        field('text', 'Name', ['key' => 'name']),
        field('rating', 'Score', ['key' => 'score', 'max' => 10]),
    ]]);
});

afterEach(function (): void {
    RatingField::$usePackage = null;
});

function rate(Form $form, ?int $score): FormSubmission
{
    return app(Submitter::class)->submit($form, ['name' => 'Ada', 'score' => $score], (new SubmissionContext)->trusted())->submission;
}

function detailsHtml(Testable $manager, FormSubmission $submission): string
{
    $method = new ReflectionMethod($manager->instance(), 'detailsSchema');

    return Schema::make($manager->instance())
        ->record($submission->refresh())
        ->components($method->invoke($manager->instance(), $submission))
        ->toHtml();
}

it('detects the package', function (): void {
    expect(RatingField::available())->toBeTrue();

    RatingField::$usePackage = false;

    expect(RatingField::available())->toBeFalse();
});

it('uses the star input, column, summarizers, filter and entry when the package is installed', function (): void {
    $field = $this->form->field('score');

    $component = $field->type->formComponent($field);
    $column = $field->type->tableColumn($field);
    $filter = $field->type->tableFilter($field);
    $entry = $field->type->detailEntry($field);

    expect($component)->toBeInstanceOf(Rating::class)
        ->and($component->getStars())->toBe(10)
        ->and($column)->toBeInstanceOf(RatingColumn::class)
        ->and($column->getStars())->toBe(10)
        ->and(array_map(fn ($summarizer): string => $summarizer::class, array_values($column->getSummarizers())))
        ->toBe([RatingAverage::class, RatingDistribution::class])
        ->and($filter)->toBeInstanceOf(RatingFilter::class)
        ->and($filter->getStars())->toBe(10)
        ->and($entry)->toBeInstanceOf(RatingEntry::class)
        ->and($entry->getStars())->toBe(10);
});

it('keeps today\'s buttons, text column and text details without the package', function (): void {
    RatingField::$usePackage = false;
    $field = $this->form->field('score');

    expect($field->type->formComponent($field))->toBeInstanceOf(ToggleButtons::class)
        ->and($field->type->tableColumn($field))->toBeNull()
        ->and($field->type->tableFilter($field))->toBeNull()
        ->and($field->type->detailEntry($field))->toBeNull();

    $submission = rate($this->form, 7);

    $manager = livewire(SubmissionsRelationManager::class, ['ownerRecord' => $this->form, 'pageClass' => EditForm::class])
        ->assertTableColumnExists('data.score', fn ($column): bool => $column instanceof TextColumn)
        ->assertTableColumnStateSet('data.score', '7 / 10', $submission);

    expect(detailsHtml($manager, $submission))->toContain('7 / 10');
});

it('shows stars, the average, the distribution and a filter in the submissions table', function (): void {
    $high = rate($this->form, 9);
    $mid = rate($this->form, 6);
    $low = rate($this->form, 3);
    $unrated = rate($this->form, null);

    $manager = livewire(SubmissionsRelationManager::class, ['ownerRecord' => $this->form, 'pageClass' => EditForm::class])
        ->assertTableColumnExists('data.score', fn ($column): bool => $column instanceof RatingColumn)
        ->assertTableColumnStateSet('data.score', 9, $high)
        ->assertTableColumnSummarySet('data.score', 'average', 6.0)
        ->assertTableColumnSummarySet('data.score', 'distribution', [9 => 1, 6 => 1, 3 => 1])
        ->assertSee('Average');

    $manager->filterTable('field_score', 6)
        ->assertCanSeeTableRecords([$high, $mid])
        ->assertCanNotSeeTableRecords([$low, $unrated])
        ->assertTableColumnSummarySet('data.score', 'average', 7.5);

    expect(detailsHtml($manager, $high))->toContain('fi-in-rating')
        ->not->toContain('9 / 10');
});

it('sorts the star column by the score as a number', function (): void {
    $ten = rate($this->form, 10);
    $two = rate($this->form, 2);
    $nine = rate($this->form, 9);

    livewire(SubmissionsRelationManager::class, ['ownerRecord' => $this->form, 'pageClass' => EditForm::class])
        ->sortTable('data.score')
        ->assertCanSeeTableRecords([$two, $nine, $ten], inOrder: true)
        ->sortTable('data.score', 'desc')
        ->assertCanSeeTableRecords([$ten, $nine, $two], inOrder: true);
});

it('submits stars from the Livewire renderer', function (): void {
    livewire(FormBuilderForm::class, ['form' => 'survey'])
        ->assertFormFieldExists('score', fn ($component): bool => $component instanceof Rating)
        ->fillForm(['name' => 'Ada', 'score' => 8])
        ->call('submit')
        ->assertHasNoFormErrors();

    expect(FormSubmission::query()->firstOrFail()->data['score'])->toBe(8);

    livewire(FormBuilderForm::class, ['form' => 'survey'])
        ->fillForm(['name' => 'Ada', 'score' => 11])
        ->call('submit')
        ->assertHasFormErrors(['score']);
});

it('reads a rating saved without the package with it, and the other way round', function (): void {
    RatingField::$usePackage = false;

    livewire(FormBuilderForm::class, ['form' => 'survey'])
        ->assertFormFieldExists('score', fn ($component): bool => $component instanceof ToggleButtons)
        ->fillForm(['name' => 'Ada', 'score' => '4'])
        ->call('submit')
        ->assertHasNoFormErrors();

    RatingField::$usePackage = true;

    livewire(FormBuilderForm::class, ['form' => 'survey'])
        ->fillForm(['name' => 'Grace', 'score' => 8])
        ->call('submit')
        ->assertHasNoFormErrors();

    [$before, $after] = FormSubmission::query()->orderBy('id')->get()->all();

    expect($before->data['score'])->toBe(4)
        ->and($after->data['score'])->toBe(8);

    livewire(SubmissionsRelationManager::class, ['ownerRecord' => $this->form, 'pageClass' => EditForm::class])
        ->assertTableColumnStateSet('data.score', 4, $before)
        ->assertTableColumnSummarySet('data.score', 'average', 6.0);

    RatingField::$usePackage = false;

    livewire(SubmissionsRelationManager::class, ['ownerRecord' => $this->form, 'pageClass' => EditForm::class])
        ->assertTableColumnStateSet('data.score', '8 / 10', $after);

    expect($this->form->field('score')->type->format($after->data['score'], $this->form->field('score')))->toBe('8 / 10');
});

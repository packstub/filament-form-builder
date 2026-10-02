<?php

namespace Packstub\FormBuilder\Fields\Types;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\Entry;
use Filament\Schemas\Components\Component;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Packstub\FilamentRating\Columns\RatingColumn;
use Packstub\FilamentRating\Components\Rating;
use Packstub\FilamentRating\Entries\RatingEntry;
use Packstub\FilamentRating\Filters\RatingFilter;
use Packstub\FilamentRating\Summarizers\RatingAverage;
use Packstub\FilamentRating\Summarizers\RatingDistribution;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\FieldType;
use Packstub\FormBuilder\Fields\ValidationRules;
use Packstub\FormBuilder\Models\FormSubmission;

/**
 * A score from 1 to N (5 stars by default). When packstub/filament-rating is
 * installed, the panel and the Livewire renderer show its stars, and the
 * submissions table gets a star column with the average and distribution
 * and a "4 stars & up" filter. The stored value is the same integer either
 * way.
 */
class RatingField extends FieldType
{
    /**
     * Forces the package on or off (tests); null detects it.
     */
    public static ?bool $usePackage = null;

    public static function id(): string
    {
        return 'rating';
    }

    /**
     * Whether packstub/filament-rating is installed.
     */
    public static function available(): bool
    {
        return static::$usePackage ?? class_exists(Rating::class);
    }

    public function icon(): string
    {
        return 'heroicon-o-star';
    }

    public function ruleCategory(): ?string
    {
        return ValidationRules::CATEGORY_NUMBER;
    }

    public function hasPlaceholder(): bool
    {
        return false;
    }

    public function editorSchema(): array
    {
        return [
            TextInput::make('max')
                ->label(__('packstub-form-builder::form-builder.editor.max_stars'))
                ->helperText(__('packstub-form-builder::form-builder.editor.max_stars_hint'))
                ->integer()
                ->minValue(1)
                ->maxValue(10)
                ->default(5),
        ];
    }

    public function max(Field $field): int
    {
        return max(1, min(10, (int) ($field->option('max') ?: 5)));
    }

    public function rules(Field $field): array
    {
        return ['integer', 'between:1,'.$this->max($field)];
    }

    public function normalize(mixed $value, Field $field): mixed
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    public function format(mixed $value, Field $field): string
    {
        return $value === null || $value === '' ? '' : $value.' / '.$this->max($field);
    }

    public function formComponent(Field $field): Component
    {
        if (static::available()) {
            return $this->configure(Rating::make($field->key)->stars($this->max($field))->clearable(! $field->required), $field);
        }

        $options = [];

        for ($score = 1; $score <= $this->max($field); $score++) {
            $options[$score] = (string) $score;
        }

        return $this->configure(ToggleButtons::make($field->key)->options($options)->inline()->grouped(), $field);
    }

    public function tableColumn(Field $field): ?Column
    {
        if (! static::available()) {
            return null;
        }

        return RatingColumn::make('data.'.$field->key)
            ->state(fn (FormSubmission $record): mixed => $record->value($field->key))
            ->stars($this->max($field))
            ->sortable(query: fn (EloquentBuilder $query, string $direction): EloquentBuilder => $query->orderBy($this->score($query->getQuery(), $field), $direction))
            ->summarize([
                RatingAverage::make('average')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull($this->score($query, $field)))
                    ->using(fn (Builder $query): ?float => ($average = $query->avg($this->score($query, $field))) === null ? null : (float) $average),
                RatingDistribution::make('distribution')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull($this->score($query, $field)))
                    ->using(fn (Builder $query): array => $this->distribution($query, $field)),
            ]);
    }

    public function tableFilter(Field $field): ?BaseFilter
    {
        if (! static::available()) {
            return null;
        }

        return RatingFilter::make('field_'.$field->key)
            ->label($field->label)
            ->stars($this->max($field))
            ->query(fn (EloquentBuilder $query, array $data): EloquentBuilder => is_numeric($data['value'] ?? null)
                ? $query->where($this->score($query->getQuery(), $field), '>=', (int) $data['value'])
                : $query);
    }

    public function detailEntry(Field $field): ?Entry
    {
        if (! static::available()) {
            return null;
        }

        return RatingEntry::make('data.'.$field->key)
            ->stars($this->max($field))
            ->showValue()
            ->placeholder('—');
    }

    /**
     * The score in the submission's JSON as a number on any database: the
     * JSON path alone is text on some, which neither averages, compares nor
     * sorts as a number (10 before 2), and MySQL reads a JSON null as the text "null". The
     * summarizers query it themselves, so Filament does not select the
     * column's name ("data.score") as a database column.
     */
    protected function score(Builder $query, Field $field): Expression
    {
        return DB::raw("cast(nullif({$query->getGrammar()->wrap('data->'.$field->key)}, 'null') as decimal)");
    }

    /**
     * How many submissions gave each score, keyed by score.
     *
     * @return array<int, int>
     */
    protected function distribution(Builder $query, Field $field): array
    {
        $score = $this->score($query, $field);

        return $query->clone()
            ->select(DB::raw($score->getValue($query->getGrammar()).' as score'))
            ->selectRaw('count(*) as aggregate')
            ->groupBy($score)
            ->get()
            ->mapWithKeys(fn (object $row): array => [(int) $row->score => (int) $row->aggregate])
            ->all();
    }
}

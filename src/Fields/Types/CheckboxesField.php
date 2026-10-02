<?php

namespace Packstub\FormBuilder\Fields\Types;

use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Component;
use Filament\Support\Enums\GridDirection;
use Packstub\FormBuilder\Fields\Concerns\HasChoices;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\FieldType;

class CheckboxesField extends FieldType
{
    use HasChoices;

    public static function id(): string
    {
        return 'checkboxes';
    }

    public function icon(): string
    {
        return 'heroicon-o-list-bullet';
    }

    public function hasChoiceColumns(): bool
    {
        return true;
    }

    public function hasPlaceholder(): bool
    {
        return false;
    }

    public function hasDefault(): bool
    {
        return false;
    }

    public function acceptsMultiple(): bool
    {
        return true;
    }

    public function rules(Field $field): array
    {
        return ['array'];
    }

    public function elementRules(Field $field): array
    {
        return $this->choiceRules($field);
    }

    public function comparableValue(mixed $value, Field $field): mixed
    {
        return $value === null || $value === '' ? [] : array_values((array) $value);
    }

    public function normalize(mixed $value, Field $field): mixed
    {
        if ($value === null || $value === '') {
            return [];
        }

        $choices = $field->choices();

        return array_values(array_filter(
            array_map(fn ($item): string => (string) $item, (array) $value),
            fn (string $item): bool => $choices === [] || array_key_exists($item, $choices),
        ));
    }

    public function formComponent(Field $field): Component
    {
        $list = CheckboxList::make($field->key)->options($field->choices());

        if (($columns = $this->choiceColumns($field)) > 1) {
            $list->columns(['default' => 1, 'sm' => $columns])->gridDirection(GridDirection::Row);
        }

        return $this->configure($list, $field);
    }
}

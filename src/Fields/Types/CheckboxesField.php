<?php

namespace Packstub\FormBuilder\Fields\Types;

use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Component;
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
        return $this->configure(CheckboxList::make($field->key)->options($field->choices()), $field);
    }
}

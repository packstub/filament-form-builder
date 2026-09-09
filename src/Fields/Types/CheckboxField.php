<?php

namespace Packstub\FormBuilder\Fields\Types;

use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Components\Component;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\FieldType;

/**
 * A single yes / no checkbox ("I agree to the terms").
 */
class CheckboxField extends FieldType
{
    public static function id(): string
    {
        return 'checkbox';
    }

    public function icon(): string
    {
        return 'heroicon-o-check';
    }

    public function rules(Field $field): array
    {
        return $field->required ? ['accepted'] : [];
    }

    public function normalize(mixed $value, Field $field): mixed
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function formComponent(Field $field): Component
    {
        $checkbox = Checkbox::make($field->key);

        if ($field->required) {
            $checkbox->accepted();
        }

        return $this->configure($checkbox, $field);
    }
}

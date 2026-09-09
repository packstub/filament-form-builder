<?php

namespace Packstub\FormBuilder\Fields\Types;

use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Packstub\FormBuilder\Fields\Concerns\HasChoices;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\FieldType;

class SelectField extends FieldType
{
    use HasChoices;

    public static function id(): string
    {
        return 'select';
    }

    public function icon(): string
    {
        return 'heroicon-o-chevron-up-down';
    }

    public function rules(Field $field): array
    {
        return $this->choiceRules($field);
    }

    public function formComponent(Field $field): Component
    {
        return $this->configure(Select::make($field->key)->options($field->choices())->native(), $field);
    }
}

<?php

namespace Packstub\FormBuilder\Fields\Types;

use Filament\Forms\Components\Radio;
use Filament\Schemas\Components\Component;
use Packstub\FormBuilder\Fields\Concerns\HasChoices;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\FieldType;

class RadioField extends FieldType
{
    use HasChoices;

    public static function id(): string
    {
        return 'radio';
    }

    public function icon(): string
    {
        return 'heroicon-o-check-circle';
    }

    public function rules(Field $field): array
    {
        return $this->choiceRules($field);
    }

    public function formComponent(Field $field): Component
    {
        return $this->configure(Radio::make($field->key)->options($field->choices()), $field);
    }
}

<?php

namespace Packstub\FormBuilder\Fields\Types;

use Filament\Forms\Components\Radio;
use Filament\Schemas\Components\Component;
use Filament\Support\Enums\GridDirection;
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

    public function hasChoiceColumns(): bool
    {
        return true;
    }

    public function hasPlaceholder(): bool
    {
        return false;
    }

    public function rules(Field $field): array
    {
        return $this->choiceRules($field);
    }

    public function formComponent(Field $field): Component
    {
        $radio = Radio::make($field->key)->options($field->choices());

        if (($columns = $this->choiceColumns($field)) > 1) {
            $radio->columns(['default' => 1, 'sm' => $columns])->gridDirection(GridDirection::Row);
        }

        return $this->configure($radio, $field);
    }
}

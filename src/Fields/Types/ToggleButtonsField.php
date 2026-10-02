<?php

namespace Packstub\FormBuilder\Fields\Types;

use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Packstub\FormBuilder\Fields\Field;

/**
 * A single choice shown as a row of buttons.
 */
class ToggleButtonsField extends RadioField
{
    public static function id(): string
    {
        return 'toggle_buttons';
    }

    public function icon(): string
    {
        return 'heroicon-o-squares-2x2';
    }

    public function hasChoiceColumns(): bool
    {
        return false;
    }

    public function view(): string
    {
        return 'packstub-form-builder::fields.toggle-buttons';
    }

    public function formComponent(Field $field): Component
    {
        return $this->configure(ToggleButtons::make($field->key)->options($field->choices())->inline()->grouped(), $field);
    }
}

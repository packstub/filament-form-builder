<?php

namespace Packstub\FormBuilder\Fields\Types;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\FieldType;

/**
 * Base for the single-line <input> types.
 */
abstract class InputField extends FieldType
{
    public function inputType(): string
    {
        return 'text';
    }

    public function view(): string
    {
        return 'packstub-form-builder::fields.input';
    }

    public function editorSchema(): array
    {
        return [
            TextInput::make('min_length')
                ->label(__('packstub-form-builder::form-builder.editor.min_length'))
                ->integer()
                ->minValue(0),
            TextInput::make('max_length')
                ->label(__('packstub-form-builder::form-builder.editor.max_length'))
                ->integer()
                ->minValue(1),
        ];
    }

    public function rules(Field $field): array
    {
        $rules = ['string'];

        if (($min = $field->option('min_length')) !== null && $min !== '') {
            $rules[] = 'min:'.(int) $min;
        }

        $rules[] = 'max:'.((($max = $field->option('max_length')) !== null && $max !== '') ? (int) $max : 255);

        return $rules;
    }

    public function formComponent(Field $field): Component
    {
        $input = TextInput::make($field->key);

        if (($min = $field->option('min_length')) !== null && $min !== '') {
            $input->minLength((int) $min);
        }

        if (($max = $field->option('max_length')) !== null && $max !== '') {
            $input->maxLength((int) $max);
        }

        return $this->configure($input, $field);
    }
}

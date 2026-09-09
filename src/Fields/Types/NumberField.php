<?php

namespace Packstub\FormBuilder\Fields\Types;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Packstub\FormBuilder\Fields\Field;

class NumberField extends InputField
{
    public static function id(): string
    {
        return 'number';
    }

    public function icon(): string
    {
        return 'heroicon-o-hashtag';
    }

    public function inputType(): string
    {
        return 'number';
    }

    public function editorSchema(): array
    {
        return [
            TextInput::make('min')
                ->label(__('packstub-form-builder::form-builder.editor.min_value'))
                ->numeric(),
            TextInput::make('max')
                ->label(__('packstub-form-builder::form-builder.editor.max_value'))
                ->numeric(),
            TextInput::make('step')
                ->label(__('packstub-form-builder::form-builder.editor.step'))
                ->numeric()
                ->placeholder('1'),
        ];
    }

    public function rules(Field $field): array
    {
        $rules = ['numeric'];

        if (($min = $field->option('min')) !== null && $min !== '') {
            $rules[] = 'min:'.$min;
        }

        if (($max = $field->option('max')) !== null && $max !== '') {
            $rules[] = 'max:'.$max;
        }

        return $rules;
    }

    public function normalize(mixed $value, Field $field): mixed
    {
        $value = parent::normalize($value, $field);

        if ($value === null || ! is_numeric($value)) {
            return $value;
        }

        return str_contains((string) $value, '.') ? (float) $value : (int) $value;
    }

    public function formComponent(Field $field): Component
    {
        $input = TextInput::make($field->key)->numeric();

        if (($min = $field->option('min')) !== null && $min !== '') {
            $input->minValue($min);
        }

        if (($max = $field->option('max')) !== null && $max !== '') {
            $input->maxValue($max);
        }

        if (($step = $field->option('step')) !== null && $step !== '') {
            $input->step($step);
        }

        return $this->configure($input, $field);
    }
}

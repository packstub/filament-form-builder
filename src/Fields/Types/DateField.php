<?php

namespace Packstub\FormBuilder\Fields\Types;

use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Component;
use Packstub\FormBuilder\Fields\Field;

class DateField extends InputField
{
    public static function id(): string
    {
        return 'date';
    }

    public function icon(): string
    {
        return 'heroicon-o-calendar';
    }

    public function inputType(): string
    {
        return 'date';
    }

    public function editorSchema(): array
    {
        return [
            DatePicker::make('min')
                ->label(__('packstub-form-builder::form-builder.editor.min_date'))
                ->native(),
            DatePicker::make('max')
                ->label(__('packstub-form-builder::form-builder.editor.max_date'))
                ->native(),
        ];
    }

    public function rules(Field $field): array
    {
        $rules = ['date'];

        if (filled($min = $field->option('min'))) {
            $rules[] = 'after_or_equal:'.$min;
        }

        if (filled($max = $field->option('max'))) {
            $rules[] = 'before_or_equal:'.$max;
        }

        return $rules;
    }

    public function formComponent(Field $field): Component
    {
        $input = DatePicker::make($field->key)->native();

        if (filled($min = $field->option('min'))) {
            $input->minDate($min);
        }

        if (filled($max = $field->option('max'))) {
            $input->maxDate($max);
        }

        return $this->configure($input, $field);
    }
}

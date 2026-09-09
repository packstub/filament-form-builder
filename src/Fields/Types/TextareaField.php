<?php

namespace Packstub\FormBuilder\Fields\Types;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\FieldType;

class TextareaField extends FieldType
{
    public static function id(): string
    {
        return 'textarea';
    }

    public function icon(): string
    {
        return 'heroicon-o-bars-3-bottom-left';
    }

    public function editorSchema(): array
    {
        return [
            TextInput::make('rows')
                ->label(__('packstub-form-builder::form-builder.editor.rows'))
                ->integer()
                ->minValue(1)
                ->default(4),
            TextInput::make('max_length')
                ->label(__('packstub-form-builder::form-builder.editor.max_length'))
                ->integer()
                ->minValue(1),
        ];
    }

    public function rules(Field $field): array
    {
        return ['string', 'max:'.(filled($max = $field->option('max_length')) ? (int) $max : 5000)];
    }

    public function formComponent(Field $field): Component
    {
        $input = Textarea::make($field->key)->rows((int) ($field->option('rows') ?: 4));

        if (filled($max = $field->option('max_length'))) {
            $input->maxLength((int) $max);
        }

        return $this->configure($input, $field);
    }
}

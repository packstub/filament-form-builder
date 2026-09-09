<?php

namespace Packstub\FormBuilder\Fields\Types;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\FieldType;

/**
 * A value the visitor never sees (a campaign id, a page name...).
 */
class HiddenField extends FieldType
{
    public static function id(): string
    {
        return 'hidden';
    }

    public function icon(): string
    {
        return 'heroicon-o-eye-slash';
    }

    public function hasCommonSettings(): bool
    {
        return false;
    }

    public function editorSchema(): array
    {
        return [
            TextInput::make('default')
                ->label(__('packstub-form-builder::form-builder.editor.value'))
                ->maxLength(255),
        ];
    }

    public function rules(Field $field): array
    {
        return ['string', 'max:255'];
    }

    public function formComponent(Field $field): Component
    {
        return Hidden::make($field->key)->default($field->default);
    }
}

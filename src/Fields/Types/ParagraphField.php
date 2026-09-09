<?php

namespace Packstub\FormBuilder\Fields\Types;

use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Text;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\FieldType;

class ParagraphField extends FieldType
{
    public static function id(): string
    {
        return 'paragraph';
    }

    public function icon(): string
    {
        return 'heroicon-o-document-text';
    }

    public function isInput(): bool
    {
        return false;
    }

    public function editorSchema(): array
    {
        return [
            Textarea::make('text')
                ->label(__('packstub-form-builder::form-builder.editor.text'))
                ->rows(3)
                ->required()
                ->columnSpanFull(),
        ];
    }

    public function formComponent(Field $field): Component
    {
        return Text::make((string) $field->option('text', ''))->columnSpanFull();
    }
}

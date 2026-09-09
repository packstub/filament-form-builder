<?php

namespace Packstub\FormBuilder\Fields\Types;

use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Text;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\FieldType;

class HeadingField extends FieldType
{
    public static function id(): string
    {
        return 'heading';
    }

    public function icon(): string
    {
        return 'heroicon-o-h2';
    }

    public function isInput(): bool
    {
        return false;
    }

    public function editorSchema(): array
    {
        return [
            Select::make('level')
                ->label(__('packstub-form-builder::form-builder.editor.heading_level'))
                ->options(['h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4'])
                ->default('h3')
                ->native(false),
        ];
    }

    public function formComponent(Field $field): Component
    {
        return Text::make($field->label)
            ->size($field->option('level') === 'h2' ? TextSize::Large : TextSize::Medium)
            ->weight(FontWeight::SemiBold)
            ->columnSpanFull();
    }
}

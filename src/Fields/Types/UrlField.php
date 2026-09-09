<?php

namespace Packstub\FormBuilder\Fields\Types;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Packstub\FormBuilder\Fields\Field;

class UrlField extends InputField
{
    public static function id(): string
    {
        return 'url';
    }

    public function icon(): string
    {
        return 'heroicon-o-link';
    }

    public function inputType(): string
    {
        return 'url';
    }

    public function editorSchema(): array
    {
        return [];
    }

    public function rules(Field $field): array
    {
        return ['string', 'max:2048', 'url'];
    }

    public function formComponent(Field $field): Component
    {
        /** @var TextInput $input */
        $input = parent::formComponent($field);

        return $input->url()->maxLength(2048);
    }
}

<?php

namespace Packstub\FormBuilder\Fields\Types;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Packstub\FormBuilder\Fields\Field;

class PhoneField extends InputField
{
    public static function id(): string
    {
        return 'phone';
    }

    public function icon(): string
    {
        return 'heroicon-o-phone';
    }

    public function inputType(): string
    {
        return 'tel';
    }

    public function editorSchema(): array
    {
        return [];
    }

    public function rules(Field $field): array
    {
        return ['string', 'max:50'];
    }

    public function formComponent(Field $field): Component
    {
        /** @var TextInput $input */
        $input = parent::formComponent($field);

        return $input->tel()->maxLength(50);
    }
}

<?php

namespace Packstub\FormBuilder\Fields\Types;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Packstub\FormBuilder\Fields\Field;

class EmailField extends InputField
{
    public static function id(): string
    {
        return 'email';
    }

    public function icon(): string
    {
        return 'heroicon-o-envelope';
    }

    public function inputType(): string
    {
        return 'email';
    }

    public function rules(Field $field): array
    {
        return [...parent::rules($field), 'email'];
    }

    public function normalize(mixed $value, Field $field): mixed
    {
        $value = parent::normalize($value, $field);

        return is_string($value) ? mb_strtolower($value) : $value;
    }

    public function formComponent(Field $field): Component
    {
        /** @var TextInput $input */
        $input = parent::formComponent($field);

        return $input->email();
    }
}

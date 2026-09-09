<?php

namespace Packstub\FormBuilder\Fields\Types;

class TextField extends InputField
{
    public static function id(): string
    {
        return 'text';
    }

    public function icon(): string
    {
        return 'heroicon-o-minus';
    }
}

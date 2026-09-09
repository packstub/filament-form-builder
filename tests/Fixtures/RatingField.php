<?php

namespace Packstub\FormBuilder\Tests\Fixtures;

use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\Types\NumberField;

class RatingField extends NumberField
{
    public static function id(): string
    {
        return 'rating';
    }

    public function rules(Field $field): array
    {
        return ['integer', 'between:1,5'];
    }
}

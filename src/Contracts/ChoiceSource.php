<?php

namespace Packstub\FormBuilder\Contracts;

use Packstub\FormBuilder\Fields\Field;

/**
 * The choices of a select, radio, checkbox list or multi-select taken from
 * your own data (products, locations, courses) instead of the list typed in
 * the builder. Register it with FormBuilder::choices() or config
 * "choice_sources".
 */
interface ChoiceSource
{
    /**
     * The name shown in the builder's "Options" select.
     */
    public function label(): string;

    /**
     * value => label. Called when the form renders and again on submit, so
     * validation uses the live list.
     *
     * @return iterable<int|string, string>
     */
    public function choices(Field $field): iterable;
}

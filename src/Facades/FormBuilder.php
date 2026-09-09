<?php

namespace Packstub\FormBuilder\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Packstub\FormBuilder\Fields\FieldTypeRegistry fieldTypes()
 * @method static \Packstub\FormBuilder\FormBuilder registerFieldTypes(array $types)
 * @method static \Packstub\FormBuilder\FormBuilder sink(array|string|\Packstub\FormBuilder\Contracts\SubmissionSink $sinks)
 * @method static array sinks()
 * @method static \Packstub\FormBuilder\FormBuilder forgetSinks()
 * @method static ?\Packstub\FormBuilder\Models\Form find(\Packstub\FormBuilder\Models\Form|string|int $form)
 * @method static \Packstub\FormBuilder\Submissions\SubmissionResult submit(\Packstub\FormBuilder\Models\Form|string|int $form, array $data, ?\Packstub\FormBuilder\Submissions\SubmissionContext $context = null)
 * @method static string formModel()
 * @method static string submissionModel()
 *
 * @see \Packstub\FormBuilder\FormBuilder
 */
class FormBuilder extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Packstub\FormBuilder\FormBuilder::class;
    }
}

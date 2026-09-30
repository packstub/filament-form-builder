<?php

namespace Packstub\FormBuilder\Facades;

use Illuminate\Support\Facades\Facade;
use Packstub\FormBuilder\Fields\FieldTypeRegistry;
use Packstub\FormBuilder\Testing\FormBuilderFake;

/**
 * @method static \Packstub\FormBuilder\Fields\FieldTypeRegistry fieldTypes()
 * @method static \Packstub\FormBuilder\FormBuilder registerFieldTypes(array $types)
 * @method static \Packstub\FormBuilder\FormBuilder choices(string $name, \Closure|\Packstub\FormBuilder\Contracts\ChoiceSource|array|string $source, ?string $label = null)
 * @method static \Packstub\FormBuilder\Fields\ChoiceSources choiceSources()
 * @method static \Packstub\FormBuilder\FormBuilder sink(array|string|\Packstub\FormBuilder\Contracts\SubmissionSink $sinks)
 * @method static array sinks()
 * @method static \Packstub\FormBuilder\FormBuilder forgetSinks()
 * @method static ?\Packstub\FormBuilder\Models\Form find(\Packstub\FormBuilder\Models\Form|string|int $form)
 * @method static \Packstub\FormBuilder\Submissions\SubmissionResult submit(\Packstub\FormBuilder\Models\Form|string|int $form, array $data, ?\Packstub\FormBuilder\Submissions\SubmissionContext $context = null)
 * @method static string formModel()
 * @method static string submissionModel()
 * @method static array validInput(\Packstub\FormBuilder\Models\Form|string|int $form, array $values = [])
 * @method static bool faking()
 * @method static \Illuminate\Support\Collection submitted(\Packstub\FormBuilder\Models\Form|string|int|null $form = null, ?callable $callback = null)
 * @method static \Packstub\FormBuilder\Testing\FormBuilderFake assertSubmitted(\Packstub\FormBuilder\Models\Form|string|int $form, ?callable $callback = null)
 * @method static \Packstub\FormBuilder\Testing\FormBuilderFake assertNotSubmitted(\Packstub\FormBuilder\Models\Form|string|int $form, ?callable $callback = null)
 * @method static \Packstub\FormBuilder\Testing\FormBuilderFake assertSubmittedCount(\Packstub\FormBuilder\Models\Form|string|int $form, int $count)
 * @method static \Packstub\FormBuilder\Testing\FormBuilderFake assertNothingSubmitted()
 * @method static \Packstub\FormBuilder\Testing\FormBuilderFake assertSpamDetected(\Packstub\FormBuilder\Models\Form|string|int $form, ?string $reason = null)
 *
 * @see \Packstub\FormBuilder\FormBuilder
 */
class FormBuilder extends Facade
{
    /**
     * Record submissions instead of sending emails, notifications, webhooks,
     * channel messages and sink calls; assert on them afterwards.
     */
    public static function fake(): FormBuilderFake
    {
        $fake = new FormBuilderFake(app(FieldTypeRegistry::class));

        static::swap($fake);

        return $fake->listen();
    }

    protected static function getFacadeAccessor(): string
    {
        return \Packstub\FormBuilder\FormBuilder::class;
    }
}

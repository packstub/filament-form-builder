<?php

namespace Packstub\FormBuilder;

use Packstub\FormBuilder\Contracts\SubmissionSink;
use Packstub\FormBuilder\Fields\FieldType;
use Packstub\FormBuilder\Fields\FieldTypeRegistry;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;
use Packstub\FormBuilder\Submissions\SubmissionContext;
use Packstub\FormBuilder\Submissions\SubmissionResult;
use Packstub\FormBuilder\Submissions\Submitter;

class FormBuilder
{
    /** @var array<int, class-string<SubmissionSink>|SubmissionSink> */
    protected array $sinks = [];

    public function __construct(protected FieldTypeRegistry $types) {}

    /** @return class-string<Form> */
    public static function formModel(): string
    {
        return config('packstub-form-builder.models.form', Form::class);
    }

    /** @return class-string<FormSubmission> */
    public static function submissionModel(): string
    {
        return config('packstub-form-builder.models.submission', FormSubmission::class);
    }

    public function fieldTypes(): FieldTypeRegistry
    {
        return $this->types;
    }

    /**
     * @param  array<int, class-string<FieldType>|FieldType>  $types
     */
    public function registerFieldTypes(array $types): static
    {
        $this->types->register($types);

        return $this;
    }

    /**
     * @param  array<int, class-string<SubmissionSink>|SubmissionSink>  $sinks
     */
    public function sink(array|string|SubmissionSink $sinks): static
    {
        foreach (is_array($sinks) ? $sinks : [$sinks] as $sink) {
            $this->sinks[] = $sink;
        }

        return $this;
    }

    /**
     * @return array<int, SubmissionSink>
     */
    public function sinks(): array
    {
        return array_map(
            fn (string|SubmissionSink $sink): SubmissionSink => $sink instanceof SubmissionSink ? $sink : app($sink),
            [...(array) config('packstub-form-builder.sinks', []), ...$this->sinks],
        );
    }

    public function forgetSinks(): static
    {
        $this->sinks = [];

        return $this;
    }

    /**
     * Find a form by slug or id.
     */
    public function find(Form|string|int $form): ?Form
    {
        if ($form instanceof Form) {
            return $form;
        }

        $model = static::formModel();

        return is_int($form) || ctype_digit((string) $form)
            ? $model::query()->find($form)
            : $model::query()->where('slug', $form)->first();
    }

    /**
     * Submit from code, bypassing the spam checks that need a rendered form.
     *
     * @param  array<string, mixed>  $data
     */
    public function submit(Form|string|int $form, array $data, ?SubmissionContext $context = null): SubmissionResult
    {
        $form = $this->find($form) ?? throw new \InvalidArgumentException('Unknown form.');
        $context ??= new SubmissionContext(channel: 'code');

        return app(Submitter::class)->submit($form, $data, $context->trusted());
    }
}

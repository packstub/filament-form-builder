<?php

namespace Packstub\FormBuilder\Submissions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Packstub\FormBuilder\Events\SpamDetected;
use Packstub\FormBuilder\Events\SubmissionReceived;
use Packstub\FormBuilder\Exceptions\FormClosedException;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\FormBuilder;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;

/**
 * The one path every submission takes, whatever the renderer: availability,
 * spam checks, validation from the field definitions, storage, events.
 */
class Submitter
{
    public function __construct(
        protected SpamGuard $spam,
        protected Dispatcher $events,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws FormClosedException
     * @throws ValidationException
     */
    public function submit(Form $form, array $input, ?SubmissionContext $context = null): SubmissionResult
    {
        $context ??= new SubmissionContext;

        if (($reason = $form->closedReason()) !== null) {
            throw new FormClosedException($reason);
        }

        if ($form->requiresLogin() && $context->userId === null) {
            throw new FormClosedException(__('packstub-form-builder::form-builder.frontend.login_required'));
        }

        if (! $context->trusted && ($reason = $this->spam->reason($form, $input, $context)) !== null) {
            $this->events->dispatch(new SpamDetected($form, $reason, $input, $context));

            // Bots get the success state; nothing is stored.
            return new SubmissionResult($form, null, spam: true);
        }

        $data = $this->validate($form, $input);
        $submission = $this->build($form, $data, $context);

        if ($form->store_submissions) {
            DB::transaction(fn () => $submission->save());
        }

        $this->events->dispatch(new SubmissionReceived($form, $submission, $context));

        return new SubmissionResult($form, $submission);
    }

    /**
     * Validate and normalise the input against the form's fields.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(Form $form, array $input): array
    {
        $input = array_diff_key($input, array_flip($this->spam->reservedKeys()));

        $validated = Validator::make($input, $form->validationRules(), [], $form->validationAttributes())->validate();

        $data = [];

        foreach ($form->inputFields() as $field) {
            $value = $validated[$field->key] ?? null;

            // Hidden fields fall back to their configured value.
            if ($value === null && $field->type::id() === 'hidden') {
                $value = $field->default;
            }

            $data[$field->key] = $field->type->normalize($value, $field);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function build(Form $form, array $data, SubmissionContext $context): FormSubmission
    {
        $model = FormBuilder::submissionModel();

        /** @var FormSubmission $submission */
        $submission = new $model([
            'form_id' => $form->getKey(),
            'data' => $data,
            'fields' => $form->inputFields()->map(fn (Field $field): array => [
                'label' => $field->label,
                'type' => $field->type::id(),
            ])->all(),
            'user_id' => $context->userId,
            'ip' => config('packstub-form-builder.submissions.store_ip', true) ? $context->ip : null,
            'user_agent' => config('packstub-form-builder.submissions.store_user_agent', true)
                ? ($context->userAgent === null ? null : mb_substr($context->userAgent, 0, 512))
                : null,
            'source_url' => $context->sourceUrl,
            'channel' => $context->channel,
            'meta' => $context->meta === [] ? null : $context->meta,
        ]);

        $submission->setRelation('form', $form);

        return $submission;
    }
}

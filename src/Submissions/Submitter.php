<?php

namespace Packstub\FormBuilder\Submissions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\UploadedFile;
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
use Packstub\FormBuilder\Models\ShareLink;

/**
 * The one path every submission takes, whatever the renderer: availability,
 * spam checks, validation from the field definitions, storage, events.
 */
class Submitter
{
    public function __construct(
        protected SpamGuard $spam,
        protected ProtectionToken $tokens,
        protected Captcha $captcha,
        protected PasswordGate $passwords,
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

        $this->assertOpen($form, $context);
        $link = $this->shareLink($form, $input[ShareLink::FIELD] ?? null);

        if (! $context->trusted && ! $this->passwords->keyIsValid($form, $input[PasswordGate::FIELD] ?? null)) {
            throw new FormClosedException(__('packstub-form-builder::form-builder.frontend.password_required'));
        }

        if (! $context->trusted && ($reason = $this->spam->reason($form, $input, $context)) !== null) {
            $this->events->dispatch(new SpamDetected($form, $reason, $input, $context));

            // Bots get the success state; nothing is stored.
            return new SubmissionResult($form, null, spam: true);
        }

        if (! $context->trusted && ($error = $this->captcha->verify($form, $input, $context->ip)) !== null) {
            throw ValidationException::withMessages(['captcha' => [$error]]);
        }

        $data = $this->validate($form, $input);
        $submission = $this->build($form, $data, $context);
        $submission->share_link_id = $link?->getKey();

        if ($form->store_submissions) {
            DB::transaction(function () use ($form, $submission): void {
                $submission->number = $this->nextNumber($form);
                $submission->save();
            });
        }

        $this->tokens->markUsed($form, $input[$this->tokens->field()] ?? null);

        $this->events->dispatch(new SubmissionReceived($form, $submission, $context));

        return new SubmissionResult($form, $submission);
    }

    /**
     * Availability, login and the per-person limit.
     *
     * @throws FormClosedException
     */
    public function assertOpen(Form $form, SubmissionContext $context): void
    {
        if (($reason = $form->closedReason()) !== null) {
            throw new FormClosedException($reason);
        }

        if ($form->requiresLogin() && $context->userId === null) {
            throw new FormClosedException(__('packstub-form-builder::form-builder.frontend.login_required'));
        }

        if ($form->onePerPerson() && $this->hasSubmitted($form, $context)) {
            throw new FormClosedException((string) $form->setting('already_submitted_message', __('packstub-form-builder::form-builder.frontend.already_submitted')));
        }
    }

    /**
     * The share link a submission came through, when it carries one; a
     * revoked, expired or full link closes the form for it.
     *
     * @throws FormClosedException
     */
    public function shareLink(Form $form, mixed $token): ?ShareLink
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        $link = $form->exists ? $form->shareLinks()->where('token', $token)->first() : null;

        if ($link === null) {
            throw new FormClosedException(__('packstub-form-builder::form-builder.frontend.link_invalid'));
        }

        if (($reason = $link->closedReason()) !== null) {
            throw new FormClosedException($reason);
        }

        return $link;
    }

    /**
     * Whether the visitor behind the context already submitted the form.
     */
    public function hasSubmitted(Form $form, SubmissionContext $context): bool
    {
        if (! $form->exists) {
            return false;
        }

        $fingerprint = $context->fingerprint();

        if ($fingerprint === null) {
            return false;
        }

        $query = $form->submissions();

        return $context->userId !== null
            ? $query->where('user_id', $context->userId)->exists()
            : $query->where('fingerprint', $fingerprint)->exists();
    }

    /**
     * Validate and normalise the input against the form's fields, with the
     * conditions applied: hidden fields are skipped (stored as null) and a
     * conditional requirement is resolved against the input.
     *
     * @param  array<string, mixed>  $input
     * @param  array<int, string>|null  $only  Validate these keys only (a step of a multi-step form).
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(Form $form, array $input, ?array $only = null): array
    {
        $input = array_diff_key($input, array_flip($this->spam->reservedKeys()));

        // Types that store files need the form; let them shape the input first.
        app()->instance('packstub-form-builder.current-form', $form);

        foreach ($form->inputFields() as $field) {
            if (array_key_exists($field->key, $input)) {
                $input[$field->key] = $field->type->prepare($input[$field->key], $field);
            }
        }

        $values = $this->comparable($form, $input);
        $visible = $form->visibleKeys($values);

        $validated = Validator::make(
            $input,
            $form->validationRules($values, $only),
            $form->validationMessages(),
            $form->validationAttributes(),
        )->validate();

        $data = [];

        foreach ($form->inputFields() as $field) {
            if ($only !== null && ! in_array($field->key, $only, true)) {
                continue;
            }

            if (! in_array($field->key, $visible, true)) {
                $data[$field->key] = null;

                continue;
            }

            $value = $validated[$field->key] ?? null;

            // Hidden inputs fall back to their configured value.
            if ($value === null && $field->type::id() === 'hidden') {
                $value = $field->default;
            }

            $data[$field->key] = $field->type->normalize($value, $field);
        }

        return $data;
    }

    /**
     * The input as the conditions see it: every input field's raw value,
     * loosely normalised (checkboxes to booleans, lists to arrays).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function comparable(Form $form, array $input): array
    {
        $values = [];

        foreach ($form->inputFields() as $field) {
            $value = $input[$field->key] ?? null;

            if ($field->type::id() === 'hidden' && ($value === null || $value === '')) {
                $value = $field->default;
            }

            $values[$field->key] = $field->type->isComparable() && ! ($value instanceof UploadedFile)
                ? $field->type->comparableValue($value, $field)
                : $value;
        }

        return $values;
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
            'fingerprint' => $context->fingerprint(),
            'source_url' => $context->sourceUrl,
            'channel' => $context->channel,
            'meta' => $context->meta === [] ? null : $context->meta,
        ]);

        $submission->setRelation('form', $form);

        return $submission;
    }

    /**
     * The next sequential number of the form's submissions, under a lock on
     * the form row.
     */
    protected function nextNumber(Form $form): int
    {
        $locked = $form->newQueryWithoutScopes()->whereKey($form->getKey())->lockForUpdate()->first();

        $max = (int) ($locked ?? $form)->submissions()->max('number');

        return $max + 1;
    }
}

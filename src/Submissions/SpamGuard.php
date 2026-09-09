<?php

namespace Packstub\FormBuilder\Submissions;

use Packstub\FormBuilder\Models\Form;

class SpamGuard
{
    public function __construct(protected ProtectionToken $tokens) {}

    /**
     * Why the submission looks like spam ("honeypot", "too_fast", "no_token"), or null.
     *
     * @param  array<string, mixed>  $input
     */
    public function reason(Form $form, array $input, SubmissionContext $context): ?string
    {
        if ($form->usesHoneypot() && filled($input[$this->honeypotField()] ?? null)) {
            return 'honeypot';
        }

        $minSeconds = $form->minSeconds();

        if ($minSeconds > 0) {
            $age = $this->tokens->age($form, $input[$this->tokens->field()] ?? null);

            if ($age === null) {
                return 'no_token';
            }

            if ($age < $minSeconds) {
                return 'too_fast';
            }
        }

        return null;
    }

    public function honeypotField(): string
    {
        return (string) config('packstub-form-builder.spam.honeypot_field', '_fb_website');
    }

    /**
     * The input keys the guard owns, to strip from the submitted data.
     *
     * @return array<int, string>
     */
    public function reservedKeys(): array
    {
        return [$this->honeypotField(), $this->tokens->field(), '_fb_return', '_token', '_method'];
    }
}

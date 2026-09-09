<?php

namespace Packstub\FormBuilder\Submissions;

use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;

final class SubmissionResult
{
    public function __construct(
        public readonly Form $form,
        public readonly ?FormSubmission $submission,
        public readonly bool $spam = false,
    ) {}

    public function message(): string
    {
        return $this->form->successMessage();
    }

    public function redirectUrl(): ?string
    {
        return $this->form->redirect_url ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ok' => true,
            'message' => $this->message(),
            'redirect' => $this->redirectUrl(),
            'id' => $this->submission?->getKey(),
        ];
    }
}

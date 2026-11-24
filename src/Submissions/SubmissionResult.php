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

    /**
     * Where to send the visitor after a submission: the form's redirect URL
     * when it is an http(s) URL or a path on this site, else none (a
     * "javascript:" URL would run as the visitor on the page).
     */
    public function redirectUrl(): ?string
    {
        $url = $this->form->redirect_url;

        return is_string($url) && self::isSafeRedirect($url) ? $url : null;
    }

    public static function isSafeRedirect(string $url): bool
    {
        $url = trim($url);

        if ($url === '' || preg_match('~[\x00-\x1F\x7F\\\\]~', $url)) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//');
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) !== false;
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

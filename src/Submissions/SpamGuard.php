<?php

namespace Packstub\FormBuilder\Submissions;

use Packstub\FormBuilder\Models\Form;

class SpamGuard
{
    public function __construct(protected ProtectionToken $tokens) {}

    /**
     * Why the submission looks like spam ("honeypot", "too_fast", "no_token",
     * "reused_token", "origin", "blocked_word", "blocked_email",
     * "blocked_ip"), or null.
     *
     * @param  array<string, mixed>  $input
     */
    public function reason(Form $form, array $input, SubmissionContext $context): ?string
    {
        if ($form->usesHoneypot() && filled($input[$this->honeypotField()] ?? null)) {
            return 'honeypot';
        }

        $minSeconds = $form->minSeconds();
        $token = $input[$this->tokens->field()] ?? null;

        if ($minSeconds > 0) {
            $age = $this->tokens->age($form, $token);

            if ($age === null) {
                return 'no_token';
            }

            if ($age < $minSeconds) {
                return 'too_fast';
            }
        }

        if ($token !== null && $this->tokens->isUsed($form, $token)) {
            return 'reused_token';
        }

        if (! $this->originAllowed($context->origin)) {
            return 'origin';
        }

        return $this->blocklistReason($form, $input, $context);
    }

    /**
     * Whether the request's Origin (or Referer) is on the allowed list
     * (config "spam.allowed_origins"; empty = every origin).
     */
    public function originAllowed(?string $origin): bool
    {
        $allowed = array_values(array_filter((array) config('packstub-form-builder.spam.allowed_origins', [])));

        if ($allowed === [] || $origin === null || $origin === '') {
            return true;
        }

        $host = parse_url($origin, PHP_URL_HOST);

        if (! is_string($host)) {
            return true;
        }

        foreach ($allowed as $pattern) {
            $pattern = strtolower(trim((string) $pattern));
            $pattern = (string) (parse_url(str_contains($pattern, '://') ? $pattern : 'https://'.$pattern, PHP_URL_HOST) ?: $pattern);

            if ($pattern === '' || fnmatch($pattern, strtolower($host))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function blocklistReason(Form $form, array $input, SubmissionContext $context): ?string
    {
        $blocklist = (array) config('packstub-form-builder.spam.blocklist', []);
        $ips = array_filter((array) ($blocklist['ips'] ?? []));

        if ($context->ip !== null) {
            foreach ($ips as $ip) {
                if (fnmatch((string) $ip, $context->ip)) {
                    return 'blocked_ip';
                }
            }
        }

        $words = array_values(array_filter(array_map(fn ($word): string => strtolower(trim((string) $word)), (array) ($blocklist['words'] ?? []))));
        $domains = array_values(array_filter(array_map(fn ($domain): string => strtolower(ltrim(trim((string) $domain), '@')), (array) ($blocklist['email_domains'] ?? []))));

        foreach ($form->inputFields() as $field) {
            $value = $input[$field->key] ?? null;

            foreach (is_array($value) ? $value : [$value] as $item) {
                if (! is_scalar($item) || $item === '') {
                    continue;
                }

                $text = strtolower((string) $item);

                if ($domains !== [] && $field->type::id() === 'email' && str_contains($text, '@')) {
                    $domain = substr($text, strrpos($text, '@') + 1);

                    foreach ($domains as $blocked) {
                        if ($domain === $blocked || str_ends_with($domain, '.'.$blocked)) {
                            return 'blocked_email';
                        }
                    }
                }

                foreach ($words as $word) {
                    if (str_contains($text, $word)) {
                        return 'blocked_word';
                    }
                }
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
        return [
            $this->honeypotField(),
            $this->tokens->field(),
            '_fb_return', '_fb_key', '_fb_link', '_fb_step', '_fb_fields', '_token', '_method',
            'cf-turnstile-response', 'h-captcha-response', 'g-recaptcha-response', '_fb_captcha',
        ];
    }
}

<?php

namespace Packstub\FormBuilder\Http;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Packstub\FormBuilder\Models\Form;

/**
 * What a rendered form shows after a plain POST round-trip: the success
 * message, the validation errors and the old input. Read from the session
 * when there is one, else from the query string the controller redirected
 * to (fb_success / fb_state), so session-less pages work too.
 */
final class FormState
{
    /**
     * @param  array<string, array<int, string>>  $errors
     * @param  array<string, mixed>  $input
     */
    public function __construct(
        public readonly bool $success = false,
        public readonly array $errors = [],
        public readonly array $input = [],
        public readonly ?string $message = null,
    ) {}

    public static function successKey(Form $form): string
    {
        return 'packstub-form-builder.success.'.$form->getKey();
    }

    public static function errorBag(Form $form): string
    {
        return 'form_builder_'.$form->getKey();
    }

    public static function for(Form $form, ?Request $request = null): self
    {
        $request ??= request();

        if ($request->hasSession()) {
            $session = $request->session();

            if ($session->has(self::successKey($form))) {
                return new self(success: true, message: (string) $session->get(self::successKey($form)));
            }

            $bag = $session->get('errors');
            $errors = $bag?->hasBag(self::errorBag($form)) ? $bag->getBag(self::errorBag($form))->toArray() : [];

            if ($errors !== []) {
                return new self(errors: $errors, input: (array) $session->getOldInput());
            }
        }

        if ((string) $request->query('fb_success') === $form->slug) {
            return new self(success: true, message: $form->successMessage());
        }

        return self::fromQuery($form, $request->query('fb_state'));
    }

    /**
     * Encode errors and input for a session-less redirect.
     *
     * @param  array<string, array<int, string>>  $errors
     * @param  array<string, mixed>  $input
     */
    public static function encode(Form $form, array $errors, array $input): string
    {
        return Crypt::encryptString(json_encode([
            'f' => $form->slug,
            'e' => $errors,
            'i' => $input,
        ], JSON_THROW_ON_ERROR));
    }

    public static function fromQuery(Form $form, mixed $state): self
    {
        if (! is_string($state) || $state === '') {
            return new self;
        }

        try {
            $payload = json_decode(Crypt::decryptString($state), true, 16, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return new self;
        }

        if (! is_array($payload) || ($payload['f'] ?? null) !== $form->slug) {
            return new self;
        }

        return new self(
            errors: is_array($payload['e'] ?? null) ? $payload['e'] : [],
            input: is_array($payload['i'] ?? null) ? $payload['i'] : [],
        );
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function error(string $key): ?string
    {
        return $this->errors[$key][0] ?? null;
    }

    public function old(string $key, mixed $default = null): mixed
    {
        return $this->input[$key] ?? $default;
    }
}

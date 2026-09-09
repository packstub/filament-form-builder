<?php

namespace Packstub\FormBuilder\Submissions;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Packstub\FormBuilder\Models\Form;

/**
 * The time-trap token: an encrypted "form id + rendered at" pair that the
 * form carries in a hidden input, so a submission posted too quickly after
 * the render (a bot) can be told apart.
 */
class ProtectionToken
{
    public function make(Form $form, ?int $renderedAt = null): string
    {
        return Crypt::encryptString(json_encode([
            'f' => $form->getKey(),
            't' => $renderedAt ?? time(),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Seconds elapsed since the token was issued for this form, or null when
     * the token is missing, tampered with or belongs to another form.
     */
    public function age(Form $form, mixed $token): ?int
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true, 8, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return null;
        }

        if (! is_array($payload) || (string) ($payload['f'] ?? '') !== (string) $form->getKey()) {
            return null;
        }

        return max(0, time() - (int) ($payload['t'] ?? 0));
    }

    public function field(): string
    {
        return (string) config('packstub-form-builder.spam.token_field', '_fb_token');
    }
}

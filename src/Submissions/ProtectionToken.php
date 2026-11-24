<?php

namespace Packstub\FormBuilder\Submissions;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Packstub\FormBuilder\Models\Form;

/**
 * The time-trap token: an encrypted "form id + rendered at + nonce" triple
 * that the form carries in a hidden input, so a submission posted too
 * quickly after the render (a bot) can be told apart, and a token cannot
 * be replayed for a second submission. A form rendered through a share link
 * carries the link's token in it too, so a private form only takes
 * submissions from a render the server made, through a link still active.
 */
class ProtectionToken
{
    public function make(Form $form, ?int $renderedAt = null, ?string $link = null): string
    {
        return Crypt::encryptString(json_encode(array_filter([
            'f' => $form->getKey(),
            't' => $renderedAt ?? time(),
            'n' => Str::random(12),
            'l' => $link,
        ], fn (mixed $value): bool => $value !== null), JSON_THROW_ON_ERROR));
    }

    /**
     * Whether the token was issued for this form (not tampered with).
     */
    public function isValid(Form $form, mixed $token): bool
    {
        return $this->payload($form, $token) !== null;
    }

    /**
     * The token of the share link the form was rendered through, if any.
     */
    public function link(Form $form, mixed $token): ?string
    {
        $link = $this->payload($form, $token)['l'] ?? null;

        return is_string($link) && $link !== '' ? $link : null;
    }

    /**
     * Seconds elapsed since the token was issued for this form, or null when
     * the token is missing, tampered with or belongs to another form.
     */
    public function age(Form $form, mixed $token): ?int
    {
        $payload = $this->payload($form, $token);

        return $payload === null ? null : max(0, time() - (int) ($payload['t'] ?? 0));
    }

    /**
     * Whether the token was issued for this form longer ago than config
     * "spam.token_ttl" minutes: it no longer opens a private form and must
     * not post again once its "used" mark is gone.
     */
    public function isExpired(Form $form, mixed $token): bool
    {
        $age = $this->age($form, $token);

        return $age !== null && $age > $this->ttlMinutes() * 60;
    }

    /**
     * Mark the token used, atomically: false when another submission
     * claimed it first. A missing or foreign token has nothing to claim.
     */
    public function claim(Form $form, mixed $token): bool
    {
        $payload = $this->payload($form, $token);

        if ($payload === null || ! isset($payload['n'])) {
            return true;
        }

        return Cache::add($this->usedKey($payload['n']), true, now()->addMinutes($this->ttlMinutes()));
    }

    /**
     * Forget a claim, when the submission it was for could not be stored.
     */
    public function release(Form $form, mixed $token): void
    {
        $payload = $this->payload($form, $token);

        if ($payload !== null && isset($payload['n'])) {
            Cache::forget($this->usedKey($payload['n']));
        }
    }

    /**
     * Whether the token was already used for an accepted submission.
     */
    public function isUsed(Form $form, mixed $token): bool
    {
        $payload = $this->payload($form, $token);

        return $payload !== null && isset($payload['n']) && Cache::has($this->usedKey($payload['n']));
    }

    /**
     * Remember that the token was used, for as long as it is valid (config
     * "spam.token_ttl" minutes).
     */
    public function markUsed(Form $form, mixed $token): void
    {
        $payload = $this->payload($form, $token);

        if ($payload === null || ! isset($payload['n'])) {
            return;
        }

        Cache::put($this->usedKey($payload['n']), true, now()->addMinutes($this->ttlMinutes()));
    }

    protected function ttlMinutes(): int
    {
        return max(1, (int) config('packstub-form-builder.spam.token_ttl', 1440));
    }

    public function field(): string
    {
        return (string) config('packstub-form-builder.spam.token_field', '_fb_token');
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function payload(Form $form, mixed $token): ?array
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

        return $payload;
    }

    protected function usedKey(string $nonce): string
    {
        return 'packstub-form-builder:token:'.$nonce;
    }
}

<?php

namespace Packstub\FormBuilder\Submissions;

use Illuminate\Support\Facades\Http;
use Packstub\FormBuilder\Models\Form;

/**
 * Cloudflare Turnstile, hCaptcha and Google reCAPTCHA v3, verified server
 * side with the keys in config "captcha". None is required; a form picks
 * one in its spam settings.
 */
class Captcha
{
    public const PROVIDERS = ['turnstile', 'hcaptcha', 'recaptcha'];

    /**
     * The input name each provider's widget posts.
     */
    public static function responseField(string $provider): string
    {
        return match ($provider) {
            'turnstile' => 'cf-turnstile-response',
            'hcaptcha' => 'h-captcha-response',
            default => 'g-recaptcha-response',
        };
    }

    public static function siteKey(string $provider): ?string
    {
        $key = config("packstub-form-builder.captcha.{$provider}.site_key");

        return is_string($key) && $key !== '' ? $key : null;
    }

    public static function secret(string $provider): ?string
    {
        $secret = config("packstub-form-builder.captcha.{$provider}.secret");

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    /**
     * Whether the provider has both keys configured.
     */
    public static function isConfigured(string $provider): bool
    {
        return static::siteKey($provider) !== null && static::secret($provider) !== null;
    }

    /**
     * The script the widget needs and the markup of the widget itself.
     *
     * @return array{script: string, html: string}|null
     */
    public static function widget(Form $form): ?array
    {
        $provider = $form->captcha();

        if ($provider === null || ($siteKey = static::siteKey($provider)) === null) {
            return null;
        }

        return match ($provider) {
            'turnstile' => [
                'script' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
                'html' => '<div class="cf-turnstile fb-captcha" data-sitekey="'.e($siteKey).'"></div>',
            ],
            'hcaptcha' => [
                'script' => 'https://js.hcaptcha.com/1/api.js',
                'html' => '<div class="h-captcha fb-captcha" data-sitekey="'.e($siteKey).'"></div>',
            ],
            default => [
                'script' => 'https://www.google.com/recaptcha/api.js?render='.rawurlencode($siteKey),
                'html' => '<input type="hidden" name="g-recaptcha-response" class="fb-captcha" data-fb-recaptcha="'.e($siteKey).'" value="">',
            ],
        };
    }

    /**
     * Verify the response the widget posted. Returns null when it passes,
     * else the error message.
     *
     * @param  array<string, mixed>  $input
     */
    public function verify(Form $form, array $input, ?string $ip = null): ?string
    {
        $provider = $form->captcha();

        if ($provider === null || ($secret = static::secret($provider)) === null) {
            return null;
        }

        $response = $input[static::responseField($provider)] ?? $input['_fb_captcha'] ?? null;

        if (! is_string($response) || $response === '') {
            return __('packstub-form-builder::form-builder.frontend.captcha_required');
        }

        $endpoint = match ($provider) {
            'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            'hcaptcha' => 'https://api.hcaptcha.com/siteverify',
            default => 'https://www.google.com/recaptcha/api/siteverify',
        };

        try {
            $result = Http::asForm()->timeout(10)->post($endpoint, array_filter([
                'secret' => $secret,
                'response' => $response,
                'remoteip' => $ip,
            ]))->json();
        } catch (\Throwable) {
            $result = null;
        }

        if (! is_array($result) || ! ($result['success'] ?? false)) {
            return __('packstub-form-builder::form-builder.frontend.captcha_failed');
        }

        if ($provider === 'recaptcha') {
            $minimum = (float) config('packstub-form-builder.captcha.recaptcha.min_score', 0.5);

            if (isset($result['score']) && (float) $result['score'] < $minimum) {
                return __('packstub-form-builder::form-builder.frontend.captcha_failed');
            }

            // The form's script asks for the "submit" action.
            if (isset($result['action']) && $result['action'] !== 'submit') {
                return __('packstub-form-builder::form-builder.frontend.captcha_failed');
            }
        }

        if (is_string($result['hostname'] ?? null) && ! $this->hostnameAllowed($result['hostname'])) {
            return __('packstub-form-builder::form-builder.frontend.captcha_failed');
        }

        return null;
    }

    /**
     * Whether the captcha was solved on a page the form may be posted from:
     * this site, or a host of config "spam.allowed_origins". With no allowed
     * origins (embeds anywhere), any host.
     */
    protected function hostnameAllowed(string $hostname): bool
    {
        if (array_filter((array) config('packstub-form-builder.spam.allowed_origins', [])) === []) {
            return true;
        }

        if (app()->bound('request') && strcasecmp($hostname, request()->getHost()) === 0) {
            return true;
        }

        return app(SpamGuard::class)->originAllowed('https://'.$hostname);
    }
}

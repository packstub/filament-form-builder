# Spam protection

Built-in checks that need no third party, plus an optional captcha.

## Honeypot

A text input a person never sees (moved off-screen, excluded from the tab order and from autofill). A submission that fills it is dropped. Field name: `spam.honeypot_field` (`_fb_website`). Per form: **Honeypot** in Settings.

## Time trap and single-use token

The rendered form carries an encrypted token with the form id, the time it was rendered and a nonce. A submission whose token is missing, tampered with, issued for another form, or younger than `spam.min_seconds` (default 2) is dropped, and a token that already produced a submission cannot produce another. A token is valid for `spam.token_ttl` minutes (1440, a day, by default); after that the visitor is asked to reload the page, and a headless client fetches a new one. Per form: **Minimum seconds before submit**; 0 disables the time trap. Headless clients get a token from the definition endpoint and send it back under `protection.token_field`.

## Rate limit

`submissions.throttle` (default `10,1`: ten submissions per minute per IP) on the submit and unlock routes, through Laravel's `throttle` middleware. `null` disables it.

## Blocklists and origins

```php
'spam' => [
    'allowed_origins' => ['example.com', '*.example.org'],
    'blocklist' => [
        'words' => ['casino'],
        'email_domains' => ['spam.test'],
        'ips' => ['203.0.113.*'],
    ],
],
```

A submission whose `Origin` (or `Referer`) host is not on `allowed_origins` (when the list is not empty), whose text contains a blocked word, whose email field belongs to a blocked domain, or that comes from a blocked IP (wildcards allowed) is dropped.

## Captcha

Cloudflare Turnstile, hCaptcha and Google reCAPTCHA v3, verified on the server. Put the keys in `captcha` (or the `TURNSTILE_*`, `HCAPTCHA_*`, `RECAPTCHA_*` environment variables), then pick the provider per form under Settings › Spam protection, or set `captcha.default` for every form. Providers without keys are not offered. The widget renders in the Blade and Livewire renderers and on the hosted page; a missing or failed verification answers a validation error on `captcha`. A reCAPTCHA token must be for the `submit` action the form's script asks for, and with `spam.allowed_origins` set the provider's hostname must be this site or one of those hosts. Headless clients read the provider, site key and the input name from `protection.captcha` in the definition and post the widget's response under that name.

## One per person and limits

**One submission per person** (by user id when signed in, else by a hash of the IP address and the user agent) and **Maximum submissions** are in Settings › Limits; both answer with a message of your own. For guests, one per person is a courtesy limit: another browser or network counts as another person. Turn on **Require a logged-in user** where it must hold.

## What the sender sees

Dropped submissions get the same success state as real ones, so a bot learns nothing. `Packstub\FormBuilder\Events\SpamDetected` is dispatched with the reason (`honeypot`, `no_token`, `too_fast`, `reused_token`, `origin`, `blocked_word`, `blocked_email`, `blocked_ip`), the raw input and the context, for logging.

# Spam protection

Three checks, no captcha, no third party.

## Honeypot

A text input a person never sees (moved off-screen, excluded from the tab order and from autofill). A submission that fills it is dropped. Field name: `spam.honeypot_field` (`_fb_website`). Per form: **Honeypot** in Settings.

## Time trap

The rendered form carries an encrypted token with the form id and the time it was rendered. A submission whose token is missing, tampered with, issued for another form, or younger than `spam.min_seconds` (default 2) is dropped. Per form: **Minimum seconds before submit**; 0 disables it. Headless clients get a token from the definition endpoint and send it back under `protection.token_field`.

## Rate limit

`submissions.throttle` (default `10,1`: ten submissions per minute per IP) on the submit route, through Laravel's `throttle` middleware. `null` disables it.

## What the sender sees

Dropped submissions get the same success state as real ones, so a bot learns nothing. `Packstub\FormBuilder\Events\SpamDetected` is dispatched with the reason (`honeypot`, `no_token`, `too_fast`), the raw input and the context, for logging.

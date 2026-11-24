# Changelog

All notable changes to `packstub/filament-form-builder` are documented here.

## Unreleased

A security release for panels with tenancy. Please update.

**Upgrading:** with `tenancy.enabled`, a form's custom CSS and JavaScript are off by default; set `frontend.custom_css` or `frontend.custom_js` to `true` to keep them.

**Upgrading:** with `tenancy.enabled`, webhooks and chat channels reach public addresses only; set `webhooks.private_networks` to `true` to allow private ones.

**Upgrading:** with `tenancy.enabled`, a form's own sender address is ignored and its emails come from `notifications.from_email`; set `notifications.custom_from` to `true` to keep it.

- Security: in a panel with tenancy, **Notify in the panel** offered the users of every workspace, and a picked user got the submission's summary from another workspace; it now offers and notifies the workspace's members only (`tenancy.members` names the relationship)
- Security: a form's custom CSS could close its `<style>` element and run a script on the page; both renderers now neutralise `</` in it (a `<` such as `@media (width < 600px)` stays as written)
- Security: a file field accepted any stored path, so a submission could name another file on the uploads disk; it now takes the form's own uploads only
- Security: a condition value or a redirect URL could run a script on the form's page, and visitor values became HTML in the emails; all three are escaped or checked now
- Security: a webhook could reach private addresses (cloud metadata, localhost) and follow redirects; it now connects to the checked public address and follows none
- Security: with tenancy, a choice source on a public page ran outside any workspace and could list every workspace's rows; it now runs inside the form's workspace
- Security: with tenancy, a form could send its emails as any address on your mailer; the sender address is now yours (`notifications.custom_from`)
- Security: importing a form's JSON skipped the editor's checks; users, addresses, URLs and code it would refuse are now dropped
- Security: the endpoint that checks one step of a multi-step form stored uploaded files and ignored private forms and passwords; it now stores nothing, answers only visitors the form would take a submission from, and is rate limited (`submissions.validate_throttle`)
- Custom CSS and JavaScript can be turned off for every form (`frontend.custom_css`, `frontend.custom_js`)
- Fixed: links to a form's submissions in emails, panel notifications and chat messages were left out in a panel with tenancy
- `composer.json`'s homepage points at the package's page on packstub.dev

## 1.4.0 — 2026-10-02

- With `packstub/filament-rating` installed, Rating fields get a star input, a star column with the average and the distribution, and a stars filter (#14)
- Radio buttons and checkbox lists can lay out their choices in up to four columns
- Docs: a shorter Features list, and screenshots of the star input and the star column

## 1.3.0 — 2026-10-01

**Upgrading:** run `php artisan vendor:publish --tag=packstub-form-builder-migrations` and `php artisan migrate`. Share links answer at `/f/{token}`: exclude `f` from a catch-all route, or change `routes.share_prefix`. A private form now takes submissions only from a page the server rendered; a headless client sends back `protection.token` from the definition endpoint.

- Choices from your own data, registered with `FormBuilder::choices()` (#5)
- An Address field (#6)
- A message per submission to Slack, Discord or Microsoft Teams (#7)
- Your logo and brand colour on the emails (#8)
- Share links for private forms, with a label, an expiry and a submission cap (#9)
- A submissions-per-day chart, form owners with a My forms filter, and `FormBuilder::fake()` for tests (#10, #11, #12)
- Fixed: the plugin on a panel other than the default one

## 1.2.0 — 2026-09-25

The big one: conditions, multi-step forms, 14 new field types and a lot more around submissions.

**Upgrading:** run `php artisan vendor:publish --tag=packstub-form-builder-migrations`, `php artisan migrate`, then `php artisan filament:assets`. `SubmissionsCsv` is now `SubmissionsExport`, with the same methods.

- Show, hide or require a field based on other answers, checked on the server too
- Sections and multi-step forms with a progress bar and validation per step
- New field types such as file upload, rich text, rating, toggle and consent, on a twelve-column grid
- Captcha (Turnstile, hCaptcha, reCAPTCHA v3), passwords, submission limits and blocklists
- Signed webhooks with retries, emails with merge tags and a confirmation to the visitor
- Twenty templates, a live preview, JSON import and export, embeds, prefill and retention

## 1.1.1 — 2026-09-18

- A leaner download: development files stay in the repository. Nothing to do when upgrading.

## 1.1.0 — 2026-09-17

- The Livewire renderer needs nothing but `<livewire:form-builder>`: Filament's styles and scripts are added for you
- Blade renderer: your site's CSS no longer overrides the form's own margins
- Livewire renderer: the gap above the submit button is back on pages without the Blade stylesheet
- Docs: screenshots of the panel and both renderers

## 1.0.0 — 2026-09-09

First release of Filament Form Builder: design forms in a Filament resource, render them with Blade, Livewire or JSON, and store, email or export the submissions.

- 14 field types, each with a label, help text, default, width and Laravel rules
- Stored submissions with read state, filters and a CSV export, plus notification emails
- Blade, Livewire and JSON renderers, and a hosted page per form
- Spam protection without a captcha: a honeypot, a time trap and a rate limit
- Custom field types, submission sinks and events when you need more

Docs: https://packstub.dev/docs/filament-form-builder

# Changelog

All notable changes to `packstub/filament-form-builder` are documented here.

## Unreleased

Upgrading: run `php artisan vendor:publish --tag=packstub-form-builder-migrations` and `php artisan migrate` (a guarded migration adds the share links table, the link on submissions and the owner of a form). Share links answer at `/f/{token}`: exclude `f` from a catch-all route, or change `routes.share_prefix`. Signed share links from 1.2 keep working. A headless client posting to a private form sends back the protection token from the definition endpoint (`protection.token`). Nothing else changes for existing forms.

### Added

- **Choices from your data** (#5): a dropdown, multi-select, radio, toggle buttons or checkbox list takes its options from a source registered with `FormBuilder::choices($name, $source)` (a closure, an array or a `ChoiceSource` class; config `choice_sources` too), picked under **Options** in the builder. Resolved when the form renders and again on submit, once per request or queued job; the key is stored and the label shown.
- **Address field** (#6): street (two lines), city, region, postal code and country in plain inputs with browser autocomplete, stored as one object; the parts to show and the ones a required address needs (at least one); one line in tables and emails, a column per part in exports; used by the catering order and patient intake templates.
- **Chat channels** (#7): a message per submission to Slack, Discord or Microsoft Teams through an incoming webhook URL, queued, retried and logged with the webhook deliveries.
- **Branded emails** (#8): the form's logo and brand colour (Design tab) on the team email and the visitor's confirmation.
- **Share links** (#9): a private form's Share action creates a stored link at `/f/{token}` with a label, an optional expiry and submission cap (counted from stored submissions); the rendered form carries its link, so a revoked or expired link stops working on a page left open, and a link filled by a no-JavaScript submission still shows its success message; a **Share links** list on the form to copy, add and revoke them one by one; submissions record their link (a column, a filter, `share_link_id` in the webhook meta); `?link=` on the definition endpoint and `_fb_link` on submit for headless clients.
- **Submissions per day** (#10): a chart on the form's edit page over 7, 30 or 90 days, with the totals.
- **Ownership** (#11): forms record who created them (a **Created by** column, a **My forms** filter); `ownership.only_own` shows each user only their forms, `ownership.see_all` names the ability that still sees every one.
- **Testing helpers** (#12): `FormBuilder::fake()` records submissions and spam and skips emails, notifications, webhooks, channel messages and sinks; `assertSubmitted()`, `assertNotSubmitted()`, `assertSubmittedCount()`, `assertNothingSubmitted()`, `assertSpamDetected()`; `FormBuilder::validInput()` for test posts.
- **Field type hooks**: `nestedRules()`, `nestedAttributes()`, `exportColumns()`, `exportValue()`, `definition()`.

### Changed

- The webhook log on a form is now **Deliveries**, with a *Sent to* column (the webhook or a chat app).
- A validation error on a part of a value (`address.city`, `tags.0`) shows on its field in the Blade renderer.
- A private form takes submissions only from a page the server rendered (your own page, a signed link, an active share link); a post without its protection token answers 403 "This form is private."

### Fixed

- The plugin on a panel other than the default one: registering the routes failed with *Plugin [packstub-form-builder] is not registered for panel*.

## 1.2.0 — 2026-09-25

Upgrading: run `php artisan vendor:publish --tag=packstub-form-builder-migrations` and `php artisan migrate` (a guarded migration adds the submission number and fingerprint, the tenant column and the webhook deliveries table), then `php artisan filament:assets` (the Livewire stylesheet grew from 14 to 24 KB gzipped to cover the new components). `SubmissionsCsv` is now `SubmissionsExport` (same `download()` / `write()` signature). Nothing else changes for existing forms.

### Added

- **Conditions**: every field and section can be always visible, shown when or hidden when conditions on other fields hold (equal, not equal, containing, not containing, greater, less, empty, not empty; all or any), and a required field can be required only when or except when. Applied live by the Blade renderer's script and by the Livewire renderer, enforced on the server (a hidden field is not validated and is stored as `null`), exposed in the JSON definition. Conditions can reference fields added in the same editing session.
- **Sections and multi-step forms**: a Section block groups fields (title, description, own conditions, hidden toggle); with Display set to multi-step on the new Design tab every section is a step with a progress bar, step numbers, Next / Back buttons of your own and per-step validation (`POST /forms/{slug}/validate`, or Filament's wizard in Livewire). Without JavaScript everything shows on one page.
- **14 field types**: amount (prefix, suffix, decimals), rich text, multi-select, toggle buttons, toggle, tags, rating, date and time, time, file upload (private disk, several files, accepted types, size, signed downloads, base64 over the JSON API, deleted with the submission), colour, country (ISO codes), consent (checkbox with a link) and divider. Widths: quarter, third, half, two thirds, three quarters, full on a twelve-column grid.
- **Validation picker**: rules chosen from a list that fits the type (about 40), a custom error message per field, the free-text rules kept.
- **Hidden fields**: keep a field in the design without showing it; old values stay in exports and columns.
- **Live preview**: a Preview action on the create and edit pages renders the form from the unsaved state in a slide-over and takes a test submission without storing it.
- **Templates**: twenty built-in templates under "Use a template" (contact, lead generation, feedback and surveys, HR, support, agency, healthcare, orders), `Templates::add()` for your own.
- **Duplicate, export and import**: a copy with a free slug, a JSON export, an import on the list; `Form::fromArray()` renders a portable array with either component, `$form->toPortable()` produces one.
- **Share**: a Share action with the public link, the availability window and the iframe snippet; a private visibility whose form opens only through a signed share link, with an optional expiry (`$form->shareUrl($until)`).
- **Password**: a per-form password with a prompt in every renderer, an encrypted key in the session or the query string, `POST /forms/{slug}/unlock` for JSON clients.
- **Limits**: one submission per person (user id, else a hash of IP and user agent), a maximum number of submissions, a closed message, a full message and an already-submitted message of your own; the JSON definition and the page tell.
- **Single-use tokens**: the time-trap token now carries a nonce and cannot post twice (`spam.token_ttl`).
- **Captcha**: Cloudflare Turnstile, hCaptcha and Google reCAPTCHA v3, verified on the server, chosen per form once the keys are in `captcha`; widgets in the Blade and Livewire renderers, `protection.captcha` in the definition.
- **Blocklists and origins**: `spam.blocklist` (words, email domains, IPs) and `spam.allowed_origins`; new `SpamDetected` reasons.
- **Sequential numbers**: submissions get a number per form (`#42`), shown in the table, the details, the emails and the exports.
- **Submissions table**: one column per field (searchable, sortable, toggleable), a filter per choice, boolean or date field, an Edit slide-over with the Filament components, file downloads in the details, rich text rendered, an Excel export when OpenSpout is installed.
- **Retention**: `form-builder:prune` deletes submissions older than the form's or the config's retention, anonymises older ones and drops old webhook deliveries; `--dry-run`.
- **Notifications tab**: subject with merge tags (`{form_name}`, `{submission_number}`, `{field_key}`…), from, reply-to (an address or the respondent), CC, BCC, uploaded files attached; a confirmation email to the visitor with a Markdown body and merge tags; Filament database notifications to chosen users.
- **Webhooks**: a URL per form, POST / PUT / PATCH, Standard Webhooks signing with a generated secret, field selection, metadata toggle, extra headers, queued deliveries with retries, a Webhook deliveries relation manager with a retry action, `WebhookDelivery` model.
- **Embeds**: `?embed=1` renders the hosted page bare and posts its height for an iframe (snippet on the Embed tab); `GET /forms/{slug}/embed.js` renders the form into any page through the JSON API with the stylesheet, the in-place submit, the conditions and the steps.
- **Prefill**: `:values` on both components and the page URL's query parameters (`frontend.prefill`, per form).
- **Design tab**: labels beside the fields, page title, meta description, social image and logo on the hosted page, a brand colour, custom CSS and JavaScript scoped to the form.
- **Tenancy**: `tenancy.enabled` scopes forms to Filament's current tenant (or a resolver of your own) through a global scope and a tenant column.
- **Formats**: `formats.date`, `datetime`, `time` for the panel, emails and exports.
- **Field type hooks**: `ruleCategory()`, `hasPlaceholder()`, `hasDefault()`, `choices()`, `prepare()`, `comparableValue()`, `display()`, `tableColumn()`, `tableFilter()`.

### Changed

- **Livewire stylesheet** now covers the toggle, toggle buttons, tags, file upload, colour picker, rich editor, section, wizard and badge components (24 KB gzipped).
- **Forms table**: "Active" reads "Published"; Duplicate, Export JSON and Delete sit in a row menu so the row fits.
- **Field editor**: blocks open collapsed (the header carries the label, type and hidden state), the conditions and the validation rules of a field live in two compact sections under its settings, collapsed until one is set, with a summary in the header ("Shown when 2 conditions hold", "3 rules · custom message"); the requirement's match and conditions show only for a conditional requirement.
- **Embed and Share**: every snippet and link carries a clipboard icon and a "Click to copy" tooltip, multi-line snippets keep their line breaks; the Share dialog's button reads "Save" on a public form and "Generate link" on a private one.

## 1.1.1 — 2026-09-18

Upgrading: nothing to do — no code, migration or config changed.

### Changed

- **Leaner dist archive**: `composer require` downloads what an install runs; the changelog (read it on GitHub, or in each release's notes), `pint.json` and the other development files stay in the repository (`.gitattributes` `export-ignore`).
- **Package health in CI** (`.github/workflows/package-health.yml`, `.github/scripts/package-health.sh`): every push checks what the package health score on filamentphp.com checks (Powered by [Plumb](https://plumbphp.dev/packstub/filament-form-builder)) — a lean dist archive, no `composer.lock` in it, actions pinned to a commit SHA, Dependabot with a cooldown for every ecosystem that has a lockfile, a security policy — on the commit, before a tag ships it. A weekly run reads the published score and fails below 100. Dependabot now also watches the Tailwind CLI used to build the Livewire stylesheet (`npm`).

## 1.1.0 — 2026-09-17

### Added

- **Livewire renderer**: the page needs nothing but `<livewire:form-builder>`. Filament's colour variables, its scripts and the renderer's stylesheet are injected into the response the way Livewire injects its own script, unless the layout already prints `@filamentStyles` / `@filamentScripts`. The stylesheet is compiled from the Filament component files the built-in field types render (14 KB gzipped against 63 KB for the panel theme, no preflight; the reset the components rely on is scoped to the form) and published by `filament:assets`. Config: `frontend.livewire_assets` (off to handle the frontend yourself) and `frontend.livewire_theme` (the panel theme or your own when custom field types need more). `LivewireAssets::styles()` / `::scripts()` print the same pieces by hand.

### Fixed

- **Blade renderer**: every rule of the inlined stylesheet is scoped under `.fb-form`, so a site rule such as `main p { margin-bottom: 1.5rem }` no longer beats the hint, error and paragraph margins.
- **Livewire renderer**: the submit button keeps the schema's gap above it on pages that do not also inline the Blade stylesheet.

### Changed

- **Docs**: screenshots of the panel and of the Blade and Livewire renderers in the README and the docs pages, taken in the demo rig.

## 1.0.0 — 2026-09-09

### Added

- **Forms resource**: build forms in the panel with a block per field type (text, email, phone, URL, number, long text, dropdown, radio buttons, checkbox, checkbox list, date, hidden, heading, paragraph), each with label, key, placeholder, help text, default, required, width and extra Laravel rules; settings for the submit button, success message, redirect, notification emails, storing submissions, an availability window, login requirement and spam protection; an Embed tab with copyable snippets.
- **Submissions**: stored with the values keyed by field key plus a snapshot of the labels, the page they came from, the channel, IP and user agent (both optional); a relation manager with a details slide-over, read / unread state, filters, bulk actions and a CSV export without extra packages; an unread badge on the navigation item.
- **Three renderers** on one submission pipeline: the `<x-form-builder::form>` Blade component (plain HTML, session-less friendly, optional in-place fetch submission), the `<livewire:form-builder>` component (Filament fields, in-place validation) and a JSON API (`GET /forms/{slug}/definition`, `POST /forms/{slug}` with `Accept: application/json`); a hosted page per form.
- **Spam protection** without a captcha: a honeypot, a time trap (encrypted token issued with the form) and a per-IP rate limit; dropped submissions fire `SpamDetected`.
- **Extending**: custom field types (`FieldType`), submission sinks (`SubmissionSink`) for CRMs and webhooks, the `SubmissionReceived` event, `FormBuilder::submit()` from code, swappable models and table names, CSS variables for theming.

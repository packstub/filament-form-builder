# Changelog

All notable changes to `packstub/filament-form-builder` are documented here.

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

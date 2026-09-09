# Changelog

All notable changes to `packstub/filament-form-builder` are documented here.

## 1.0.0 — unreleased

### Added

- **Forms resource**: build forms in the panel with a block per field type (text, email, phone, URL, number, long text, dropdown, radio buttons, checkbox, checkbox list, date, hidden, heading, paragraph), each with label, key, placeholder, help text, default, required, width and extra Laravel rules; settings for the submit button, success message, redirect, notification emails, storing submissions, an availability window, login requirement and spam protection; an Embed tab with copyable snippets.
- **Submissions**: stored with the values keyed by field key plus a snapshot of the labels, the page they came from, the channel, IP and user agent (both optional); a relation manager with a details slide-over, read / unread state, filters, bulk actions and a CSV export without extra packages; an unread badge on the navigation item.
- **Three renderers** on one submission pipeline: the `<x-form-builder::form>` Blade component (plain HTML, session-less friendly, optional in-place fetch submission), the `<livewire:form-builder>` component (Filament fields, in-place validation) and a JSON API (`GET /forms/{slug}/definition`, `POST /forms/{slug}` with `Accept: application/json`); a hosted page per form.
- **Spam protection** without a captcha: a honeypot, a time trap (encrypted token issued with the form) and a per-IP rate limit; dropped submissions fire `SpamDetected`.
- **Extending**: custom field types (`FieldType`), submission sinks (`SubmissionSink`) for CRMs and webhooks, the `SubmissionReceived` event, `FormBuilder::submit()` from code, swappable models and table names, CSS variables for theming.

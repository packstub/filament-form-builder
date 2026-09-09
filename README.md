# Filament Form Builder

<div class="filament-hidden">

![Filament Form Builder — build forms in the panel, render them anywhere](https://raw.githubusercontent.com/packstub/filament-form-builder/main/art/banner.jpg)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/packstub/filament-form-builder.svg?style=flat-square)](https://packagist.org/packages/packstub/filament-form-builder)
[![Tests](https://img.shields.io/github/actions/workflow/status/packstub/filament-form-builder/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/packstub/filament-form-builder/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/packstub/filament-form-builder.svg?style=flat-square)](https://packagist.org/packages/packstub/filament-form-builder)
[![License](https://img.shields.io/packagist/l/packstub/filament-form-builder.svg?style=flat-square)](https://github.com/packstub/filament-form-builder/blob/main/LICENSE.md)
[![Sponsor](https://img.shields.io/badge/sponsor-%E2%9D%A4-ea4aaa?style=flat-square&logo=githubsponsors&logoColor=white)](https://github.com/sponsors/icaliman)

</div>

Build forms in your Filament panel, put them on your site with one Blade tag, a Livewire component or a JSON call, and read the submissions where you already work.

## Features

- **[Builder in the panel](#building-a-form)** — a Forms resource with a block per field type: text, email, phone, URL, number, long text, dropdown, radio buttons, checkbox, checkbox list, date, hidden, heading and paragraph. Every field has a label, key, placeholder, help text, default, required flag, width and any extra Laravel validation rule you type in.
- **[Three ways to render](#rendering-a-form)** — a plain Blade component that works on cached and session-less pages, a Livewire component with Filament fields and in-place validation, and a JSON API for SPAs and mobile apps. All three go through the same validation and storage.
- **[Submissions](#submissions)** — stored with the values, the page they came from and the labels at the time, listed per form with a details slide-over, read / unread state, filters, bulk actions and a CSV export. Unread counts on the navigation item.
- **[Notifications and sinks](#notifications-and-sinks)** — an email per submission to any list of addresses, a `SubmissionReceived` event, and a `SubmissionSink` contract for the CRM, webhook or mailing list you want to feed.
- **[Spam protection without a captcha](#spam-protection)** — a honeypot, a time trap (an encrypted token issued with the form) and a per-IP rate limit. Bots see the success message; nothing is stored.
- **[Availability](#settings)** — a success message or a redirect, an active switch, an opening and closing date, an optional login requirement, and a "do not store" mode for forms that should only be emailed.
- **[Extensible](#extending)** — write a field type class with its own settings, rules and views; swap the models and table names; submit from code with `FormBuilder::submit()`.
- **[Themeable and translatable](#theming)** — the Blade renderer uses CSS variables with sensible defaults and ships its own small stylesheet; every string lives in a language file.

## Compatibility

| Plugin | Filament | Laravel | PHP |
| --- | --- | --- | --- |
| 1.x | 4.x, 5.x | 12.x, 13.x | 8.3+ |

## Installation

```bash
composer require packstub/filament-form-builder
php artisan packstub-form-builder:install
```

Register the plugin on your panel:

```php
use Packstub\FormBuilder\FormBuilderPlugin;

$panel->plugin(FormBuilderPlugin::make());
```

A **Forms** resource appears in the navigation.

## Building a form

Create a form, give it a name, and add fields from the block picker. Each block carries the settings of its type: choices for a dropdown or a checkbox list, a range for numbers and dates, rows for long text, the level of a heading. Keys are derived from labels and kept unique, so values in submissions and exports keep a stable name.

The **Settings** tab holds the submit button label, the success message or a redirect URL, notification addresses, whether submissions are stored, an opening and closing date, a login requirement and the spam settings. The **Embed** tab shows the snippets for the form you are editing.

## Rendering a form

### Blade

```blade
<x-form-builder::form form="contact" />
```

A plain HTML form posting to `/forms/contact`. Without JavaScript the browser posts and comes back to the page with the success message or the errors and the old input. With the small script the component inlines (`frontend.enhance`), the form submits with `fetch` and shows the result in place, without reloading.

The component works on pages without a session: errors and old input travel in an encrypted query parameter instead of the session. The submit route runs the `web` middleware by default; on a session-less site set `routes.middleware` to `[]` and rely on the honeypot, the time trap and the rate limit.

Options: `:enhance="false"` for a plain POST only, `:styles="false"` when your site ships its own CSS, `action` and `return` to override the endpoint and the page to come back to.

### Livewire

```blade
<livewire:form-builder form="contact" />
```

The fields as Filament components, validated in place. The page needs Filament's frontend assets:

```blade
@filamentStyles
@filamentScripts
```

### JSON

```http
GET  /forms/contact/definition
POST /forms/contact            Accept: application/json
```

The definition lists the fields with their type, rules and choices, plus a fresh protection token and the names of the anti-spam fields to send back. The POST answers `{ "ok": true, "message": "...", "redirect": null, "id": 12 }` or `422` with `errors` keyed by field.

### Hosted page

Every form is also served on its own at `/forms/{slug}` (switch off with `routes.page`, change the layout with `routes.page_layout`).

## Submissions

Submissions live under the form as a relation manager: the date, a summary of the first values, the page they came from and the channel (web, json, livewire, code). Opening one shows every value and the details, and marks it read. Bulk actions mark as read, export or delete. The CSV export lists the form's current fields, then any key an older submission still carries.

```php
use Packstub\FormBuilder\Models\FormSubmission;

FormSubmission::query()->unread()->count();
$submission->value('email');
$submission->formatted(); // ['email' => ['label' => 'Email', 'value' => 'ada@example.com'], ...]
```

## Notifications and sinks

Add addresses to **Notify by email** and each submission is emailed (queued when a queue is configured). For anything else, write a sink:

```php
use Packstub\FormBuilder\Contracts\SubmissionSink;
use Packstub\FormBuilder\Models\FormSubmission;

class PushToCrm implements SubmissionSink
{
    public function handle(FormSubmission $submission): void
    {
        Crm::createLead($submission->form->slug, $submission->data);
    }
}
```

Register it in `config/packstub-form-builder.php` under `sinks`, or at runtime with `FormBuilder::sink(PushToCrm::class)`. The `SubmissionReceived` and `SpamDetected` events are dispatched as well.

## Spam protection

- **Honeypot** — a hidden input a person never sees; a filled one drops the submission.
- **Time trap** — the form carries an encrypted token with the time it was rendered; a submission posted faster than `spam.min_seconds` (default 2) is dropped. Headless clients get the token from the definition endpoint.
- **Rate limit** — `submissions.throttle` (default `10,1`) per IP on the submit endpoint.

Both checks can be tuned per form in its Settings tab. Dropped submissions look like a success to the sender and fire `SpamDetected`.

## Settings

| Setting | What it does |
| --- | --- |
| Active | Off: the form renders as closed and rejects submissions |
| Submit button, success message | Shown by every renderer |
| Redirect after submit | Sends the visitor to a URL instead of showing the message |
| Notify by email | One email per submission to each address |
| Store submissions | Off: the submission is only emailed and passed to sinks |
| Opens at, closes at | An availability window |
| Require a logged-in user | Rejects anonymous submissions |
| Honeypot, minimum seconds | Per-form spam settings |

## Extending

A field type is a class: its id, an icon, the settings it shows in the builder, its validation rules, how it normalises a value, a Blade view and a Filament component.

```php
use Filament\Forms\Components\TextInput;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\Types\NumberField;

class RatingField extends NumberField
{
    public static function id(): string
    {
        return 'rating';
    }

    public function rules(Field $field): array
    {
        return ['integer', 'between:1,5'];
    }
}
```

Register it in the config under `field_types`, on the plugin with `FormBuilderPlugin::make()->fieldTypes([RatingField::class])`, or with `FormBuilder::registerFieldTypes([...])`. Hide built-ins with `->withoutFieldTypes([...])`. Name the label in your language file under `packstub-form-builder::form-builder.types.rating`.

Submit from code:

```php
use Packstub\FormBuilder\Facades\FormBuilder;

FormBuilder::submit('contact', ['name' => 'Ada', 'email' => 'ada@example.com', 'message' => 'Hi']);
```

## Theming

The Blade renderer scopes everything under `.fb-form` and reads these variables, each with a fallback:

```css
:root {
    --fb-color-text: #0f172a;
    --fb-color-muted: #475569;
    --fb-color-border: #cbd5e1;
    --fb-color-surface: #fff;
    --fb-color-primary: #2563eb;
    --fb-color-primary-contrast: #fff;
    --fb-color-danger: #dc2626;
    --fb-color-success: #15803d;
    --fb-radius: .5rem;
    --fb-font: inherit;
}
```

Publish the stylesheet and script with `php artisan vendor:publish --tag=packstub-form-builder-assets` and set `frontend.styles` / `frontend.enhance` to `false` to ship them your own way. Views publish with `--tag=packstub-form-builder-views`, the language file with `--tag=packstub-form-builder-translations`.

## Configuration

```bash
php artisan vendor:publish --tag=packstub-form-builder-config
```

| Key | Default | What it does |
| --- | --- | --- |
| `tables.*`, `models.*` | `form_builder_*` | Table names and model classes |
| `field_types` | the built-ins | Field types offered in the builder |
| `routes.prefix` | `forms` | URL prefix of the submit, definition and hosted page routes |
| `routes.middleware` | `['web']` | Middleware of the submit and definition routes |
| `routes.page`, `page_middleware`, `page_layout` | on, `['web']`, package layout | The hosted page |
| `submissions.throttle` | `10,1` | Rate limit per IP; `null` to disable |
| `submissions.store_ip`, `store_user_agent` | `true` | What the submission row records |
| `submissions.queue_notifications` | `true` | Queue the notification emails |
| `spam.honeypot`, `honeypot_field`, `min_seconds`, `token_field` | on, `_fb_website`, `2`, `_fb_token` | Spam defaults (per form in the panel) |
| `sinks` | `[]` | `SubmissionSink` classes |
| `frontend.styles`, `frontend.enhance` | `true` | Inline the stylesheet and the fetch script |
| `navigation.*`, `gate` | — | Navigation group, icon, sort, unread badge; an ability to check |

Plugin methods: `fieldTypes()`, `withoutFieldTypes()`, `resource()`, `withoutResource()`, `navigationGroup()`, `navigationIcon()`, `navigationSort()`, `navigationBadge()`, `authorize()`.

## Documentation

[Installation](https://packstub.dev/docs/filament-form-builder/installation) · [Building forms](https://packstub.dev/docs/filament-form-builder/building-forms) · [Rendering](https://packstub.dev/docs/filament-form-builder/rendering) · [Submissions](https://packstub.dev/docs/filament-form-builder/submissions) · [Spam protection](https://packstub.dev/docs/filament-form-builder/spam-protection) · [Extending](https://packstub.dev/docs/filament-form-builder/extending) · [Configuration](https://packstub.dev/docs/filament-form-builder/configuration)

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG](CHANGELOG.md).

## Security

Report vulnerabilities to [support@packstub.dev](mailto:support@packstub.dev) rather than the issue tracker.

## Credits

- [Ion Caliman](https://github.com/icaliman)
- [All contributors](https://github.com/packstub/filament-form-builder/contributors)

## License

The MIT License (MIT). See [LICENSE](LICENSE.md).

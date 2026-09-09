# Configuration

```bash
php artisan vendor:publish --tag=packstub-form-builder-config
```

| Key | Default | What it does |
| --- | --- | --- |
| `tables.forms`, `tables.submissions` | `form_builder_forms`, `form_builder_submissions` | Table names (set before migrating) |
| `models.form`, `models.submission` | package models | Model classes |
| `field_types` | the built-ins | Field types offered in the builder |
| `routes.enabled` | `true` | Register the routes at all |
| `routes.prefix` | `forms` | URL prefix |
| `routes.middleware` | `['web']` | Middleware of the submit and definition routes |
| `routes.page` | `true` | The hosted page |
| `routes.page_middleware` | `['web']` | Its middleware |
| `routes.page_layout` | `packstub-form-builder::layout` | Its layout component |
| `submissions.throttle` | `10,1` | Rate limit per IP; `null` disables |
| `submissions.store_ip` | `true` | Record the IP |
| `submissions.store_user_agent` | `true` | Record the user agent |
| `submissions.queue_notifications` | `true` | Queue notification emails |
| `spam.honeypot` | `true` | Honeypot default (per form in the panel) |
| `spam.honeypot_field` | `_fb_website` | Name of the honeypot input |
| `spam.min_seconds` | `2` | Time-trap default (per form in the panel) |
| `spam.token_field` | `_fb_token` | Name of the token input |
| `sinks` | `[]` | `SubmissionSink` classes |
| `frontend.styles` | `true` | Inline the stylesheet with the Blade renderer |
| `frontend.enhance` | `true` | Inline the fetch script |
| `navigation.group`, `icon`, `sort`, `badge` | — | Navigation of the Forms resource |
| `gate` | `null` | An ability checked before showing the resource |

## Plugin methods

```php
FormBuilderPlugin::make()
    ->fieldTypes([RatingField::class])
    ->withoutFieldTypes(['url'])
    ->resource(MyFormResource::class)
    ->withoutResource()
    ->navigationGroup('Content')
    ->navigationIcon('heroicon-o-inbox')
    ->navigationSort(3)
    ->navigationBadge(false)
    ->authorize(fn (): bool => auth()->user()->can('manage forms'));
```

## Translations and views

```bash
php artisan vendor:publish --tag=packstub-form-builder-translations
php artisan vendor:publish --tag=packstub-form-builder-views
php artisan vendor:publish --tag=packstub-form-builder-assets
```

Every string, field type name and frontend message is in `lang/vendor/packstub-form-builder/en/form-builder.php`.

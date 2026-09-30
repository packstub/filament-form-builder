# Configuration

```bash
php artisan vendor:publish --tag=packstub-form-builder-config
```

| Key | Default | What it does |
| --- | --- | --- |
| `tables.forms`, `tables.submissions`, `tables.webhook_deliveries`, `tables.share_links` | `form_builder_*` | Table names (set before migrating) |
| `models.form`, `models.submission`, `models.webhook_delivery`, `models.share_link` | package models | Model classes |
| `field_types` | the built-ins | Field types offered in the builder |
| `choice_sources` | `[]` | Named choice lists: `ChoiceSource` classes or `value => label` arrays (closures go through `FormBuilder::choices()`) |
| `routes.enabled` | `true` | Register the routes at all |
| `routes.prefix` | `forms` | URL prefix |
| `routes.middleware` | `['web']` | Middleware of the submit, validate, unlock and definition routes |
| `routes.page` | `true` | The hosted page |
| `routes.page_middleware` | `['web']` | Its middleware |
| `routes.page_layout` | `packstub-form-builder::layout` | Its layout component |
| `routes.embed` | `true` | The embed script route |
| `routes.share_prefix` | `f` | Where share links live (`/f/{token}`); `null` turns them off |
| `submissions.throttle` | `10,1` | Rate limit per IP; `null` disables |
| `submissions.store_ip` | `true` | Record the IP |
| `submissions.store_user_agent` | `true` | Record the user agent |
| `submissions.queue_notifications` | `true` | Queue notification emails |
| `submissions.retention_days` | `0` | Delete submissions older than this when pruning (0 keeps them; per form in the panel) |
| `submissions.anonymize_after_days` | `0` | Strip IP and user agent from older submissions when pruning |
| `uploads.disk`, `uploads.directory` | `local`, `form-builder` | Where the file upload field stores files |
| `uploads.max_kb` | `10240` | Default size limit of an upload |
| `uploads.attach_max_kb` | `10240` | What the team email attaches at most, in total |
| `spam.honeypot` | `true` | Honeypot default (per form in the panel) |
| `spam.honeypot_field` | `_fb_website` | Name of the honeypot input |
| `spam.min_seconds` | `2` | Time-trap default (per form in the panel) |
| `spam.token_field` | `_fb_token` | Name of the token input |
| `spam.token_ttl` | `120` | Minutes a used token is remembered |
| `spam.allowed_origins` | `[]` | Hosts the form may be posted from (empty: any) |
| `spam.blocklist.words`, `email_domains`, `ips` | `[]` | Drop submissions that match |
| `captcha.default` | `null` | Provider for forms that pick none: `turnstile`, `hcaptcha`, `recaptcha` |
| `captcha.{provider}.site_key`, `secret` | env | The keys; `captcha.recaptcha.min_score` (`0.5`) for v3 |
| `notifications.from_email`, `from_name` | env | Defaults for the emails a form sends |
| `webhooks.queue` | `true` | Queue deliveries |
| `webhooks.attempts` | `3` | Tries per delivery |
| `webhooks.timeout` | `15` | Seconds per request |
| `webhooks.keep_days` | `30` | Days the delivery log is kept |
| `webhooks.verify_ssl` | `true` | Verify the endpoint's certificate |
| `formats.date`, `datetime`, `time` | `Y-m-d`, `Y-m-d H:i`, `H:i` | Display formats in the panel, emails and exports |
| `sinks` | `[]` | `SubmissionSink` classes |
| `frontend.styles` | `true` | Inline the stylesheet with the Blade renderer |
| `frontend.enhance` | `true` | Inline the script (in-place submit, conditions, steps) |
| `frontend.prefill` | `true` | Let the page URL's query parameters fill fields (per form in the panel) |
| `frontend.livewire_assets` | `true` | Put Filament's frontend on pages that render the Livewire component |
| `frontend.livewire_theme` | `null` | Its stylesheet: `null` the compiled one, a path or URL, `false` none |
| `tenancy.enabled` | `false` | Scope forms to a tenant |
| `tenancy.column`, `model`, `resolver` | `tenant_id`, `null`, `null` | The column, the tenant model, a callable returning the current tenant (Filament's by default) |
| `ownership.only_own` | `false` | Show each user only the forms they created |
| `ownership.see_all` | `null` | A gate ability whose users still see every form |
| `navigation.group`, `icon`, `sort`, `badge` | — | Navigation of the Forms resource |
| `gate` | `null` | An ability checked before showing the resource |

## Plugin methods

```php
FormBuilderPlugin::make()
    ->fieldTypes([ScoreField::class])
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

Every string, field type name, rule name, operator and frontend message is in `lang/vendor/packstub-form-builder/en/form-builder.php`.

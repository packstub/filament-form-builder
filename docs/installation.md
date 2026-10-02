# Installation

```bash
composer require packstub/filament-form-builder
php artisan packstub-form-builder:install
```

The install command publishes the config file and the migrations and offers to run them. Three tables are created: `form_builder_forms`, `form_builder_submissions` and `form_builder_webhook_deliveries` (rename them in `tables` before migrating).

Register the plugin on every panel that should manage forms:

```php
use Packstub\FormBuilder\FormBuilderPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(
            FormBuilderPlugin::make()
                ->navigationGroup('Content'),
        );
}
```

## Requirements

| Plugin | Filament | Laravel | PHP |
| --- | --- | --- | --- |
| 1.x | 4.x, 5.x | 12.x, 13.x | 8.3+ |

No other package is required. The CSV export, the emails, the webhooks and the spam protection are built in; the Excel export appears when OpenSpout is installed, the captcha when a provider's keys are set, the panel notifications when Filament's database notifications are set up, and the Rating field shows stars in the panel and the Livewire renderer, with an average under its column, when [packstub/filament-rating](building-forms.md#with-packstubfilament-rating) is installed.

The Livewire renderer's stylesheet is a Filament asset: `php artisan filament:assets` publishes it (the `filament:upgrade` script Filament adds to `composer.json` runs that on every update).

## Routes

The package registers, under `routes.prefix` (`forms`):

| Route | Name | Purpose |
| --- | --- | --- |
| `POST /forms/{slug}` | `packstub-form-builder.submit` | Submit (HTML redirect back, or JSON with `Accept: application/json`) |
| `POST /forms/{slug}/validate` | `packstub-form-builder.validate` | Validate one step of a multi-step form |
| `POST /forms/{slug}/unlock` | `packstub-form-builder.unlock` | Take the password of a protected form |
| `GET /forms/{slug}/definition` | `packstub-form-builder.definition` | The form definition for headless clients |
| `GET /forms/{slug}/embed.js` | `packstub-form-builder.embed` | The script embed (off with `routes.embed`) |
| `GET /forms/{slug}` | `packstub-form-builder.show` | The hosted page (off with `routes.page`); `?embed=1` for an iframe |
| `GET /forms/files/{submission}/{field}/{index}` | `packstub-form-builder.file` | A signed download of an uploaded file |
| `GET /f/{token}` | `packstub-form-builder.share` | A private form through a share link (`routes.share_prefix`; `null` turns it off) |

If your site has a catch-all route (a CMS), make sure `forms` and `f` are excluded from it, or change the prefixes.

## Scheduling

Add `form-builder:prune` to the scheduler when a form keeps submissions for a limited time or old ones should lose their IP address (see [Submissions](submissions.md#retention)).

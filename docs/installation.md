# Installation

```bash
composer require packstub/filament-form-builder
php artisan packstub-form-builder:install
```

The install command publishes the config file and the migration and offers to run it. Two tables are created: `form_builder_forms` and `form_builder_submissions` (rename them in `tables` before migrating).

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

No other package is required. The CSV export, the email notification and the spam protection are built in.

## Routes

The package registers, under `routes.prefix` (`forms`):

| Route | Name | Purpose |
| --- | --- | --- |
| `POST /forms/{slug}` | `packstub-form-builder.submit` | Submit (HTML redirect back, or JSON with `Accept: application/json`) |
| `GET /forms/{slug}/definition` | `packstub-form-builder.definition` | The form definition for headless clients |
| `GET /forms/{slug}` | `packstub-form-builder.show` | The hosted page (off with `routes.page`) |

If your site has a catch-all route (a CMS), make sure `forms` is excluded from it, or change the prefix.

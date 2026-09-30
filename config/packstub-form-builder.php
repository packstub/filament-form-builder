<?php

use Packstub\FormBuilder\Fields\Types;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;
use Packstub\FormBuilder\Models\ShareLink;
use Packstub\FormBuilder\Models\WebhookDelivery;

return [

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    |
    | Table names are prefixed so they never collide with a "forms" table your
    | application may already have. Change them before running the migration.
    |
    */

    'tables' => [
        'forms' => 'form_builder_forms',
        'submissions' => 'form_builder_submissions',
        'webhook_deliveries' => 'form_builder_webhook_deliveries',
        'share_links' => 'form_builder_share_links',
    ],

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Swap any of these for your own subclass if you need extra columns,
    | scopes or relationships.
    |
    */

    'models' => [
        'form' => Form::class,
        'submission' => FormSubmission::class,
        'webhook_delivery' => WebhookDelivery::class,
        'share_link' => ShareLink::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Field types
    |--------------------------------------------------------------------------
    |
    | Every field type offered in the builder. Add your own class (extending
    | Packstub\FormBuilder\Fields\FieldType) here, on the plugin with
    | FormBuilderPlugin::make()->fieldTypes([...]), or at runtime with
    | FormBuilder::registerFieldTypes([...]).
    |
    */

    'field_types' => [
        Types\TextField::class,
        Types\EmailField::class,
        Types\PhoneField::class,
        Types\UrlField::class,
        Types\NumberField::class,
        Types\CurrencyField::class,
        Types\TextareaField::class,
        Types\RichTextField::class,
        Types\SelectField::class,
        Types\MultiSelectField::class,
        Types\RadioField::class,
        Types\ToggleButtonsField::class,
        Types\CheckboxField::class,
        Types\ToggleField::class,
        Types\CheckboxesField::class,
        Types\TagsField::class,
        Types\RatingField::class,
        Types\DateField::class,
        Types\DateTimeField::class,
        Types\TimeField::class,
        Types\FileField::class,
        Types\ColorField::class,
        Types\CountryField::class,
        Types\AddressField::class,
        Types\ConsentField::class,
        Types\HiddenField::class,
        Types\HeadingField::class,
        Types\ParagraphField::class,
        Types\DividerField::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Choice sources
    |--------------------------------------------------------------------------
    |
    | Named lists a select, radio, checkbox list or multi-select can take its
    | options from instead of the ones typed in the builder: name => a class
    | implementing Packstub\FormBuilder\Contracts\ChoiceSource, or a fixed
    | value => label array. Closures go through FormBuilder::choices() in a
    | service provider (config must stay cacheable).
    |
    */

    'choice_sources' => [],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Every form gets a POST endpoint (HTML or JSON, depending on the Accept
    | header), a JSON definition endpoint for headless clients, a validate
    | endpoint (one step of a multi-step form), an embed script and, when
    | "page" is on, a hosted page that renders the form on its own.
    |
    | "share_prefix" is where a private form's share links live
    | (/f/{token}); null turns them off.
    |
    | The middleware runs on the submit endpoint. Keep "web" for sites with a
    | session (CSRF protection and flashed errors); on a session-less site
    | drop it and rely on the honeypot, the time trap and the rate limit.
    |
    */

    'routes' => [
        'enabled' => true,
        'prefix' => 'forms',
        'middleware' => ['web'],
        'page' => true,
        'page_middleware' => ['web'],
        'page_layout' => 'packstub-form-builder::layout',
        'embed' => true,
        'share_prefix' => 'f',
    ],

    /*
    |--------------------------------------------------------------------------
    | Submissions
    |--------------------------------------------------------------------------
    |
    | "throttle" is a Laravel rate limit ("attempts,minutes") applied per IP
    | to the submit endpoint; null disables it. "store_ip" and
    | "store_user_agent" decide what the submission row records.
    | "retention_days" deletes submissions (and their files) older than that
    | many days when `form-builder:prune` runs (0 keeps everything); a form
    | can set its own in its settings.
    |
    */

    'submissions' => [
        'throttle' => '10,1',
        'store_ip' => true,
        'store_user_agent' => true,
        'queue_notifications' => true,
        'retention_days' => 0,
        'anonymize_after_days' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    |
    | Where the file upload field stores files: a disk and a directory under
    | it. Keep a private disk; the panel serves downloads through a signed
    | route. "max_kb" is the default size limit; "attach_max_kb" caps what
    | the notification email attaches.
    |
    */

    'uploads' => [
        'disk' => env('FORM_BUILDER_UPLOADS_DISK', 'local'),
        'directory' => 'form-builder',
        'max_kb' => 10240,
        'attach_max_kb' => 10240,
    ],

    /*
    |--------------------------------------------------------------------------
    | Spam protection
    |--------------------------------------------------------------------------
    |
    | The honeypot is a hidden input real users never fill; the time trap
    | rejects submissions posted faster than "min_seconds" after the form
    | was rendered (0 disables it). Both are silent: the visitor sees the
    | success state and nothing is stored. A token is single-use: the same
    | one cannot post twice ("token_ttl" is how long that is remembered, in
    | minutes). "allowed_origins" limits where the form may be posted from
    | (host names, wildcards allowed; empty = anywhere). The blocklist drops
    | submissions that contain a word, an email domain or come from an IP.
    |
    */

    'spam' => [
        'honeypot' => true,
        'honeypot_field' => '_fb_website',
        'min_seconds' => 2,
        'token_field' => '_fb_token',
        'token_ttl' => 120,
        'allowed_origins' => [],
        'blocklist' => [
            'words' => [],
            'email_domains' => [],
            'ips' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Captcha
    |--------------------------------------------------------------------------
    |
    | Cloudflare Turnstile, hCaptcha or Google reCAPTCHA v3, verified server
    | side. Fill the keys of the ones you use; each form picks a provider
    | in its spam settings ("default" applies to forms that do not).
    |
    */

    'captcha' => [
        'default' => env('FORM_BUILDER_CAPTCHA'),
        'turnstile' => [
            'site_key' => env('TURNSTILE_SITE_KEY'),
            'secret' => env('TURNSTILE_SECRET_KEY'),
        ],
        'hcaptcha' => [
            'site_key' => env('HCAPTCHA_SITE_KEY'),
            'secret' => env('HCAPTCHA_SECRET_KEY'),
        ],
        'recaptcha' => [
            'site_key' => env('RECAPTCHA_SITE_KEY'),
            'secret' => env('RECAPTCHA_SECRET_KEY'),
            'min_score' => 0.5,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Defaults for the emails a submission sends; every form can override
    | them in its Notifications settings. Null falls back to the mail
    | "from" of your app.
    |
    */

    'notifications' => [
        'from_email' => env('FORM_BUILDER_FROM_EMAIL'),
        'from_name' => env('FORM_BUILDER_FROM_NAME'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | A form can post every submission to a URL (Settings › Webhook). The
    | request is signed with the form's secret (Standard Webhooks headers).
    | Deliveries are queued, retried "attempts" times with a growing delay,
    | and logged for "keep_days" days.
    |
    */

    'webhooks' => [
        'queue' => true,
        'attempts' => 3,
        'timeout' => 15,
        'keep_days' => 30,
        'verify_ssl' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Formats
    |--------------------------------------------------------------------------
    |
    | How dates and times show in the panel, the emails and the exports.
    | Values are always stored as Y-m-d / Y-m-d H:i:s / H:i.
    |
    */

    'formats' => [
        'date' => 'Y-m-d',
        'datetime' => 'Y-m-d H:i',
        'time' => 'H:i',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sinks
    |--------------------------------------------------------------------------
    |
    | Classes implementing Packstub\FormBuilder\Contracts\SubmissionSink that
    | receive every accepted submission (a CRM, a webhook, a mailing list).
    | The built-in email notification always runs.
    |
    */

    'sinks' => [],

    /*
    |--------------------------------------------------------------------------
    | Frontend
    |--------------------------------------------------------------------------
    |
    | Blade renderer: "styles" inlines the package stylesheet with the first
    | rendered form; turn it off when your site ships its own. "enhance"
    | adds a small script that submits the form with fetch, renders errors
    | and the success message in place and drives the conditions and the
    | steps (the plain POST still works without it). "prefill" lets the
    | page URL's query parameters fill fields of the same key.
    |
    | Livewire renderer: "livewire_assets" puts Filament's colour variables,
    | scripts and the renderer's stylesheet on any page that renders
    | <livewire:form-builder> and does not print @filamentStyles /
    | @filamentScripts itself. "livewire_theme" is that stylesheet: null for
    | the package's compiled one (the Filament components the built-in
    | field types use, published by `filament:assets`), a path or URL for
    | another (say "css/filament/filament/app.css", the panel theme, when
    | custom field types render components the compiled one lacks), or
    | false to link none.
    |
    */

    'frontend' => [
        'styles' => true,
        'enhance' => true,
        'prefill' => true,
        'livewire_assets' => true,
        'livewire_theme' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenancy
    |--------------------------------------------------------------------------
    |
    | Scope forms to a tenant: every form gets the current tenant's key in
    | "column" when it is created, and queries only see the current tenant's
    | forms. The current tenant is Filament's (a panel with ->tenant()) or
    | what "resolver" (a callable) returns. "model" is the tenant model, for
    | the relationship. With a database per tenant (Filament Tenancy) leave
    | this off: each tenant has its own tables.
    |
    */

    'tenancy' => [
        'enabled' => false,
        'column' => 'tenant_id',
        'model' => null,
        'resolver' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Ownership
    |--------------------------------------------------------------------------
    |
    | Every form records the user who created it (user_id). With "only_own"
    | the Forms resource shows each user the forms they created; users who
    | pass the "see_all" gate ability (null = nobody) still see every form.
    |
    */

    'ownership' => [
        'only_own' => false,
        'see_all' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */

    'navigation' => [
        'group' => null,
        'icon' => 'heroicon-o-document-text',
        'sort' => null,
        'badge' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Gate
    |--------------------------------------------------------------------------
    |
    | An ability checked before the Forms resource is shown (null = everyone
    | who can open the panel). A policy on the Form model works as well.
    |
    */

    'gate' => null,

];

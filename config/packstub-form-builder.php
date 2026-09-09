<?php

use Packstub\FormBuilder\Fields\Types;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;

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
        Types\TextareaField::class,
        Types\SelectField::class,
        Types\RadioField::class,
        Types\CheckboxField::class,
        Types\CheckboxesField::class,
        Types\DateField::class,
        Types\HiddenField::class,
        Types\HeadingField::class,
        Types\ParagraphField::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Every form gets a POST endpoint (HTML or JSON, depending on the Accept
    | header), a JSON definition endpoint for headless clients and, when
    | "page" is on, a hosted page that renders the form on its own.
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
    ],

    /*
    |--------------------------------------------------------------------------
    | Submissions
    |--------------------------------------------------------------------------
    |
    | "throttle" is a Laravel rate limit ("attempts,minutes") applied per IP
    | to the submit endpoint; null disables it. "store_ip" and
    | "store_user_agent" decide what the submission row records.
    |
    */

    'submissions' => [
        'throttle' => '10,1',
        'store_ip' => true,
        'store_user_agent' => true,
        'queue_notifications' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Spam protection
    |--------------------------------------------------------------------------
    |
    | The honeypot is a hidden input real users never fill; the time trap
    | rejects submissions posted faster than "min_seconds" after the form
    | was rendered (0 disables it). Both are silent: the visitor sees the
    | success state and nothing is stored.
    |
    */

    'spam' => [
        'honeypot' => true,
        'honeypot_field' => '_fb_website',
        'min_seconds' => 2,
        'token_field' => '_fb_token',
    ],

    /*
    |--------------------------------------------------------------------------
    | Submission sinks
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
    | "styles" inlines the package stylesheet with the first rendered form;
    | turn it off when your site ships its own. "enhance" adds a small
    | script that submits the form with fetch and renders errors and the
    | success message in place (the plain POST still works without it).
    |
    */

    'frontend' => [
        'styles' => true,
        'enhance' => true,
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

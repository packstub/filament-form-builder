# Rendering

Three renderers share one pipeline: the same validation from the field definitions, the same spam checks, the same storage, events and notifications.

## Blade

```blade
<x-form-builder::form form="contact" />
```

`form` takes a slug, an id or a `Form` model. The component renders a plain `<form>` posting to the submit route, with the honeypot and the time-trap token as hidden inputs, and a CSRF field when the page has a session.

### Without JavaScript

The browser posts and is redirected back to the page (the URL of the page, sent as a hidden field, or the referer; other hosts are ignored). With a session, the success message, the errors and the old input are flashed. Without one they travel in the query string: `?fb_success=contact`, or `?fb_state=<encrypted errors and input>`. The redirect ends in `#form-contact` so the page scrolls to the form.

### With the enhancement script

By default the component inlines a small script (`frontend.enhance`) that submits with `fetch` and `Accept: application/json`, then shows the errors under their fields or replaces the form with the success message, without reloading. It dispatches a `form-builder:submitted` event on the wrapper. Forms added to the page later can be initialised with `document.dispatchEvent(new CustomEvent('form-builder:init'))`.

### Options

| Prop | Default | What it does |
| --- | --- | --- |
| `enhance` | config | Inline the fetch script |
| `styles` | config | Inline the stylesheet (once per page) |
| `action` | submit route | Override the endpoint |
| `return` | current URL | The page to come back to |
| `id`, `class` | `form-{slug}` | Wrapper id and extra classes |

### Session-less sites

The submit route runs the `web` middleware by default (CSRF, session). On a site whose public pages have no session — a cached CMS front end — set:

```php
'routes' => [
    'middleware' => [],
    'page_middleware' => [],
],
```

CSRF protection is then off for the endpoint; the honeypot, the time trap and the rate limit stay on.

## Livewire

```blade
<livewire:form-builder form="contact" />
```

The fields become Filament components (`TextInput`, `Select`, `CheckboxList`, `DatePicker`…), validated in place and submitted through the same pipeline. The page must load Filament's frontend assets:

```blade
<head>
    @filamentStyles
</head>
<body>
    <livewire:form-builder form="contact" />
    @filamentScripts
</body>
```

## JSON

```http
GET /forms/contact/definition
```

```json
{
  "name": "Contact",
  "slug": "contact",
  "accepting": true,
  "submit_url": "https://example.com/forms/contact",
  "fields": [
    { "key": "email", "type": "email", "input": true, "label": "Email", "required": true, "choices": null, "options": {} }
  ],
  "protection": {
    "token_field": "_fb_token",
    "token": "eyJpdiI6…",
    "honeypot_field": "_fb_website"
  }
}
```

Render the fields however you like, then post the values with the token under `token_field` and, if `honeypot_field` is set, that field empty:

```http
POST /forms/contact
Accept: application/json
Content-Type: application/json

{ "email": "ada@example.com", "message": "Hi", "_fb_token": "eyJpdiI6…" }
```

Success answers `200` with `{ "ok": true, "message": "…", "redirect": null, "id": 12 }`; validation errors answer `422` with `errors` keyed by field; a closed form answers `403`; the rate limit answers `429`.

## Hosted page

`/forms/{slug}` renders the form on its own in `routes.page_layout` (a Blade component receiving `title` and the form in its slot). Point it to your own layout component, or turn the page off with `routes.page`.

## Theming

The Blade renderer scopes its styles under `.fb-form` and reads CSS variables with fallbacks:

| Variable | Fallback |
| --- | --- |
| `--fb-color-text` | `#0f172a` |
| `--fb-color-muted` | `#475569` |
| `--fb-color-border` | `#cbd5e1` |
| `--fb-color-surface` | `#fff` |
| `--fb-color-primary` | `#2563eb` |
| `--fb-color-primary-contrast` | `#fff` |
| `--fb-color-danger` | `#dc2626` |
| `--fb-color-success` | `#15803d` |
| `--fb-radius` | `.5rem` |
| `--fb-font` | `inherit` |

Set them on `:root` or on a wrapper. To ship the CSS and JS yourself, publish them with `--tag=packstub-form-builder-assets` and turn `frontend.styles` / `frontend.enhance` off. The views publish with `--tag=packstub-form-builder-views`.

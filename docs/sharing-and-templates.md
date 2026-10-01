# Sharing and templates

## Share

**Share** on a form (list and edit page) opens the public link with copy and open, the availability window (**Opens at** / **Closes at**, saved from the dialog) and the iframe snippet.

A **private** form (Settings › Access › Visibility) is not served at `/forms/{slug}`, nor by the definition endpoint: only its **share links** open it. **Share** on a private form creates one, with a label ("Sent to the Acme team"), an optional expiry and an optional maximum number of submissions. Each link is a short address, `/f/{token}` (`routes.share_prefix`), and works in an iframe with `?embed=1`.

Every link is listed under **Share links** on the form's page with its address (click to copy), expiry, submissions and status, where **New link** adds one and **Revoke** closes one without touching the others. A revoked, expired or full link answers with "This link is no longer valid." (or the form's full message), and so does a submission posted through it. Submissions record the link they came through: a **Share link** column and filter in the submissions table, `share_link_id` in the webhook's `meta`.

From code:

```php
$link = $form->shareLinks()->create(['label' => 'Acme', 'expires_at' => now()->addWeek(), 'max_submissions' => 20]);
$link->url();      // https://example.com/f/k3v…
$link->revoke();
$link->status();   // active, expired, revoked or full
```

A private form takes submissions only from a render the server made: the form carries its link inside the protection token, so a revoked or expired link stops working even on a page left open. A link's maximum counts stored submissions, so it is offered only when the form stores them.

A headless client reads a private form's definition with `?link={token}` and posts back the protection token it received (`protection.token`, which holds the link) and the link as `_fb_link`.

The signed links of 1.2 (`$form->shareUrl()`, `$form->shareUrl(now()->addDays(7))`) keep working throughout 1.x.

## Password

A form with a **Password** shows a prompt first, in every renderer and on the hosted page. Once typed, the browser keeps an encrypted key in the session (or in the `fb_key` query parameter on a session-less site) and the form submits with it. JSON clients post the password to `POST /forms/{slug}/unlock` and send the returned `key` as `_fb_key`.

## Templates

**Use a template** on the Forms list creates a form from one of the twenty built-in templates (contact, demo request, newsletter, event registration, feedback and surveys, job application, support ticket, client onboarding, patient intake, catering order…), some with sections and conditions, ready to edit.

Offer your own: put files like the built-in ones (`resources/templates/*.php` in the package, each returning `name`, `category`, `description` and a portable `form` array) in a directory and register it:

```php
use Packstub\FormBuilder\Templates\Templates;

Templates::add(resource_path('form-templates'));
```

A portable form array is what **Export JSON** produces and `Form::fromArray()` reads: `name`, `description`, `fields`, `settings` and the other columns except the id, slug and dates.

# Submissions

Every accepted submission is stored (unless the form's **Store submissions** is off) with:

| Column | Content |
| --- | --- |
| `number` | The sequential number within the form (`#42`) |
| `data` | The values, keyed by field key, normalised by the field type; hidden and conditionally hidden fields as `null` |
| `fields` | The label and type of every field at submission time |
| `user_id` | The logged-in user, when there was one |
| `ip`, `user_agent` | Optional (`submissions.store_ip`, `store_user_agent`) |
| `fingerprint` | The visitor's anonymous id, for "one per person" |
| `source_url` | The page the form was on |
| `channel` | `web`, `json`, `livewire` or `code` |
| `read_at` | Set when someone opens it in the panel |

## In the panel

The **Submissions** relation manager on the form's edit page lists them newest first: the number, the date, a summary, and **one column per field** (the first two shown, the rest toggleable), each searchable and sortable. Filters: unread, one per field of a choice, boolean or date type, and the share link on a form that has some. Opening one shows every value (copyable; uploaded files as download links; rich text rendered) and the details, and marks it read. **Edit** changes the values in a slide-over with the same Filament components as the Livewire renderer. Bulk actions mark as read, export or delete.

![The Submissions table under a form: unread envelopes, received date, a summary of the values, the page, and the Export CSV action](https://raw.githubusercontent.com/packstub/art/main/filament-form-builder/docs/submissions.png)

![A submission opened in a slide-over: every value with its label, copyable, and a collapsed details section](https://raw.githubusercontent.com/packstub/art/main/filament-form-builder/docs/submission.png)

**Export CSV** downloads the filtered list: the number, the id, the date, the form's fields (hidden ones included; an address gets a column per part), then any key an older submission still has, then the page, IP and user. **Export Excel** appears when [OpenSpout](https://github.com/openspout/openspout) is installed (Filament's export action brings it; otherwise `composer require openspout/openspout`).

Once a form has submissions, its edit page opens with **Submissions per day**, a bar chart over the last 7, 30 or 90 days with the totals (all time, the last 7 days, unread) above it. It counts the submissions table: no tracking script.

The Forms navigation item shows the unread count (`navigationBadge(false)` to hide it).

## Files

Uploads live on `uploads.disk` (`local` by default: private) under `uploads.directory/{form id}/`, named `{ulid}__{original-name}.{ext}`. The panel links them through a signed route (`GET /forms/files/{submission}/{field}/{index}`); nothing has a guessable address. Deleting a submission deletes its files. The JSON API takes files as `{ "name": "brief.pdf", "data": "data:application/pdf;base64,…" }` items.

## Retention

Schedule `form-builder:prune` daily:

```php
Schedule::command('form-builder:prune')->daily();
```

It deletes submissions (and their files) older than the form's **Keep submissions for** or `submissions.retention_days` (0 keeps everything), strips the IP address and user agent from those older than `submissions.anonymize_after_days`, and drops webhook deliveries older than `webhooks.keep_days`. `--dry-run` reports without deleting.

## Notifications and webhooks

See [Notifications and webhooks](notifications.md).

## Events and sinks

`Packstub\FormBuilder\Events\SubmissionReceived` is dispatched with the form, the submission and the context; `SpamDetected` when a submission is dropped.

A sink receives every accepted submission:

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

Register sinks in the config (`sinks`) or at runtime (`FormBuilder::sink(PushToCrm::class)`). When the form does not store submissions, the sink receives an unsaved model with the form relation loaded. `$submission->toPayload()` gives the same array the webhook posts.

## From code

```php
use Packstub\FormBuilder\Facades\FormBuilder;

$result = FormBuilder::submit('contact', ['email' => 'ada@example.com', 'message' => 'Hi']);
$result->submission; // FormSubmission
```

Submissions from code skip the spam checks, the password and the captcha; validation, conditions and limits still apply and validation throws `ValidationException`.

# Submissions

Every accepted submission is stored (unless the form's **Store submissions** is off) with:

| Column | Content |
| --- | --- |
| `data` | The values, keyed by field key, normalised by the field type |
| `fields` | The label and type of every field at submission time |
| `user_id` | The logged-in user, when there was one |
| `ip`, `user_agent` | Optional (`submissions.store_ip`, `store_user_agent`) |
| `source_url` | The page the form was on |
| `channel` | `web`, `json`, `livewire` or `code` |
| `read_at` | Set when someone opens it in the panel |

## In the panel

The **Submissions** relation manager on the form's edit page lists them newest first with a summary of the first values, the page and the channel. Opening one shows every value (copyable) and the details, and marks it read. A filter shows unread ones; bulk actions mark as read, export or delete. **Export CSV** downloads the filtered list: the form's current fields first, then any key an older submission still has, then the page, IP and user.

The Forms navigation item shows the unread count (`navigationBadge(false)` to hide it).

## Notifications

Addresses in **Notify by email** each get one email per submission, listing the values and linking to the panel. Emails are queued when `submissions.queue_notifications` is on and a queue is configured.

## Events and sinks

`Packstub\FormBuilder\Events\SubmissionReceived` is dispatched with the form, the submission and the context; `SpamDetected` when a submission is dropped.

A sink receives every accepted submission:

```php
use Packstub\FormBuilder\Contracts\SubmissionSink;
use Packstub\FormBuilder\Models\FormSubmission;

class PostToWebhook implements SubmissionSink
{
    public function handle(FormSubmission $submission): void
    {
        Http::post(config('services.hooks.forms'), [
            'form' => $submission->form->slug,
            'values' => $submission->data,
            'page' => $submission->source_url,
        ]);
    }
}
```

Register sinks in the config (`sinks`) or at runtime (`FormBuilder::sink(PostToWebhook::class)`). When the form does not store submissions, the sink receives an unsaved model with the form relation loaded.

## From code

```php
use Packstub\FormBuilder\Facades\FormBuilder;

$result = FormBuilder::submit('contact', ['email' => 'ada@example.com', 'message' => 'Hi']);
$result->submission; // FormSubmission
```

Submissions from code skip the honeypot and time-trap checks; validation still runs and throws `ValidationException`.

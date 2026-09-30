# Notifications and webhooks

Everything on the **Notifications** tab of a form. Emails are queued when `submissions.queue_notifications` is on and a queue is configured.

## Email to the team

Addresses in **Notify by email** each get one email per submission, listing the values and linking to the panel.

| Setting | What it does |
| --- | --- |
| Subject | Takes tags: `{form_name}`, `{submission_number}`, `{submission_id}`, `{date}`, `{domain}`, and `{field_key}` for any value |
| From address, from name | Override `notifications.from_email` / `from_name` (which override the app's mail from) |
| Reply-to | An address, or `respondent` for the email the visitor typed |
| CC, BCC | More addresses |
| Attach uploaded files | Adds the files of the submission, up to `uploads.attach_max_kb` in total |

## Confirmation to the visitor

Turn on **Send a confirmation to the visitor**, pick the email field (or let the first email field with a value be used) and write the subject and the message. The message is Markdown and takes the same tags as `{{ field_key }}` or `{field_key}`; the submitted values can be appended as a table.

## In the panel

Pick users under **Notify in the panel** and each gets a Filament database notification with a *View* link for every submission. The app needs Filament's [database notifications](https://filamentphp.com/docs/notifications/database-notifications) set up (the `notifications` table and the panel's `databaseNotifications()`).

## Chat channels

Add channels under **Chat channels** and every submission posts a message to each: the form, the submission number, the first ten values and a button to the submission in the panel.

| App | URL to paste |
| --- | --- |
| Slack | An [incoming webhook](https://api.slack.com/messaging/webhooks) URL (`https://hooks.slack.com/services/…`) |
| Discord | A channel webhook URL (Channel settings › Integrations › Webhooks) |
| Microsoft Teams | A workflow's *When a Teams webhook request is received* URL; the message is an Adaptive Card |

Messages go through the same queue, retries and log as the webhook (**Deliveries** on the form's page, with the app in the *Sent to* column).

## Branding

With a **Logo** and a **Brand colour** on the Design tab, both emails carry the logo in the header and the colour on the button and the links, on top of the app's mail theme. Without them the emails look as before. Publish the views (`packstub-form-builder-views`) to change the layout further.

## Webhook

Set a **Webhook URL** and every submission is posted there as JSON:

```json
{
  "type": "submission.received",
  "timestamp": "2026-09-25T10:00:00+00:00",
  "data": {
    "id": 12,
    "number": 12,
    "form": { "id": 1, "slug": "contact", "name": "Contact" },
    "submitted_at": "2026-09-25T10:00:00+00:00",
    "data": { "email": "ada@example.com", "message": "Hi" },
    "labels": { "email": "Email", "message": "Message" },
    "meta": { "ip": "203.0.113.5", "user_agent": "…", "source_url": "https://example.com/contact", "channel": "web" }
  }
}
```

| Setting | What it does |
| --- | --- |
| Method | `POST`, `PUT` or `PATCH` |
| Signing secret | Generated when the URL is set; the request carries [Standard Webhooks](https://www.standardwebhooks.com) headers: `webhook-id`, `webhook-timestamp` and `webhook-signature` (`v1,` + base64 HMAC-SHA256 of `{id}.{timestamp}.{body}`) |
| Fields to send | A subset of the values (empty sends every field) |
| Include the visitor details | The `meta` block |
| Extra headers | Sent with every request |

Deliveries are queued (`webhooks.queue`), retried with a growing delay (1, 5, 25 minutes…) up to `webhooks.attempts` times, and listed under **Deliveries** on the form's page with their status, attempts and response, where a failed one can be retried. `form-builder:prune` drops deliveries older than `webhooks.keep_days`.

Make, n8n, Zapier's *Webhooks by Zapier* and any endpoint of your own take this payload as is.

## Events and sinks

`Packstub\FormBuilder\Events\SubmissionReceived` is dispatched with the form, the submission and the context; `SpamDetected` when a submission is dropped. A `SubmissionSink` class receives every accepted submission; see [Submissions](submissions.md#events-and-sinks).

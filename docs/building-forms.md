# Building forms

Open **Forms**, create one, and add fields from the block picker on the **Fields** tab. Blocks can be reordered, collapsed, cloned and deleted.

![The Forms resource: submission and unread counts, active state, an Open page action](https://raw.githubusercontent.com/packstub/art/main/filament-form-builder/docs/forms-list.png)

![The Fields tab: one collapsible block per field, the Message block open with its settings](https://raw.githubusercontent.com/packstub/art/main/filament-form-builder/docs/builder.png)

## Field types

![The block picker with the fourteen built-in field types](https://raw.githubusercontent.com/packstub/art/main/filament-form-builder/docs/field-picker.png)

| Type | Value | Own settings |
| --- | --- | --- |
| Text | string | Minimum and maximum length |
| Email | lowercased string | Length; validated as an email |
| Phone | string | — |
| URL | string | Validated as a URL |
| Number | int or float | Minimum, maximum, step |
| Long text | string | Rows, maximum length |
| Dropdown | one choice | Choices (value → label) |
| Radio buttons | one choice | Choices |
| Checkbox | true / false | Required means it must be ticked |
| Checkbox list | list of choices | Choices |
| Date | `Y-m-d` string | Earliest and latest date |
| Hidden | string | Value |
| Heading | — | Level (H2–H4) |
| Paragraph | — | Text |

Every input field also has a label, a **key** (derived from the label; the name of the value in submissions, exports and the JSON API), a placeholder, help text, a default value, a required flag, a width (full or half of the row) and **extra validation rules**: any Laravel rule, one per tag, such as `max:100`, `starts_with:+`, `regex:/^[A-Z]/`.

Keys must be unique within a form; the builder refuses duplicates and the model suffixes a missing one.

## Settings

![The Settings tab: general, after submit, notifications, availability and spam protection sections](https://raw.githubusercontent.com/packstub/art/main/filament-form-builder/docs/settings.png)

| Setting | What it does |
| --- | --- |
| Name, slug | The slug is the form's URL and embed name |
| Active | Off: the form renders as closed and rejects submissions |
| Submit button | Label of the button |
| Success message | Shown after a submission, unless a redirect is set |
| Redirect after submit | A URL to send the visitor to |
| Notify by email | One email per submission to each address |
| Store submissions | Off: submissions are only emailed and passed to sinks |
| Opens at, closes at | Outside the window the form is closed |
| Require a logged-in user | Rejects anonymous submissions |
| Honeypot, minimum seconds | Per-form [spam settings](spam-protection.md) |

## Embed

The **Embed** tab shows, for the form being edited, the Blade tag, the Livewire tag, the hosted page URL and the JSON endpoints, each copyable.

![The Embed tab with the four copyable snippets](https://raw.githubusercontent.com/packstub/art/main/filament-form-builder/docs/embed.png)

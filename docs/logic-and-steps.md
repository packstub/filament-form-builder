# Logic and steps

## Conditions

A field is **always visible**, **shown when** or **hidden when** a group of conditions holds. Each condition compares another field's value with an operator:

| Operator | Matches when |
| --- | --- |
| equal to, not equal to | The value is (not) the given one; text compares case-insensitively, numbers numerically, checkboxes against `true` / `false` |
| containing, not containing | The text contains the given one, or the list (checkbox list, multi-select, tags) holds it |
| greater than, less than | Numbers only |
| empty, not empty | Nothing typed, nothing picked, unchecked |

**Match** decides whether all conditions or any of them must hold. A field can only look at input fields; a condition on a field that is itself hidden never holds, so dependants of a hidden field hide with it.

A required field can be required **always**, **only when** or **except when** its own group of conditions holds.

The same groups apply to a **section**: a hidden section hides its fields; on a multi-step form the step is skipped.

Conditions run in three places with the same rules:

- in the browser, live, by the Blade renderer's script and by the Livewire renderer;
- on the server, at submission: a hidden field is not validated and is stored as `null`, a conditional requirement is resolved against the submitted values;
- in the JSON definition, under `visibility` and `requirement` on each field and `visibility` on each section, for headless clients.

Without JavaScript every field shows; the server still applies the conditions.

## Multi-step forms

Put the fields in sections and switch **Display** to *Multi-step* on the **Design** tab. Every section becomes a step: a card with the step number, a progress bar and *Next* / *Back* buttons (labels of your own). Each step validates its own fields before the visitor moves on, through `POST /forms/{slug}/validate` in the Blade renderer and in place in the Livewire one (Filament's wizard). The visitor can go back unless **Let visitors go back** is off. Fields outside any section form the first step.

Without JavaScript the Blade renderer shows every section on one page and the submit button; the form still works.

## Design

| Setting | What it does |
| --- | --- |
| Display | Single page, or multi-step |
| Labels | Above the fields, or beside them |
| Progress bar, step numbers, back navigation, button labels | The multi-step controls |
| Page title, meta description, social image, logo | The hosted page's `<head>` and header |
| Brand colour | Sets `--fb-color-primary` on the form |
| Custom CSS | Scoped to the form; use the `.fb-form` classes and the `--fb-*` variables |
| Custom JavaScript | Runs once the form is on the page, with `form` as the wrapper element |

The custom CSS and JavaScript run on the hosted page and wherever the Blade or Livewire component renders the form. In a panel your customers sign in to, anyone who can edit a form can run code on those pages: turn the fields off for every form with `frontend.custom_js` (and `frontend.custom_css`) set to `false`. With `tenancy.enabled`, custom JavaScript is off unless you set `frontend.custom_js` to `true`. Code a form has already stored, or that arrives with an imported form, is then not printed either, and saving a form in the editor drops it.

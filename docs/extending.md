# Extending

## Field types

A field type is a class extending `Packstub\FormBuilder\Fields\FieldType`:

| Method | Purpose |
| --- | --- |
| `id()` | The short id stored with every field |
| `label()`, `icon()` | Shown in the block picker (label from `types.{id}` in the language file) |
| `isInput()` | `false` for layout-only types |
| `hasChoices()`, `acceptsMultiple()`, `choices(Field $field)` | Choice lists, list values, computed choices |
| `hasPlaceholder()`, `hasDefault()` | Which common settings the builder shows |
| `ruleCategory()` | Which rules the picker offers: `text`, `number`, `date`, `choice`, `multiple`, `file`, `boolean` or `null` |
| `editorSchema()` | Extra settings shown in the builder, as Filament components |
| `rules(Field $field)`, `elementRules(Field $field)` | Validation rules (required / nullable are added for you) |
| `prepare(mixed $value, Field $field)` | Shape the raw input before validation (split a string into a list, decode a base64 file) |
| `comparableValue(mixed $value, Field $field)` | The raw value as the conditions compare it |
| `normalize(mixed $value, Field $field)` | The stored value |
| `format(mixed $value, Field $field)` | The value as text (tables, emails, CSV) |
| `display(mixed $value, Field $field)` | The value in the details view; return an `HtmlString` for HTML |
| `tableColumn(Field $field)`, `tableFilter(Field $field)` | A column and a filter for the submissions table, or `null` for the defaults |
| `detailEntry(Field $field)` | An infolist entry for the submission details (its state is `data.<key>`), or `null` for the text of `display()` |
| `nestedRules(Field $field, bool $required)`, `nestedAttributes(Field $field)` | Rules and names for the parts of a value stored as an object, validated as `key.part` (the address type) |
| `exportColumns(Field $field)`, `exportValue(mixed $value, Field $field, string $column)` | Split the value into several export columns |
| `definition(Field $field)` | Extra keys for the field in the JSON definition |
| `view()` | The Blade view of the plain renderer (receives `field`, `inputId`, `error`, `value`) |
| `formComponent(Field $field)` | The Filament component of the Livewire renderer |

Extend a built-in when it is close to what you need:

```php
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\Types\NumberField;

class ScoreField extends NumberField
{
    public static function id(): string
    {
        return 'score';
    }

    public function icon(): string
    {
        return 'heroicon-o-star';
    }

    public function rules(Field $field): array
    {
        return ['integer', 'between:1,100'];
    }
}
```

Register it in `field_types`, with `FormBuilderPlugin::make()->fieldTypes([ScoreField::class])`, or with `FormBuilder::registerFieldTypes([...])`. Hide built-ins with `withoutFieldTypes([TextField::class, 'url'])`. A type that renders a Filament component the compiled Livewire stylesheet lacks needs `frontend.livewire_theme` pointed at a fuller theme (see [Rendering](rendering.md#livewire)).

## Choice sources

Choice fields can take their options from `FormBuilder::choices($name, $source, $label)` or config `choice_sources`; see [Building forms](building-forms.md#choices-from-your-data). A class source implements `Packstub\FormBuilder\Contracts\ChoiceSource` (`label()` and `choices(Field $field)`).

## Templates

Offer your own templates next to the built-in ones with `Templates::add($directory)`; see [Sharing and templates](sharing-and-templates.md#templates).

## Models and tables

Point `models.form` / `models.submission` / `models.webhook_delivery` / `models.share_link` to your subclasses for extra columns, scopes or relationships, and `tables.*` to other table names before migrating.

## Tenancy

With `tenancy.enabled`, forms carry the current tenant's key in `tenancy.column` (`tenant_id`): the one Filament resolves in a panel with `->tenant()`, or what the `tenancy.resolver` callable returns. Queries see the current tenant's forms only (outside a tenant, the public routes see every form) and new forms get the key on creation; `$form->tenant()` is a `BelongsTo` to `tenancy.model`. With a database per tenant ([Filament Tenancy](https://packstub.dev/docs/filament-tenancy)) leave it off: each tenant has its own tables. To limit forms or submissions per plan, gate the resource with [Filament Features](https://packstub.dev/docs/filament-features) in `authorize()`.

## Ownership

Every form records the user who created it (`user_id`, `$form->owner`); the Forms list has a hidden **Created by** column and a **My forms** filter. With `ownership.only_own` each user sees and edits only their own forms, unless they pass the gate ability named in `ownership.see_all`:

```php
// config/packstub-form-builder.php
'ownership' => ['only_own' => true, 'see_all' => 'manage-all-forms'],

// AppServiceProvider::boot()
Gate::define('manage-all-forms', fn (User $user): bool => $user->is_admin);
```

The public routes are not affected.

## Resource

`FormBuilderPlugin::make()->resource(MyFormResource::class)` swaps the resource (extend `FormResource`); `withoutResource()` skips it. `authorize(fn () => auth()->user()->isEditor())` and the `gate` config key restrict who sees it; a policy on the `Form` model works as well.

## Embedding in another package

A package that wants to offer forms as a block or a widget can:

- render with the Blade component, passing its own `return` URL and `:styles="false"` to inherit the host's CSS variables, or a portable array instead of a slug;
- read the definition with `$form->toDefinition()`, the sections with `$form->sections()` and the fields with `$form->fieldList()`;
- resolve the conditions for a set of values with `$form->visibleKeys($values)`;
- submit with `app(Submitter::class)->submit($form, $input, SubmissionContext::fromRequest($request, 'my-package'))`;
- list forms for a picker with `FormBuilder::formModel()::query()->where('is_active', true)->pluck('name', 'slug')`.

## Testing

`FormBuilder::fake()` keeps submissions validating and storing but skips the emails, panel notifications, webhooks, channel messages and sinks, and records what came in:

```php
use Packstub\FormBuilder\Facades\FormBuilder;

FormBuilder::fake();

$this->post('/forms/contact', FormBuilder::validInput('contact', ['email' => 'ada@example.com', 'message' => 'Hi']));

FormBuilder::assertSubmitted('contact', fn ($submission, array $data) => $data['email'] === 'ada@example.com')
    ->assertSubmittedCount('contact', 1)
    ->assertNotSubmitted('newsletter');

FormBuilder::assertNothingSubmitted();          // nothing at all
FormBuilder::assertSpamDetected('contact', 'honeypot');
FormBuilder::submitted('contact');              // the recorded submissions
```

`FormBuilder::validInput($form, $values)` adds the protection token so a test post passes the time trap and the token check (set `spam.min_seconds` to `0` in tests).


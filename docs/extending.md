# Extending

## Field types

A field type is a class extending `Packstub\FormBuilder\Fields\FieldType`:

| Method | Purpose |
| --- | --- |
| `id()` | The short id stored with every field |
| `label()`, `icon()` | Shown in the block picker (label from `types.{id}` in the language file) |
| `isInput()` | `false` for layout-only types |
| `hasChoices()`, `acceptsMultiple()` | Choice lists, list values |
| `editorSchema()` | Extra settings shown in the builder, as Filament components |
| `rules(Field $field)`, `elementRules(Field $field)` | Validation rules (required / nullable are added for you) |
| `normalize(mixed $value, Field $field)` | The stored value |
| `format(mixed $value, Field $field)` | The value as text (tables, emails, CSV) |
| `view()` | The Blade view of the plain renderer (receives `field`, `inputId`, `error`, `value`) |
| `formComponent(Field $field)` | The Filament component of the Livewire renderer |

Extend a built-in when it is close to what you need:

```php
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\Types\NumberField;

class RatingField extends NumberField
{
    public static function id(): string
    {
        return 'rating';
    }

    public function icon(): string
    {
        return 'heroicon-o-star';
    }

    public function rules(Field $field): array
    {
        return ['integer', 'between:1,5'];
    }
}
```

Register it in `field_types`, with `FormBuilderPlugin::make()->fieldTypes([RatingField::class])`, or with `FormBuilder::registerFieldTypes([...])`. Hide built-ins with `withoutFieldTypes([TextField::class, 'url'])`.

## Models and tables

Point `models.form` / `models.submission` to your subclasses for extra columns, scopes or relationships, and `tables.*` to other table names before migrating.

## Resource

`FormBuilderPlugin::make()->resource(MyFormResource::class)` swaps the resource (extend `FormResource`); `withoutResource()` skips it. `authorize(fn () => auth()->user()->isEditor())` and the `gate` config key restrict who sees it; a policy on the `Form` model works as well.

## Embedding in another package

A package that wants to offer forms as a block or a widget can:

- render with the Blade component, passing its own `return` URL and `:styles="false"` to inherit the host's CSS variables;
- read the definition with `$form->toDefinition()` and the fields with `$form->fieldList()`;
- submit with `app(Submitter::class)->submit($form, $input, SubmissionContext::fromRequest($request, 'my-package'))`;
- list forms for a picker with `FormBuilder::formModel()::query()->where('is_active', true)->pluck('name', 'slug')`.

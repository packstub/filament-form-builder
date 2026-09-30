<?php

namespace Packstub\FormBuilder\Fields;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;

/**
 * A kind of field the builder offers. Subclass it to add your own: give it
 * an id, an editor schema for its settings, validation rules, a Blade view
 * for the plain renderer and a Filament component for the Livewire one.
 */
abstract class FieldType
{
    /**
     * The short, stable id stored with every field ("text", "email"...).
     */
    abstract public static function id(): string;

    public function label(): string
    {
        $key = 'packstub-form-builder::form-builder.types.'.static::id();
        $label = __($key);

        return $label === $key ? Str::headline(static::id()) : $label;
    }

    public function icon(): string
    {
        return 'heroicon-o-pencil-square';
    }

    /**
     * Whether the field collects a value (false for headings, paragraphs...).
     */
    public function isInput(): bool
    {
        return true;
    }

    /**
     * Whether the field has a list of choices (select, radio, checkboxes).
     */
    public function hasChoices(): bool
    {
        return false;
    }

    /**
     * The value => label choices of a field of this type: the choice source
     * picked in the builder, else the list typed there (a type may compute
     * them).
     *
     * @return array<string, string>
     */
    public function choices(Field $field): array
    {
        $source = $field->option('choices_source');

        if (is_string($source) && $source !== '') {
            return app(ChoiceSources::class)->resolve($source, $field);
        }

        return $field->storedChoices();
    }

    /**
     * Whether the submitted value is a list.
     */
    public function acceptsMultiple(): bool
    {
        return false;
    }

    /**
     * The kind of value the field takes, which decides the validation rules
     * the builder offers (ValidationRules::CATEGORY_*); null for none.
     */
    public function ruleCategory(): ?string
    {
        if (! $this->isInput()) {
            return null;
        }

        if ($this->acceptsMultiple()) {
            return ValidationRules::CATEGORY_MULTIPLE;
        }

        return $this->hasChoices() ? ValidationRules::CATEGORY_CHOICE : ValidationRules::CATEGORY_TEXT;
    }

    /**
     * Whether the builder offers the placeholder setting.
     */
    public function hasPlaceholder(): bool
    {
        return $this->hasCommonSettings();
    }

    /**
     * Whether the builder offers the default value setting.
     */
    public function hasDefault(): bool
    {
        return $this->hasCommonSettings();
    }

    /**
     * Whether the value can be prefilled from the page URL and compared in conditions.
     */
    public function isComparable(): bool
    {
        return $this->isInput();
    }

    /**
     * Whether the builder shows the placeholder, hint, default and required settings.
     */
    public function hasCommonSettings(): bool
    {
        return $this->isInput();
    }

    /**
     * Extra settings for this type, shown in the builder after the common ones.
     *
     * @return array<int, Component>
     */
    public function editorSchema(): array
    {
        return [];
    }

    /**
     * Validation rules for the type (required / nullable are added for you).
     *
     * @return array<int, mixed>
     */
    public function rules(Field $field): array
    {
        return [];
    }

    /**
     * Validation rules for each element of a multiple-value field ("key.*").
     *
     * @return array<int, mixed>
     */
    public function elementRules(Field $field): array
    {
        return [];
    }

    /**
     * Rules for the parts of a value stored as an object (an address), keyed
     * by part: validated as "key.part". $required is the field's resolved
     * requirement.
     *
     * @return array<string, array<int, mixed>>
     */
    public function nestedRules(Field $field, bool $required): array
    {
        return [];
    }

    /**
     * The names validation messages use for the parts, keyed by part.
     *
     * @return array<string, string>
     */
    public function nestedAttributes(Field $field): array
    {
        return [];
    }

    /**
     * The columns the exports split the value into, part => heading; empty
     * for one column.
     *
     * @return array<string, string>
     */
    public function exportColumns(Field $field): array
    {
        return [];
    }

    /**
     * One export column of a split value (see exportColumns()).
     */
    public function exportValue(mixed $value, Field $field, string $column): string
    {
        return '';
    }

    /**
     * Extra keys for the field in the JSON definition (the embed and headless
     * clients read them).
     *
     * @return array<string, mixed>
     */
    public function definition(Field $field): array
    {
        return [];
    }

    /**
     * Shape the raw input before validation (a file type turns base64
     * payloads into files). Most types leave it alone.
     */
    public function prepare(mixed $value, Field $field): mixed
    {
        return $value;
    }

    /**
     * Turn the raw submitted value into what gets stored.
     */
    public function normalize(mixed $value, Field $field): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return $value;
    }

    /**
     * The raw submitted value as the conditions compare it, before
     * validation: trimmed strings, lists as arrays, booleans as booleans.
     */
    public function comparableValue(mixed $value, Field $field): mixed
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return $value;
    }

    /**
     * The submitted value as text (tables, emails, CSV).
     */
    public function format(mixed $value, Field $field): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_array($value)) {
            $choices = $field->choices();

            return implode(', ', array_map(
                fn ($item): string => (string) ($choices[(string) $item] ?? $item),
                $value,
            ));
        }

        if (is_bool($value)) {
            return $value ? __('packstub-form-builder::form-builder.values.yes') : __('packstub-form-builder::form-builder.values.no');
        }

        if ($field->type->hasChoices()) {
            return (string) ($field->choices()[(string) $value] ?? $value);
        }

        return (string) $value;
    }

    /**
     * A table column for the submissions table, or null for the default
     * text column.
     */
    public function tableColumn(Field $field): ?Column
    {
        return null;
    }

    /**
     * A filter for the submissions table, or null for none.
     */
    public function tableFilter(Field $field): ?BaseFilter
    {
        return null;
    }

    /**
     * The submitted value as HTML for the details view (plain text by default).
     */
    public function display(mixed $value, Field $field): string|Htmlable
    {
        return $this->format($value, $field);
    }

    /**
     * The Blade view of the plain renderer.
     */
    public function view(): string
    {
        return 'packstub-form-builder::fields.'.static::id();
    }

    /**
     * The Filament component of the Livewire renderer.
     */
    public function formComponent(Field $field): Component
    {
        return $this->configure(TextInput::make($field->key), $field);
    }

    /**
     * Apply the common settings to a Filament component.
     */
    protected function configure(Component $component, Field $field): Component
    {
        $component->label($field->label);

        if (method_exists($component, 'placeholder') && $field->placeholder !== null) {
            $component->placeholder($field->placeholder);
        }

        if (method_exists($component, 'helperText') && $field->hint !== null) {
            $component->helperText($field->hint);
        }

        if (method_exists($component, 'default') && $field->default !== null) {
            $component->default($field->default);
        }

        if (method_exists($component, 'columnSpan')) {
            $component->columnSpan($field->columns());
        }

        if (method_exists($component, 'rules')) {
            $component->rules($field->rules());
        }

        if (method_exists($component, 'validationMessages') && $field->message !== null) {
            $component->validationMessages(array_fill_keys(array_map(fn (string $key): string => substr($key, strlen($field->key) + 1), array_keys($field->messages())), $field->message));
        }

        return $component;
    }
}

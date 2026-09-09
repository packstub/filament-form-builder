<?php

namespace Packstub\FormBuilder\Fields;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
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
     * Whether the submitted value is a list.
     */
    public function acceptsMultiple(): bool
    {
        return false;
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
            $component->columnSpan($field->width === 'half' ? 1 : 2);
        }

        if (method_exists($component, 'rules')) {
            $component->rules($field->rules());
        }

        return $component;
    }
}

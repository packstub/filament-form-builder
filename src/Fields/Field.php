<?php

namespace Packstub\FormBuilder\Fields;

use Illuminate\Support\Str;

/**
 * One field of a form, as stored in the form's "fields" JSON and resolved
 * against the field type registry.
 */
final class Field
{
    /**
     * The builder keys with a meaning of their own; everything else in the
     * data is a type-specific option.
     */
    public const RESERVED = [
        'key', 'label', 'placeholder', 'hint', 'required', 'default', 'rules', 'width', 'hidden', 'message', 'validation',
        'visibility', 'visibility_logic', 'visibility_rules',
        'requirement', 'requirement_logic', 'requirement_rules',
    ];

    /**
     * @param  array<string, mixed>  $options  Type-specific settings (choices, min, max, rows...).
     * @param  array<int, string>  $rules  Extra Laravel validation rules typed in the builder.
     * @param  array<int, array{rule: string, value: mixed}>  $validation  Rules picked from the list.
     * @param  array<string, mixed>  $data  The raw builder data, for anything a custom type stores.
     */
    public function __construct(
        public readonly string $key,
        public readonly FieldType $type,
        public readonly string $label,
        public readonly ?string $placeholder = null,
        public readonly ?string $hint = null,
        public readonly bool $required = false,
        public readonly mixed $default = null,
        public readonly array $options = [],
        public readonly array $rules = [],
        public readonly string $width = 'full',
        public readonly array $data = [],
        public readonly bool $hidden = false,
        public readonly ?Conditions $visibility = null,
        public readonly ?Conditions $requirement = null,
        public readonly array $validation = [],
        public readonly ?string $message = null,
        public readonly ?string $section = null,
    ) {}

    /**
     * Build a field from one builder item: ['type' => 'text', 'data' => [...]].
     *
     * @param  array<string, mixed>  $item
     */
    public static function fromArray(array $item, FieldTypeRegistry $registry, ?string $section = null): ?self
    {
        $type = $registry->find((string) ($item['type'] ?? ''));

        if ($type === null) {
            return null;
        }

        $data = is_array($item['data'] ?? null) ? $item['data'] : [];
        $label = trim((string) ($data['label'] ?? ''));
        $key = self::normalizeKey((string) ($data['key'] ?? ''), $label, $type);

        if ($key === '') {
            return null;
        }

        return new self(
            key: $key,
            type: $type,
            label: $label !== '' ? $label : $type->label(),
            placeholder: self::nullableString($data['placeholder'] ?? null),
            hint: self::nullableString($data['hint'] ?? null),
            required: $type->isInput() && (bool) ($data['required'] ?? false),
            default: $data['default'] ?? null,
            options: array_diff_key($data, array_flip(self::RESERVED)),
            rules: self::normalizeRules($data['rules'] ?? []),
            width: self::normalizeWidth($data['width'] ?? null),
            data: $data,
            hidden: (bool) ($data['hidden'] ?? false),
            visibility: Conditions::fromData($data, 'visibility'),
            requirement: Conditions::fromData($data, 'requirement'),
            validation: self::normalizeValidation($data['validation'] ?? []),
            message: self::nullableString($data['message'] ?? null),
            section: $section,
        );
    }

    public static function normalizeKey(string $key, string $label, FieldType $type): string
    {
        $key = Str::slug($key !== '' ? $key : $label, '_');

        if ($key === '' && ! $type->isInput()) {
            $key = $type::id();
        }

        return $key;
    }

    public static function normalizeWidth(mixed $width): string
    {
        return in_array($width, ['full', 'half', 'third', 'two-thirds', 'quarter', 'three-quarters'], true) ? $width : 'full';
    }

    /**
     * The width as a number of columns out of twelve.
     */
    public function columns(): int
    {
        return match ($this->width) {
            'quarter' => 3,
            'third' => 4,
            'half' => 6,
            'two-thirds' => 8,
            'three-quarters' => 9,
            default => 12,
        };
    }

    public function isInput(): bool
    {
        return $this->type->isInput();
    }

    /**
     * Whether the field shows and validates for the given values (its own
     * conditions only; Form::visibleKeys() adds the cascade).
     *
     * @param  array<string, mixed>  $values
     */
    public function isVisibleFor(array $values): bool
    {
        return $this->visibility()->passes($values);
    }

    /**
     * Whether a value is required for the given values.
     *
     * @param  array<string, mixed>  $values
     */
    public function isRequiredFor(array $values): bool
    {
        return $this->required && $this->requirement()->passes($values);
    }

    public function visibility(): Conditions
    {
        return $this->visibility ?? Conditions::always();
    }

    public function requirement(): Conditions
    {
        return $this->requirement ?? Conditions::always();
    }

    /**
     * Whether the required state or visibility depends on other fields.
     */
    public function isConditional(): bool
    {
        return ! $this->visibility()->isAlways() || ($this->required && ! $this->requirement()->isAlways());
    }

    /**
     * The value => label choices of a select, radio or checkbox list.
     *
     * @return array<string, string>
     */
    public function choices(): array
    {
        return $this->type->choices($this);
    }

    /**
     * The choices as stored in the builder data, normalised to value => label.
     *
     * @return array<string, string>
     */
    public function storedChoices(): array
    {
        $choices = $this->options['choices'] ?? [];

        if (! is_array($choices)) {
            return [];
        }

        $normalized = [];

        foreach ($choices as $value => $label) {
            // A list of {value, label} rows or a value => label map both work.
            if (is_array($label)) {
                $value = $label['value'] ?? ($label['label'] ?? null);
                $label = $label['label'] ?? $value;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $normalized[(string) $value] = (string) ($label ?? $value);
        }

        return $normalized;
    }

    /**
     * The complete validation rules for the field: required/nullable, the
     * type's own rules, the ones picked from the list and the custom ones
     * typed in the builder.
     *
     * @param  array<string, mixed>|null  $values  The submitted values, for conditional requirement (null = as configured).
     * @return array<int, mixed>
     */
    public function rules(?array $values = null): array
    {
        $required = $values === null ? $this->required : $this->isRequiredFor($values);
        $rules = [$required ? 'required' : 'nullable'];

        foreach ([...$this->type->rules($this), ...$this->pickedRules(), ...$this->rules] as $rule) {
            if ($rule !== null && $rule !== '' && ! in_array($rule, $rules, true)) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    /**
     * The rules of the value's parts, keyed "key.part" (see
     * FieldType::nestedRules()).
     *
     * @param  array<string, mixed>|null  $values
     * @return array<string, array<int, mixed>>
     */
    public function nestedRules(?array $values = null): array
    {
        $required = $values === null ? $this->required : $this->isRequiredFor($values);
        $rules = [];

        foreach ($this->type->nestedRules($this, $required) as $part => $partRules) {
            $rules[$this->key.'.'.$part] = $partRules;
        }

        return $rules;
    }

    /**
     * The rules picked from the list, as Laravel rules.
     *
     * @return array<int, mixed>
     */
    public function pickedRules(): array
    {
        $rules = [];

        foreach ($this->validation as $entry) {
            $rule = ValidationRules::toLaravel($entry['rule'], $entry['value']);

            if ($rule !== null) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    /**
     * Custom validation messages, keyed "key.rule", when the field has one.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        if ($this->message === null) {
            return [];
        }

        $names = ['required'];

        foreach ($this->rules(null) as $rule) {
            $name = is_string($rule) ? explode(':', $rule, 2)[0] : (is_object($rule) ? Str::snake(class_basename($rule)) : null);

            if ($name !== null && $name !== 'nullable') {
                $names[] = $name;
            }
        }

        foreach ($this->validation as $entry) {
            $names[] = ValidationRules::laravelName($entry['rule']);
        }

        $messages = [];

        foreach (array_unique($names) as $name) {
            $messages[$this->key.'.'.$name] = $this->message;
        }

        return $messages;
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function htmlId(string $prefix): string
    {
        return $prefix.'-'.Str::slug($this->key);
    }

    /**
     * The field as the JSON definition endpoint exposes it.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type::id(),
            'input' => $this->isInput(),
            'label' => $this->label,
            'placeholder' => $this->placeholder,
            'hint' => $this->hint,
            'required' => $this->required,
            'default' => $this->default,
            'width' => $this->width,
            'multiple' => $this->type->acceptsMultiple(),
            'choices' => $this->type->hasChoices() ? $this->choices() : null,
            'options' => $this->options,
            'section' => $this->section,
            'visibility' => $this->visibility()->isAlways() ? null : $this->visibility()->toArray(),
            'requirement' => $this->required && ! $this->requirement()->isAlways() ? $this->requirement()->toArray() : null,
            ...$this->type->definition($this),
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }

    /**
     * @return array<int, string>
     */
    private static function normalizeRules(mixed $rules): array
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        if (! is_array($rules)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($rule): string => is_string($rule) ? trim($rule) : '',
            $rules,
        )));
    }

    /**
     * @return array<int, array{rule: string, value: mixed}>
     */
    private static function normalizeValidation(mixed $validation): array
    {
        if (! is_array($validation)) {
            return [];
        }

        $entries = [];

        foreach ($validation as $entry) {
            if (! is_array($entry) || ! is_string($entry['rule'] ?? null) || $entry['rule'] === '') {
                continue;
            }

            $entries[] = ['rule' => $entry['rule'], 'value' => $entry['value'] ?? null];
        }

        return $entries;
    }
}

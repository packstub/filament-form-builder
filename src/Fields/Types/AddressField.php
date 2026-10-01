<?php

namespace Packstub\FormBuilder\Fields\Types;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Text;
use Illuminate\Validation\Rule;
use Packstub\FormBuilder\Fields\Countries;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\FieldType;
use Packstub\FormBuilder\Fields\ValidationRules;

/**
 * A postal address: street (two lines), city, region, postal code and
 * country, stored as one object. The builder picks the parts that show and
 * the ones a required address needs. No autocomplete: plain inputs that
 * work without JavaScript.
 */
class AddressField extends FieldType
{
    public const PARTS = ['line1', 'line2', 'city', 'region', 'postal_code', 'country'];

    public const REQUIRED_BY_DEFAULT = ['line1', 'city', 'postal_code', 'country'];

    public const AUTOCOMPLETE = [
        'line1' => 'address-line1',
        'line2' => 'address-line2',
        'city' => 'address-level2',
        'region' => 'address-level1',
        'postal_code' => 'postal-code',
        'country' => 'country',
    ];

    public static function id(): string
    {
        return 'address';
    }

    public function icon(): string
    {
        return 'heroicon-o-map-pin';
    }

    public function hasPlaceholder(): bool
    {
        return false;
    }

    public function hasDefault(): bool
    {
        return false;
    }

    public function isComparable(): bool
    {
        return false;
    }

    public function ruleCategory(): ?string
    {
        return null;
    }

    public function editorSchema(): array
    {
        $parts = static::partLabels();

        return [
            CheckboxList::make('parts')
                ->label(__('packstub-form-builder::form-builder.editor.address_parts'))
                ->options($parts)
                ->default(self::PARTS)
                ->columns(2),
            CheckboxList::make('required_parts')
                ->label(__('packstub-form-builder::form-builder.editor.address_required_parts'))
                ->helperText(__('packstub-form-builder::form-builder.editor.address_required_parts_hint'))
                ->options($parts)
                ->default(self::REQUIRED_BY_DEFAULT)
                ->columns(2),
            TextInput::make('countries')
                ->label(__('packstub-form-builder::form-builder.editor.countries'))
                ->helperText(__('packstub-form-builder::form-builder.editor.countries_hint'))
                ->placeholder('US, GB, DE'),
        ];
    }

    /**
     * part => label of every part.
     *
     * @return array<string, string>
     */
    public static function partLabels(): array
    {
        return collect(self::PARTS)
            ->mapWithKeys(fn (string $part): array => [$part => __('packstub-form-builder::form-builder.address.'.$part)])
            ->all();
    }

    /**
     * part => label of the parts the field shows, in order.
     *
     * @return array<string, string>
     */
    public function parts(Field $field): array
    {
        $chosen = $field->option('parts');
        $chosen = is_array($chosen) && $chosen !== [] ? $chosen : self::PARTS;

        return array_intersect_key(static::partLabels(), array_flip(array_intersect(self::PARTS, $chosen)));
    }

    /**
     * The parts a required address needs; never none, or a required address
     * could be left blank.
     *
     * @return array<int, string>
     */
    public function requiredParts(Field $field): array
    {
        $parts = array_keys($this->parts($field));
        $chosen = $field->option('required_parts');
        $required = array_values(array_intersect($parts, is_array($chosen) ? $chosen : self::REQUIRED_BY_DEFAULT));

        if ($required === []) {
            $required = array_values(array_intersect($parts, self::REQUIRED_BY_DEFAULT)) ?: array_slice($parts, 0, 1);
        }

        return $required;
    }

    /**
     * The countries the country part offers, code => name.
     *
     * @return array<string, string>
     */
    public function countries(Field $field): array
    {
        $all = Countries::all();
        $choices = [];

        foreach (array_map('strtoupper', ValidationRules::list((string) $field->option('countries', ''))) as $code) {
            if (isset($all[$code])) {
                $choices[$code] = $all[$code];
            }
        }

        return $choices === [] ? $all : $choices;
    }

    public function rules(Field $field): array
    {
        return ['array'];
    }

    public function nestedRules(Field $field, bool $required): array
    {
        $rules = [];
        $requiredParts = $this->requiredParts($field);

        foreach (array_keys($this->parts($field)) as $part) {
            $rules[$part] = [
                $required && in_array($part, $requiredParts, true) ? 'required' : 'nullable',
                'string',
                $part === 'country' ? Rule::in(array_keys($this->countries($field))) : 'max:255',
            ];
        }

        return $rules;
    }

    public function nestedAttributes(Field $field): array
    {
        return array_map(fn (string $label): string => $field->label.' ('.mb_strtolower($label).')', $this->parts($field));
    }

    public function prepare(mixed $value, Field $field): mixed
    {
        if (! is_array($value)) {
            return $value === null || $value === '' ? null : $value;
        }

        $prepared = [];

        foreach (array_keys($this->parts($field)) as $part) {
            $item = $value[$part] ?? null;
            $item = is_scalar($item) ? trim((string) $item) : null;
            $prepared[$part] = $item === '' || $item === null ? null : ($part === 'country' ? strtoupper($item) : $item);
        }

        return $prepared;
    }

    public function normalize(mixed $value, Field $field): mixed
    {
        $value = $this->prepare($value, $field);

        if (! is_array($value) || array_filter($value, fn ($item): bool => $item !== null) === []) {
            return null;
        }

        return $value;
    }

    public function format(mixed $value, Field $field): string
    {
        if (! is_array($value)) {
            return is_scalar($value) ? (string) $value : '';
        }

        $country = $value['country'] ?? null;
        $locality = trim(implode(' ', array_filter([$value['postal_code'] ?? null, $value['city'] ?? null])));

        return implode(', ', array_filter([
            $value['line1'] ?? null,
            $value['line2'] ?? null,
            $locality !== '' ? $locality : null,
            $value['region'] ?? null,
            $country === null ? null : (Countries::all()[$country] ?? $country),
        ], fn ($item): bool => $item !== null && $item !== ''));
    }

    public function exportColumns(Field $field): array
    {
        return $this->nestedAttributes($field);
    }

    public function exportValue(mixed $value, Field $field, string $column): string
    {
        $item = is_array($value) ? ($value[$column] ?? null) : null;

        if ($column === 'country' && is_string($item)) {
            return Countries::all()[$item] ?? $item;
        }

        return is_scalar($item) ? (string) $item : '';
    }

    public function definition(Field $field): array
    {
        return [
            'parts' => $this->parts($field),
            'required_parts' => $field->required ? $this->requiredParts($field) : [],
            'countries' => isset($this->parts($field)['country']) ? $this->countries($field) : null,
        ];
    }

    public function formComponent(Field $field): Component
    {
        $required = $field->required && $field->requirement()->isAlways();
        $requiredParts = $this->requiredParts($field);
        $components = [];

        foreach ($this->parts($field) as $part => $label) {
            $component = $part === 'country'
                ? Select::make($part)->options($this->countries($field))->searchable()->native(false)
                : TextInput::make($part)->maxLength(255)->autocomplete(self::AUTOCOMPLETE[$part]);

            $components[] = $component
                ->label($label)
                ->required($required && in_array($part, $requiredParts, true))
                ->columnSpan(in_array($part, ['line1', 'line2'], true) ? 2 : 1);
        }

        if ($field->hint !== null) {
            $components[] = Text::make($field->hint)->color('gray')->columnSpanFull();
        }

        return Fieldset::make($field->label)
            ->statePath($field->key)
            ->schema($components)
            ->columns(2)
            ->columnSpan($field->columns());
    }
}

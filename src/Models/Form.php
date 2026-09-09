<?php

namespace Packstub\FormBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\FieldTypeRegistry;
use Packstub\FormBuilder\FormBuilder;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property ?string $description
 * @property ?array<int, array<string, mixed>> $fields
 * @property bool $is_active
 * @property ?string $submit_label
 * @property ?string $success_message
 * @property ?string $redirect_url
 * @property ?array<int, string> $notification_emails
 * @property bool $store_submissions
 * @property ?Carbon $opens_at
 * @property ?Carbon $closes_at
 * @property ?array<string, mixed> $settings
 */
class Form extends Model
{
    protected $guarded = [];

    protected $attributes = [
        'is_active' => true,
        'store_submissions' => true,
    ];

    /** @var Collection<int, Field>|null */
    protected ?Collection $resolvedFields = null;

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'is_active' => 'boolean',
            'notification_emails' => 'array',
            'store_submissions' => 'boolean',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $form): void {
            $form->slug = Str::slug($form->slug ?: $form->name);
            $form->fields = static::normalizeFieldDefinitions($form->fields ?? []);
            $form->resolvedFields = null;
        });

        static::retrieved(fn (self $form) => $form->resolvedFields = null);
    }

    public function getTable(): string
    {
        return config('packstub-form-builder.tables.forms', 'form_builder_forms');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormBuilder::submissionModel(), 'form_id');
    }

    // ------------------------------------------------------------------
    // Fields
    // ------------------------------------------------------------------

    /**
     * Every field of the form, resolved against the registry (unknown types
     * are skipped).
     *
     * @return Collection<int, Field>
     */
    public function fieldList(): Collection
    {
        if ($this->resolvedFields !== null) {
            return $this->resolvedFields;
        }

        $registry = app(FieldTypeRegistry::class);

        return $this->resolvedFields = collect($this->fields ?? [])
            ->map(fn (array $item): ?Field => Field::fromArray($item, $registry))
            ->filter()
            ->values();
    }

    /**
     * The fields that collect a value, keyed by field key.
     *
     * @return Collection<string, Field>
     */
    public function inputFields(): Collection
    {
        return $this->fieldList()->filter(fn (Field $field): bool => $field->isInput())->keyBy('key');
    }

    public function field(string $key): ?Field
    {
        return $this->inputFields()->get($key);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function validationRules(): array
    {
        $rules = [];

        foreach ($this->inputFields() as $field) {
            $rules[$field->key] = $field->rules();

            if ($field->type->acceptsMultiple() && ($elementRules = $field->type->elementRules($field)) !== []) {
                $rules[$field->key.'.*'] = $elementRules;
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return $this->inputFields()->map(fn (Field $field): string => $field->label)->all();
    }

    /**
     * Unique, slug-safe keys for every stored field; missing keys come from
     * the label.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeFieldDefinitions(array $items): array
    {
        $registry = app(FieldTypeRegistry::class);
        $seen = [];

        foreach ($items as $index => $item) {
            $type = $registry->find((string) ($item['type'] ?? ''));

            if ($type === null || ! is_array($item['data'] ?? null)) {
                unset($items[$index]);

                continue;
            }

            $data = $item['data'];
            $key = Field::normalizeKey((string) ($data['key'] ?? ''), (string) ($data['label'] ?? ''), $type);

            if ($key === '') {
                $key = $type::id();
            }

            $base = $key;
            $suffix = 2;

            while (isset($seen[$key])) {
                $key = $base.'_'.$suffix++;
            }

            $seen[$key] = true;
            $data['key'] = $key;
            $items[$index]['data'] = $data;
        }

        return array_values($items);
    }

    // ------------------------------------------------------------------
    // Settings
    // ------------------------------------------------------------------

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function submitLabel(): string
    {
        return $this->submit_label ?: __('packstub-form-builder::form-builder.frontend.submit');
    }

    public function successMessage(): string
    {
        return $this->success_message ?: __('packstub-form-builder::form-builder.frontend.success');
    }

    /**
     * @return array<int, string>
     */
    public function notificationEmails(): array
    {
        return array_values(array_filter(array_map('trim', $this->notification_emails ?? [])));
    }

    public function usesHoneypot(): bool
    {
        return (bool) $this->setting('honeypot', config('packstub-form-builder.spam.honeypot', true));
    }

    public function minSeconds(): int
    {
        return (int) $this->setting('min_seconds', config('packstub-form-builder.spam.min_seconds', 2));
    }

    public function requiresLogin(): bool
    {
        return (bool) $this->setting('require_login', false);
    }

    // ------------------------------------------------------------------
    // Availability
    // ------------------------------------------------------------------

    /**
     * Why the form does not take submissions right now, or null when it does.
     */
    public function closedReason(): ?string
    {
        if (! $this->is_active) {
            return __('packstub-form-builder::form-builder.frontend.closed');
        }

        if ($this->opens_at !== null && $this->opens_at->isFuture()) {
            return __('packstub-form-builder::form-builder.frontend.not_open_yet');
        }

        if ($this->closes_at !== null && $this->closes_at->isPast()) {
            return __('packstub-form-builder::form-builder.frontend.closed');
        }

        return null;
    }

    public function isAccepting(): bool
    {
        return $this->closedReason() === null;
    }

    // ------------------------------------------------------------------
    // URLs
    // ------------------------------------------------------------------

    public function submitUrl(): string
    {
        return route('packstub-form-builder.submit', $this);
    }

    public function definitionUrl(): string
    {
        return route('packstub-form-builder.definition', $this);
    }

    public function pageUrl(): ?string
    {
        return app('router')->has('packstub-form-builder.show') ? route('packstub-form-builder.show', $this) : null;
    }

    /**
     * The form as the JSON definition endpoint exposes it.
     *
     * @return array<string, mixed>
     */
    public function toDefinition(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'accepting' => $this->isAccepting(),
            'closed_reason' => $this->closedReason(),
            'submit_label' => $this->submitLabel(),
            'success_message' => $this->successMessage(),
            'redirect_url' => $this->redirect_url,
            'submit_url' => $this->submitUrl(),
            'fields' => $this->fieldList()->map(fn (Field $field): array => $field->toArray())->all(),
        ];
    }
}

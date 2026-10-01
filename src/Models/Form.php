<?php

namespace Packstub\FormBuilder\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Packstub\FormBuilder\Fields\Conditions;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\FieldTypeRegistry;
use Packstub\FormBuilder\Fields\Section;
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
 * @property ?int $submissions_number
 * @property ?int $user_id
 */
class Form extends Model
{
    public const SECTION_TYPE = 'section';

    protected $guarded = [];

    protected $attributes = [
        'is_active' => true,
        'store_submissions' => true,
    ];

    /** @var Collection<int, Section>|null */
    protected ?Collection $resolvedSections = null;

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
            $form->resolvedSections = null;
        });

        static::retrieved(fn (self $form) => $form->resolvedSections = null);

        static::addGlobalScope('tenant', function (Builder $query): void {
            $column = FormBuilder::tenantColumn();
            $tenant = FormBuilder::currentTenantKey();

            if ($column !== null && $tenant !== null) {
                $query->where($query->qualifyColumn($column), $tenant);
            }
        });

        static::creating(function (self $form): void {
            if ($form->user_id === null && ($user = auth()->id()) !== null) {
                $form->user_id = $user;
            }

            $column = FormBuilder::tenantColumn();

            if ($column !== null && $form->getAttribute($column) === null && ($tenant = FormBuilder::currentTenantKey()) !== null) {
                $form->setAttribute($column, $tenant);
            }
        });
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

    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(FormBuilder::webhookDeliveryModel(), 'form_id');
    }

    public function shareLinks(): HasMany
    {
        return $this->hasMany(FormBuilder::shareLinkModel(), 'form_id');
    }

    /**
     * The user who created the form.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'user_id');
    }

    /**
     * The tenant the form belongs to, when the plugin runs in a tenant-aware
     * panel (config "tenancy").
     */
    public function tenant(): BelongsTo
    {
        $model = FormBuilder::tenantModel() ?? config('auth.providers.users.model');

        return $this->belongsTo($model, FormBuilder::tenantColumn() ?? 'tenant_id');
    }

    // ------------------------------------------------------------------
    // Fields
    // ------------------------------------------------------------------

    /**
     * The sections of the form, in order: the builder's section blocks, plus
     * an unnamed one for the fields outside any section. Hidden fields are
     * left out.
     *
     * @return Collection<int, Section>
     */
    public function sections(): Collection
    {
        if ($this->resolvedSections !== null) {
            return $this->resolvedSections;
        }

        $registry = app(FieldTypeRegistry::class);
        $sections = collect();
        $loose = collect();
        $index = 0;

        $flush = function () use (&$loose, &$sections, &$index, $registry): void {
            if ($loose->isNotEmpty()) {
                $key = 'section_'.(++$index);
                $fields = $loose
                    ->map(fn (array $item): ?Field => Field::fromArray($item, $registry, $key))
                    ->filter(fn (?Field $field): bool => $field !== null && ! $field->hidden)
                    ->values();
                $sections->push(new Section($key, null, null, Conditions::always(), $fields, implicit: true));
                $loose = collect();
            }
        };

        foreach ($this->fields ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (($item['type'] ?? null) === self::SECTION_TYPE) {
                $flush();
                $data = is_array($item['data'] ?? null) ? $item['data'] : [];

                if ((bool) ($data['hidden'] ?? false)) {
                    continue;
                }

                $key = Str::slug((string) ($data['key'] ?? ''), '_') ?: 'section_'.($index + 1);
                $index++;
                $fields = collect(is_array($data['fields'] ?? null) ? $data['fields'] : [])
                    ->map(fn ($child): ?Field => is_array($child) ? Field::fromArray($child, $registry, $key) : null)
                    ->filter(fn (?Field $field): bool => $field !== null && ! $field->hidden)
                    ->values();

                $sections->push(new Section(
                    $key,
                    filled($data['label'] ?? null) ? (string) $data['label'] : null,
                    filled($data['description'] ?? null) ? (string) $data['description'] : null,
                    Conditions::fromData($data, 'visibility'),
                    $fields,
                ));

                continue;
            }

            $loose->push($item);
        }

        $flush();

        return $this->resolvedSections = $sections;
    }

    /**
     * Whether the form has at least one named section (the builder's
     * section blocks), which the multi-step mode turns into steps.
     */
    public function hasSections(): bool
    {
        return $this->sections()->contains(fn (Section $section): bool => ! $section->implicit);
    }

    /**
     * Every visible field of the form, flat, resolved against the registry
     * (unknown types and hidden fields are skipped).
     *
     * @return Collection<int, Field>
     */
    public function fieldList(): Collection
    {
        return $this->sections()->flatMap(fn (Section $section): Collection => $section->fields)->values();
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
     * Every field the builder holds, hidden ones included, flat, keyed by
     * key. For exports and columns that must keep showing old values.
     *
     * @return Collection<string, Field>
     */
    public function allInputFields(): Collection
    {
        $registry = app(FieldTypeRegistry::class);
        $fields = collect();

        foreach ($this->fields ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (($item['type'] ?? null) === self::SECTION_TYPE) {
                foreach ((array) data_get($item, 'data.fields', []) as $child) {
                    if (is_array($child) && ($field = Field::fromArray($child, $registry)) !== null && $field->isInput()) {
                        $fields->put($field->key, $field);
                    }
                }

                continue;
            }

            if (($field = Field::fromArray($item, $registry)) !== null && $field->isInput()) {
                $fields->put($field->key, $field);
            }
        }

        return $fields;
    }

    /**
     * The keys of the input fields that show for the given values, with the
     * cascade: a field whose conditions look at a hidden field is hidden
     * too, and a field in a hidden section is hidden.
     *
     * @param  array<string, mixed>  $values
     * @return array<int, string>
     */
    public function visibleKeys(array $values): array
    {
        $fields = $this->inputFields();
        $visible = [];

        // Fields with no conditions first; then repeat until nothing changes.
        foreach ($fields as $field) {
            $visible[$field->key] = $field->visibility()->isAlways();
        }

        $changed = true;

        while ($changed) {
            $changed = false;

            foreach ($fields as $field) {
                if ($visible[$field->key]) {
                    continue;
                }

                $depends = $field->visibility()->fieldKeys();
                $dependenciesVisible = true;

                foreach ($depends as $key) {
                    if (array_key_exists($key, $visible) && ! $visible[$key]) {
                        $dependenciesVisible = false;
                        break;
                    }
                }

                if ($dependenciesVisible && $field->isVisibleFor($values)) {
                    $visible[$field->key] = true;
                    $changed = true;
                }
            }
        }

        // A hidden dependency hides its dependants even when they turned visible first.
        $changed = true;

        while ($changed) {
            $changed = false;

            foreach ($fields as $field) {
                if (! $visible[$field->key]) {
                    continue;
                }

                foreach ($field->visibility()->fieldKeys() as $key) {
                    if (array_key_exists($key, $visible) && ! $visible[$key]) {
                        $visible[$field->key] = false;
                        $changed = true;
                        break;
                    }
                }

                if ($visible[$field->key] && ! $field->isVisibleFor($values)) {
                    $visible[$field->key] = false;
                    $changed = true;
                }
            }
        }

        // Fields of a hidden section.
        foreach ($this->sections() as $section) {
            if ($section->visibility->passes($values)) {
                continue;
            }

            foreach ($section->fields as $field) {
                $visible[$field->key] = false;
            }
        }

        return array_keys(array_filter($visible));
    }

    /**
     * The validation rules for the given input (conditions applied: hidden
     * fields are not validated, conditional requirement resolved).
     *
     * @param  array<string, mixed>|null  $values  null = every field, as configured.
     * @return array<string, array<int, mixed>>
     */
    public function validationRules(?array $values = null, ?array $only = null): array
    {
        $rules = [];
        $visible = $values === null ? null : $this->visibleKeys($values);

        foreach ($this->inputFields() as $field) {
            if ($visible !== null && ! in_array($field->key, $visible, true)) {
                continue;
            }

            if ($only !== null && ! in_array($field->key, $only, true)) {
                continue;
            }

            $rules[$field->key] = $field->rules($values);

            if ($field->type->acceptsMultiple() && ($elementRules = $field->type->elementRules($field)) !== []) {
                $rules[$field->key.'.*'] = $elementRules;
            }

            $rules = [...$rules, ...$field->nestedRules($values)];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function validationMessages(): array
    {
        $messages = [];

        foreach ($this->inputFields() as $field) {
            $messages = [...$messages, ...$field->messages()];
        }

        return $messages;
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        $attributes = [];

        foreach ($this->inputFields() as $field) {
            $attributes[$field->key] = $field->label;

            foreach ($field->type->nestedAttributes($field) as $part => $label) {
                $attributes[$field->key.'.'.$part] = $label;
            }
        }

        return $attributes;
    }

    /**
     * Unique, slug-safe keys for every stored field, sections' children
     * included; missing keys come from the label.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeFieldDefinitions(array $items): array
    {
        $seen = [];

        return static::normalizeItems($items, $seen, true);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, bool>  $seen
     * @return array<int, array<string, mixed>>
     */
    protected static function normalizeItems(array $items, array &$seen, bool $allowSections): array
    {
        $registry = app(FieldTypeRegistry::class);
        $sections = 0;

        foreach ($items as $index => $item) {
            if (! is_array($item) || ! is_array($item['data'] ?? null)) {
                unset($items[$index]);

                continue;
            }

            $data = $item['data'];

            if (($item['type'] ?? null) === self::SECTION_TYPE) {
                if (! $allowSections) {
                    unset($items[$index]);

                    continue;
                }

                $sections++;
                $key = Str::slug((string) ($data['key'] ?? ''), '_') ?: Str::slug((string) ($data['label'] ?? ''), '_') ?: 'section_'.$sections;
                $base = $key;
                $suffix = 2;

                while (isset($seen['section:'.$key])) {
                    $key = $base.'_'.$suffix++;
                }

                $seen['section:'.$key] = true;
                $data['key'] = $key;
                $data['fields'] = static::normalizeItems(is_array($data['fields'] ?? null) ? $data['fields'] : [], $seen, false);
                $items[$index]['data'] = $data;

                continue;
            }

            $type = $registry->find((string) ($item['type'] ?? ''));

            if ($type === null) {
                unset($items[$index]);

                continue;
            }

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
        $value = data_get($this->settings, $key);

        return $value === null || $value === '' ? $default : $value;
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

    /**
     * The captcha provider for the form: "turnstile", "hcaptcha",
     * "recaptcha", or null for none.
     */
    public function captcha(): ?string
    {
        $provider = $this->setting('captcha', config('packstub-form-builder.captcha.default'));

        return in_array($provider, ['turnstile', 'hcaptcha', 'recaptcha'], true) ? $provider : null;
    }

    public function isWizard(): bool
    {
        return $this->setting('mode') === 'wizard' && $this->hasSections();
    }

    public function layout(): string
    {
        return $this->setting('layout') === 'horizontal' ? 'horizontal' : 'stacked';
    }

    public function showsProgress(): bool
    {
        return (bool) $this->setting('wizard_progress', true);
    }

    public function showsStepNumbers(): bool
    {
        return (bool) $this->setting('wizard_step_numbers', true);
    }

    public function allowsStepNavigation(): bool
    {
        return (bool) $this->setting('wizard_back', true);
    }

    public function nextLabel(): string
    {
        return (string) $this->setting('next_label', __('packstub-form-builder::form-builder.frontend.next'));
    }

    public function previousLabel(): string
    {
        return (string) $this->setting('previous_label', __('packstub-form-builder::form-builder.frontend.previous'));
    }

    public function isPrivate(): bool
    {
        return $this->setting('visibility') === 'private';
    }

    public function password(): ?string
    {
        $password = $this->setting('password');

        return is_string($password) && $password !== '' ? $password : null;
    }

    public function onePerPerson(): bool
    {
        return (bool) $this->setting('one_per_person', false);
    }

    public function maxSubmissions(): ?int
    {
        $max = (int) $this->setting('max_submissions', 0);

        return $max > 0 ? $max : null;
    }

    public function customCss(): ?string
    {
        $css = $this->setting('custom_css');

        return is_string($css) && trim($css) !== '' ? $css : null;
    }

    public function customJs(): ?string
    {
        $js = $this->setting('custom_js');

        return is_string($js) && trim($js) !== '' ? $js : null;
    }

    public function pageTitle(): string
    {
        return (string) $this->setting('page_title', $this->name);
    }

    public function pageDescription(): ?string
    {
        return $this->setting('page_description', $this->description);
    }

    public function pageImage(): ?string
    {
        return $this->setting('page_image');
    }

    public function pageLogo(): ?string
    {
        return $this->setting('page_logo');
    }

    public function brandColor(): ?string
    {
        return $this->setting('brand_color');
    }

    public function prefillsFromQuery(): bool
    {
        return (bool) $this->setting('prefill', config('packstub-form-builder.frontend.prefill', true));
    }

    public function retentionDays(): ?int
    {
        $days = (int) $this->setting('retention_days', (int) config('packstub-form-builder.submissions.retention_days', 0));

        return $days > 0 ? $days : null;
    }

    /**
     * The fields whose uploads the notification email attaches.
     */
    public function attachesFiles(): bool
    {
        return (bool) $this->setting('notify_attach_files', false);
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
            return $this->closedMessage();
        }

        if ($this->opens_at !== null && $this->opens_at->isFuture()) {
            return __('packstub-form-builder::form-builder.frontend.not_open_yet');
        }

        if ($this->closes_at !== null && $this->closes_at->isPast()) {
            return $this->closedMessage();
        }

        if (($max = $this->maxSubmissions()) !== null && $this->exists && $this->submissions()->count() >= $max) {
            return (string) $this->setting('full_message', __('packstub-form-builder::form-builder.frontend.full'));
        }

        return null;
    }

    public function closedMessage(): string
    {
        return (string) $this->setting('closed_message', __('packstub-form-builder::form-builder.frontend.closed'));
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

    public function validateUrl(): string
    {
        return route('packstub-form-builder.validate', $this);
    }

    public function definitionUrl(): string
    {
        return route('packstub-form-builder.definition', $this);
    }

    public function pageUrl(): ?string
    {
        return app('router')->has('packstub-form-builder.show') ? route('packstub-form-builder.show', $this) : null;
    }

    public function embedScriptUrl(): ?string
    {
        return app('router')->has('packstub-form-builder.embed') ? route('packstub-form-builder.embed', $this) : null;
    }

    /**
     * A signed link to the hosted page, a way into a private form besides
     * its share links (shareLinks()). Valid from now until $until (null =
     * no expiry).
     */
    public function shareUrl(?\DateTimeInterface $until = null): ?string
    {
        if (! app('router')->has('packstub-form-builder.show')) {
            return null;
        }

        return $until === null
            ? URL::signedRoute('packstub-form-builder.show', ['form' => $this->slug])
            : URL::temporarySignedRoute('packstub-form-builder.show', $until, ['form' => $this->slug]);
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
            'validate_url' => $this->validateUrl(),
            'mode' => $this->isWizard() ? 'wizard' : 'single',
            'layout' => $this->layout(),
            'wizard' => $this->isWizard() ? [
                'progress' => $this->showsProgress(),
                'step_numbers' => $this->showsStepNumbers(),
                'navigation' => $this->allowsStepNavigation(),
                'next_label' => $this->nextLabel(),
                'previous_label' => $this->previousLabel(),
            ] : null,
            'sections' => $this->sections()->map(fn (Section $section): array => $section->toArray())->all(),
            'fields' => $this->fieldList()->map(fn (Field $field): array => $field->toArray())->all(),
            'captcha' => $this->captcha(),
        ];
    }

    /**
     * The form as a portable array (export, templates, duplicates): every
     * column but the id, slug and timestamps.
     *
     * @return array<string, mixed>
     */
    public function toPortable(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'fields' => $this->fields ?? [],
            'is_active' => $this->is_active,
            'submit_label' => $this->submit_label,
            'success_message' => $this->success_message,
            'redirect_url' => $this->redirect_url,
            'notification_emails' => $this->notification_emails,
            'store_submissions' => $this->store_submissions,
            'settings' => $this->settings,
        ];
    }

    /**
     * Build an unsaved form from a portable array (an export, a template or
     * an array you write in code); render it with the Blade or Livewire
     * component, or save it.
     *
     * @param  array<string, mixed>  $definition
     */
    public static function fromArray(array $definition): static
    {
        $allowed = ['name', 'slug', 'description', 'fields', 'is_active', 'submit_label', 'success_message', 'redirect_url', 'notification_emails', 'store_submissions', 'opens_at', 'closes_at', 'settings'];
        $attributes = array_intersect_key($definition, array_flip($allowed));
        $attributes['fields'] = static::normalizeFieldDefinitions(is_array($attributes['fields'] ?? null) ? $attributes['fields'] : []);
        $attributes['name'] = (string) ($attributes['name'] ?? 'Form');
        $attributes['slug'] = Str::slug((string) ($attributes['slug'] ?? $attributes['name']));

        /** @var static $form */
        $form = new (FormBuilder::formModel())($attributes);

        return $form;
    }
}

<?php

namespace Packstub\FormBuilder\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\FieldTypeRegistry;
use Packstub\FormBuilder\FormBuilder;

/**
 * @property int $id
 * @property int $form_id
 * @property ?array<string, mixed> $data
 * @property ?array<string, array{label: string, type: string}> $fields
 * @property ?int $user_id
 * @property ?string $ip
 * @property ?string $user_agent
 * @property ?string $fingerprint
 * @property ?string $source_url
 * @property string $channel
 * @property ?array<string, mixed> $meta
 * @property ?Carbon $read_at
 * @property ?int $number
 * @property ?int $share_link_id
 * @property Form $form
 */
class FormSubmission extends Model
{
    protected $guarded = [];

    protected $attributes = [
        'channel' => 'web',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'fields' => 'array',
            'meta' => 'array',
            'read_at' => 'datetime',
            'number' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return config('packstub-form-builder.tables.submissions', 'form_builder_submissions');
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(FormBuilder::formModel(), 'form_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'user_id');
    }

    public function shareLink(): BelongsTo
    {
        return $this->belongsTo(FormBuilder::shareLinkModel(), 'share_link_id');
    }

    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(FormBuilder::webhookDeliveryModel(), 'submission_id');
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function markRead(bool $read = true): static
    {
        $this->forceFill(['read_at' => $read ? now() : null])->save();

        return $this;
    }

    /**
     * "#42": the sequential number within the form, else the id.
     */
    public function reference(): string
    {
        return '#'.($this->number ?? $this->getKey());
    }

    public function value(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    /**
     * The label of a field as it was when the submission came in.
     */
    public function label(string $key): string
    {
        return (string) data_get($this->fields, "{$key}.label", $key);
    }

    /**
     * The field a stored value belongs to: the form's current one, else one
     * rebuilt from the label and type recorded with the submission.
     */
    public function fieldFor(string $key): Field
    {
        $form = $this->relationLoaded('form') || $this->form_id ? $this->form : null;

        if (($field = $form?->allInputFields()->get($key)) !== null) {
            return $field;
        }

        $registry = app(FieldTypeRegistry::class);
        $type = $registry->find((string) data_get($this->fields, "{$key}.type", 'text')) ?? $registry->get('text');

        return new Field($key, $type, $this->label($key));
    }

    /**
     * Every value as text, keyed by field key: ['email' => ['label' => 'Email', 'value' => 'a@b.c']].
     *
     * @return array<string, array{label: string, value: string}>
     */
    public function formatted(): array
    {
        $rows = [];

        foreach ($this->data ?? [] as $key => $value) {
            $field = $this->fieldFor($key);

            $rows[$key] = [
                'label' => $this->label($key),
                'value' => $field->type->format($value, $field),
            ];
        }

        return $rows;
    }

    /**
     * A one-line summary of the first values (for tables and notifications).
     */
    public function summary(int $limit = 3): string
    {
        return collect($this->formatted())
            ->pluck('value')
            ->filter(fn (string $value): bool => $value !== '')
            ->take($limit)
            ->map(fn (string $value): string => Str::limit($value, 40))
            ->implode(' · ');
    }

    /**
     * The submission as a webhook or sink payload.
     *
     * @return array<string, mixed>
     */
    public function toPayload(bool $withMeta = true): array
    {
        $form = $this->form;

        return [
            'id' => $this->getKey(),
            'number' => $this->number,
            'form' => ['id' => $form->getKey(), 'slug' => $form->slug, 'name' => $form->name],
            'submitted_at' => ($this->created_at ?? now())->toIso8601String(),
            'data' => $this->data ?? [],
            'labels' => collect($this->fields ?? [])->map(fn (array $meta): string => (string) ($meta['label'] ?? ''))->all(),
            'meta' => $withMeta ? array_filter([
                'ip' => $this->ip,
                'user_agent' => $this->user_agent,
                'source_url' => $this->source_url,
                'channel' => $this->channel,
                'user_id' => $this->user_id,
                'share_link_id' => $this->share_link_id,
            ], fn ($value): bool => $value !== null) : null,
        ];
    }
}

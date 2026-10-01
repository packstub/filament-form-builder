<?php

namespace Packstub\FormBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Packstub\FormBuilder\FormBuilder;

/**
 * One attempt series at posting a submission to a form's webhook URL.
 *
 * @property int $id
 * @property int $form_id
 * @property ?int $submission_id
 * @property string $url
 * @property string $event
 * @property string $status pending / delivered / failed
 * @property int $attempts
 * @property ?int $response_status
 * @property ?string $response_body
 * @property ?string $error
 * @property ?array<string, mixed> $payload
 * @property ?Carbon $delivered_at
 * @property ?Carbon $next_attempt_at
 */
class WebhookDelivery extends Model
{
    public const PENDING = 'pending';

    public const DELIVERED = 'delivered';

    public const FAILED = 'failed';

    protected $guarded = [];

    protected $attributes = [
        'status' => self::PENDING,
        'attempts' => 0,
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'response_status' => 'integer',
            'delivered_at' => 'datetime',
            'next_attempt_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return config('packstub-form-builder.tables.webhook_deliveries', 'form_builder_webhook_deliveries');
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(FormBuilder::formModel(), 'form_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormBuilder::submissionModel(), 'submission_id');
    }

    public function isDelivered(): bool
    {
        return $this->status === self::DELIVERED;
    }

    /**
     * Whether the delivery is a message to a chat channel (event
     * "channel.slack" / "channel.discord" / "channel.teams") rather than
     * the form's webhook.
     */
    public function isChannelMessage(): bool
    {
        return str_starts_with((string) $this->event, 'channel.');
    }
}

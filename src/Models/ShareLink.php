<?php

namespace Packstub\FormBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Packstub\FormBuilder\FormBuilder;

/**
 * A stored link to a private form (/f/{token}): a label, an optional expiry
 * and submission cap, revocable on its own. Submissions made through it
 * record it.
 *
 * @property int $id
 * @property int $form_id
 * @property string $token
 * @property ?string $label
 * @property ?Carbon $expires_at
 * @property ?Carbon $revoked_at
 * @property ?int $max_submissions
 * @property ?int $user_id
 * @property Form $form
 */
class ShareLink extends Model
{
    public const ACTIVE = 'active';

    public const EXPIRED = 'expired';

    public const REVOKED = 'revoked';

    public const FULL = 'full';

    /** The hidden input that carries the link's token with a submission. */
    public const FIELD = '_fb_link';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'max_submissions' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $link): void {
            $link->token = $link->token ?: static::generateToken();
        });

        // Submissions keep their data when a link goes; they just lose the reference.
        static::deleting(function (self $link): void {
            FormBuilder::submissionModel()::query()->where('share_link_id', $link->getKey())->update(['share_link_id' => null]);
        });
    }

    public function getTable(): string
    {
        return config('packstub-form-builder.tables.share_links', 'form_builder_share_links');
    }

    public static function generateToken(): string
    {
        return Str::lower(Str::random(20));
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(FormBuilder::formModel(), 'form_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormBuilder::submissionModel(), 'share_link_id');
    }

    /**
     * active, expired, revoked or full.
     */
    public function status(): string
    {
        return match (true) {
            $this->revoked_at !== null => self::REVOKED,
            $this->expires_at !== null && $this->expires_at->isPast() => self::EXPIRED,
            $this->max_submissions !== null && $this->submissions()->count() >= $this->max_submissions => self::FULL,
            default => self::ACTIVE,
        };
    }

    public function isActive(): bool
    {
        return $this->status() === self::ACTIVE;
    }

    /**
     * Why the link does not open the form any more, or null when it does.
     */
    public function closedReason(): ?string
    {
        return match ($this->status()) {
            self::ACTIVE => null,
            self::FULL => (string) ($this->form?->setting('full_message') ?: __('packstub-form-builder::form-builder.frontend.full')),
            default => __('packstub-form-builder::form-builder.frontend.link_invalid'),
        };
    }

    public function revoke(): static
    {
        $this->forceFill(['revoked_at' => now()])->save();

        return $this;
    }

    public function url(): ?string
    {
        return app('router')->has('packstub-form-builder.share') ? route('packstub-form-builder.share', $this->token) : null;
    }

    /**
     * An active link of the given form by its token.
     */
    public static function findActive(Form $form, mixed $token): ?static
    {
        if (! is_string($token) || $token === '' || ! $form->exists) {
            return null;
        }

        /** @var static|null $link */
        $link = $form->shareLinks()->where('token', $token)->first();

        return $link?->isActive() ? $link : null;
    }
}

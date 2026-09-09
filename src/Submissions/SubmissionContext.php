<?php

namespace Packstub\FormBuilder\Submissions;

use Illuminate\Http\Request;

/**
 * Where a submission came from.
 */
final class SubmissionContext
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly ?string $ip = null,
        public readonly ?string $userAgent = null,
        public readonly ?string $sourceUrl = null,
        public readonly int|string|null $userId = null,
        public readonly string $channel = 'web',
        public readonly array $meta = [],
        public readonly bool $trusted = false,
    ) {}

    public static function fromRequest(Request $request, string $channel = 'web'): self
    {
        return new self(
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            sourceUrl: self::sourceUrl($request),
            userId: $request->user()?->getAuthIdentifier(),
            channel: $channel,
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function withMeta(array $meta): self
    {
        return new self($this->ip, $this->userAgent, $this->sourceUrl, $this->userId, $this->channel, [...$this->meta, ...$meta], $this->trusted);
    }

    public function withChannel(string $channel): self
    {
        return new self($this->ip, $this->userAgent, $this->sourceUrl, $this->userId, $channel, $this->meta, $this->trusted);
    }

    /**
     * Skip the honeypot and time-trap checks (submissions made from code).
     */
    public function trusted(bool $trusted = true): self
    {
        return new self($this->ip, $this->userAgent, $this->sourceUrl, $this->userId, $this->channel, $this->meta, $trusted);
    }

    /**
     * The page the form was submitted from: the hidden return field, else the referer.
     */
    public static function sourceUrl(Request $request): ?string
    {
        $url = $request->input('_fb_return') ?: $request->headers->get('referer');

        return is_string($url) && $url !== '' ? mb_substr($url, 0, 2048) : null;
    }
}

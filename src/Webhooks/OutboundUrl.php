<?php

namespace Packstub\FormBuilder\Webhooks;

use Packstub\FormBuilder\FormBuilder;

/**
 * Where the people who build forms may send webhooks and channel messages.
 * Unless config "webhooks.private_networks" allows it, only http(s) URLs
 * whose host resolves to public addresses, so a form can't make the server
 * call itself, the internal network or a cloud metadata endpoint and show
 * the answer in the deliveries log.
 */
final class OutboundUrl
{
    /**
     * Whether webhooks may reach private, loopback and reserved addresses:
     * config "webhooks.private_networks", or when it is null (the default),
     * only outside tenancy, where the people who build forms are the
     * site's own.
     */
    public static function allowsPrivateNetworks(): bool
    {
        $allowed = config('packstub-form-builder.webhooks.private_networks');

        return $allowed === null ? FormBuilder::tenantColumn() === null : (bool) $allowed;
    }

    /**
     * The public address to connect to for a URL, or null when the URL may
     * not be called: not http(s), a host that doesn't resolve, or one that
     * resolves to any address outside the public internet.
     */
    public static function resolve(string $url): ?string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : self::lookup($host);

        if ($addresses === []) {
            return null;
        }

        foreach ($addresses as $address) {
            if (! self::isPublic($address)) {
                return null;
            }
        }

        return $addresses[0];
    }

    /**
     * Whether a URL may be called (see resolve()); always true when
     * private networks are allowed.
     */
    public static function allows(string $url): bool
    {
        return self::allowsPrivateNetworks() || self::resolve($url) !== null;
    }

    public static function isPublic(string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE) !== false;
    }

    /**
     * @return array<int, string>
     */
    private static function lookup(string $host): array
    {
        $addresses = [];
        $v4 = @gethostbynamel($host);

        if (is_array($v4)) {
            $addresses = $v4;
        }

        $v6 = @dns_get_record($host, DNS_AAAA);

        foreach (is_array($v6) ? $v6 : [] as $record) {
            if (is_string($record['ipv6'] ?? null)) {
                $addresses[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($addresses));
    }
}

<?php

namespace App\Support;

/**
 * The addresses a request reaches the application through: Cloudflare's edge,
 * then Traefik on the Docker network.
 *
 * Laravel trusts every one of them as a proxy, so `request()->ip()` walks the
 * X-Forwarded-For chain from the right past Cloudflare and Traefik and lands on
 * the visitor. Trusting only the immediate peer, as `'*'` does, stopped at
 * Cloudflare instead, and every guest who came through the same edge shared one
 * throttle bucket. A client cannot forge its way past this: Cloudflare appends
 * the address it saw after whatever the client sent.
 *
 * Traefik is given the Cloudflare half of the list in docker-compose.prod.yml,
 * as the only sources whose forwarded headers it keeps; a test holds the two
 * copies together.
 *
 * @see https://www.cloudflare.com/ips/
 */
final class CloudflareNetwork
{
    /** Cloudflare's published edge ranges. */
    public const EDGE_RANGES = [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];

    /** The private ranges Docker hands its networks out of, where Traefik sits. */
    public const PRIVATE_RANGES = [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '127.0.0.1',
        '::1',
    ];

    /**
     * @return list<string>
     */
    public static function trustedProxies(): array
    {
        return [...self::PRIVATE_RANGES, ...self::EDGE_RANGES];
    }
}

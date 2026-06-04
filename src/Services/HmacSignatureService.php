<?php

namespace Payfastlaravelpackage\PayFastLaravelPackage\Services;

use Illuminate\Support\Carbon;

/**
 * HMAC-SHA256 sign/verify for the external storefront → Laravel handoff.
 * Generic across Wix, Shopify, custom storefronts.
 */
class HmacSignatureService
{
    /** Fields participating in the signed payload. `sig` itself is excluded. */
    private const SIGNABLE_KEYS = [
        'checkout_id',
        'amount',
        'currency',
        'email',
        'first_name',
        'last_name',
        'mobile',
        'return_url',
        'ts',
    ];

    public function __construct(
        protected string $secret,
        protected int $ttlSeconds = 600,
    ) {}

    public function sign(array $params): string
    {
        return hash_hmac('sha256', $this->canonicalize($params), $this->secret);
    }

    public function verify(array $params): bool
    {
        if ($this->secret === '') {
            return false;
        }

        $providedSig = (string) ($params['sig'] ?? '');
        if ($providedSig === '') {
            return false;
        }

        $ts = (int) ($params['ts'] ?? 0);
        if ($ts <= 0) {
            return false;
        }

        if (Carbon::now()->getTimestamp() - $ts > $this->ttlSeconds) {
            return false;
        }

        return hash_equals($this->sign($params), $providedSig);
    }

    private function canonicalize(array $params): string
    {
        $filtered = [];
        foreach (self::SIGNABLE_KEYS as $key) {
            if (array_key_exists($key, $params) && $params[$key] !== null && $params[$key] !== '') {
                $filtered[$key] = (string) $params[$key];
            }
        }
        ksort($filtered);

        return http_build_query($filtered);
    }
}

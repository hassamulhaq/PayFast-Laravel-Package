<?php

namespace Payfastlaravelpackage\PayFastLaravelPackage\Exceptions;

use RuntimeException;

class PayfastException extends RuntimeException
{
    public ?array $context = null;

    public static function tokenFetchFailed(int $status, array $body): self
    {
        $e = new self("PayFast GetAccessToken failed (HTTP {$status})");
        $e->context = ['status' => $status, 'body' => $body];

        return $e;
    }

    public static function missingConfig(string $key): self
    {
        return new self("Missing PayFast config: {$key}");
    }
}

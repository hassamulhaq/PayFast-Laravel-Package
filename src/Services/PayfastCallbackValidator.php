<?php

namespace Payfastlaravelpackage\PayFastLaravelPackage\Services;

class PayfastCallbackValidator
{
    public function __construct(
        protected string $merchantId,
        protected string $securedKey,
    ) {}

    /**
     * Replicates the WooCommerce reference plugin:
     *   sha256("$basketId|$securedKey|$merchantId|$errCode")
     */
    public function validateHash(string $providedHash, string $basketId, string $errCode): bool
    {
        $providedHash = trim($providedHash);

        if ($providedHash === '' || $this->merchantId === '' || $this->securedKey === '') {
            return false;
        }

        return hash_equals($this->expectedHash($basketId, $errCode), $providedHash);
    }

    public function expectedHash(string $basketId, string $errCode): string
    {
        return hash('sha256', sprintf(
            '%s|%s|%s|%s',
            $basketId,
            $this->securedKey,
            $this->merchantId,
            $errCode,
        ));
    }
}

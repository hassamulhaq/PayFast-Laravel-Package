<?php

namespace Payfastlaravelpackage\PayFastLaravelPackage\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Payfastlaravelpackage\PayFastLaravelPackage\Services\HmacSignatureService;
use Payfastlaravelpackage\PayFastLaravelPackage\Support\PaymentLog;

/**
 * Public CORS endpoint. Takes a cart payload from the storefront's client-side JS,
 * signs it with the shared HMAC secret, and returns a signed checkout URL.
 *
 * Keeping the signing here means the HMAC secret never reaches the browser.
 */
class ExternalCheckoutSignController
{
    public function __construct(protected HmacSignatureService $signer) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'checkout_id' => ['required', 'string', 'max:128'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'email' => ['nullable', 'email'],
            'first_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'mobile' => ['nullable', 'string', 'max:32'],
            'return_url' => ['nullable', 'url'],
        ]);

        $params = array_filter([
            'checkout_id' => $data['checkout_id'],
            'amount' => number_format((float) $data['amount'], 2, '.', ''),
            'currency' => strtoupper((string) ($data['currency'] ?? config('payfast-laravel-package.currency', 'PKR'))),
            'email' => $data['email'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'return_url' => $data['return_url'] ?? config('payfast-laravel-package.redirect.success_url'),
            'ts' => (string) Carbon::now()->getTimestamp(),
        ], fn ($v) => $v !== null && $v !== '');

        $params['sig'] = $this->signer->sign($params);

        PaymentLog::info('payfast.external.sign.issued', null, [
            'checkout_id' => $data['checkout_id'],
            'amount' => $params['amount'],
            'origin' => $request->headers->get('origin'),
        ]);

        return response()->json([
            'url' => route('payfast.checkout').'?'.http_build_query($params),
            'expires_in' => (int) config('payfast-laravel-package.external.hmac_ttl_seconds', 600),
        ]);
    }
}

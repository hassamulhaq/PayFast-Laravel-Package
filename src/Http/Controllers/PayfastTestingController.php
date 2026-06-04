<?php

namespace Payfastlaravelpackage\PayFastLaravelPackage\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Payfastlaravelpackage\PayFastLaravelPackage\Services\HmacSignatureService;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Admin-only tester that generates signed checkout URLs from a form.
 * Useful for QA without driving the storefront. Gated by config flag in prod.
 */
class PayfastTestingController
{
    public function __construct(private readonly HmacSignatureService $signer) {}

    public function show(): View
    {
        $this->guardEnvironment();

        return view('payfast-laravel-package::testing.tester', [
            'defaults' => [
                'checkout_id' => (string) Str::uuid(),
                'amount' => '199.50',
                'currency' => config('payfast-laravel-package.currency', 'PKR'),
                'email' => 'shopper@example.com',
                'mobile' => '+923001234567',
                'first_name' => 'Test',
                'last_name' => 'Buyer',
                'return_url' => config('payfast-laravel-package.redirect.success_url'),
            ],
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $this->guardEnvironment();

        $validated = $request->validate([
            'checkout_id' => ['required', 'string', 'max:128'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'string', 'size:3'],
            'email' => ['nullable', 'email'],
            'mobile' => ['nullable', 'string', 'max:32'],
            'first_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'return_url' => ['nullable', 'url'],
        ]);

        $params = array_filter([
            'checkout_id' => $validated['checkout_id'],
            'amount' => number_format((float) $validated['amount'], 2, '.', ''),
            'currency' => strtoupper($validated['currency']),
            'email' => $validated['email'] ?? null,
            'mobile' => $validated['mobile'] ?? null,
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'return_url' => $validated['return_url'] ?? null,
            'ts' => (string) Carbon::now()->getTimestamp(),
        ], fn ($v) => $v !== null && $v !== '');

        $params['sig'] = $this->signer->sign($params);

        return redirect()->away(route('payfast.checkout').'?'.http_build_query($params));
    }

    private function guardEnvironment(): void
    {
        if (app()->isProduction() && ! config('payfast-laravel-package.allow_testing_page', false)) {
            throw new HttpException(404);
        }
    }
}

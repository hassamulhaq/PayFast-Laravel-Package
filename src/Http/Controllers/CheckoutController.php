<?php

namespace Payfastlaravelpackage\PayFastLaravelPackage\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Payfastlaravelpackage\PayFastLaravelPackage\Exceptions\PayfastException;
use Payfastlaravelpackage\PayFastLaravelPackage\Http\Requests\InitiateCheckoutRequest;
use Payfastlaravelpackage\PayFastLaravelPackage\Models\PaymentTransaction;
use Payfastlaravelpackage\PayFastLaravelPackage\Services\PaymentTransactionService;
use Payfastlaravelpackage\PayFastLaravelPackage\Support\PaymentLog;

class CheckoutController
{
    public function __construct(protected PaymentTransactionService $service) {}

    /**
     * Storefront sends the buyer here with a signed query string.
     * Render the review/confirm screen.
     */
    public function show(InitiateCheckoutRequest $request): View
    {
        PaymentLog::info('payfast.checkout.show.entered', null, [
            'checkout_id' => $request->query('checkout_id'),
            'amount' => $request->query('amount'),
            'currency' => $request->query('currency'),
        ]);

        ['transaction' => $txn, 'new_user_created' => $newUserCreated] = $this->service->startFromExternal($request->externalPayload());

        if ($txn->user_id && config('payfast-laravel-package.auto_login_user', true) && ! Auth::check()) {
            Auth::loginUsingId($txn->user_id);
            PaymentLog::info('payfast.checkout.auto_login', $txn, ['user_id' => $txn->user_id]);
        }

        return view('payfast-laravel-package::checkout.show', [
            'transaction' => $txn,
            'pay_url' => route('payfast.initiate', ['uuid' => $txn->uuid]),
            'new_user_created' => $newUserCreated,
            'branding' => config('payfast-laravel-package.branding'),
        ]);
    }

    /**
     * AJAX endpoint the review page hits when the buyer clicks Pay.
     * Fetches a token from PayFast and returns the hidden-field hosted form
     * payload for browser auto-submit.
     */
    public function initiate(Request $request, string $uuid): JsonResponse
    {
        $txn = PaymentTransaction::where('uuid', $uuid)->firstOrFail();

        if ($txn->isTerminal()) {
            return response()->json(['error' => 'Transaction already completed.'], 422);
        }

        PaymentLog::info('payfast.initiate.start', $txn);

        try {
            $payload = $this->service->buildHostedPayload(
                txn: $txn,
                callbackUrl: route('payfast.callback'),
            );
        } catch (PayfastException $e) {
            PaymentLog::error('payfast.initiate.failed', $txn, [
                'error' => $e->getMessage(),
                'context' => $e->context,
            ]);

            $txn->update([
                'status' => PaymentTransaction::STATUS_FAILED,
                'payfast_error_payload' => ['stage' => 'initiate', 'error' => $e->getMessage(), 'context' => $e->context],
                'failed_at' => now(),
            ]);

            return response()->json(['error' => 'Payment gateway authentication failed. Try again.'], 502);
        }

        PaymentLog::info('payfast.initiate.success', $txn);

        return response()->json($payload);
    }
}

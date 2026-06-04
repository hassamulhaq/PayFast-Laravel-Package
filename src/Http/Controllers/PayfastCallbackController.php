<?php

namespace Payfastlaravelpackage\PayFastLaravelPackage\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Mail;
use Payfastlaravelpackage\PayFastLaravelPackage\Models\PaymentTransaction;
use Payfastlaravelpackage\PayFastLaravelPackage\Services\PayfastCallbackValidator;
use Payfastlaravelpackage\PayFastLaravelPackage\Services\WebhookNotifierService;
use Payfastlaravelpackage\PayFastLaravelPackage\Support\PaymentLog;

class PayfastCallbackController
{
    public function __construct(
        protected PayfastCallbackValidator $validator,
        protected WebhookNotifierService $webhookNotifier,
    ) {}

    /**
     * Single endpoint for both server-to-server IPN (authoritative) and
     * browser redirect (UX). IPN updates state; redirect just renders the
     * existing terminal result without re-validating hash.
     */
    public function __invoke(Request $request): View|JsonResponse|HttpResponse
    {
        $payload = $request->all();
        $isRedirect = ($request->input('redirect') === 'Y') || $request->isMethod('GET');

        $basketId = (string) $request->input('basket_id', $request->input('BASKET_ID', ''));
        $errCode = (string) $request->input('err_code', $request->input('ERR_CODE', ''));
        $errMsg = (string) $request->input('err_msg', $request->input('ERR_MSG', ''));
        $transactionId = (string) $request->input('transaction_id', $request->input('TRANSACTION_ID', ''));
        $validationHash = (string) $request->input('validation_hash', $request->input('VALIDATION_HASH', ''));

        PaymentLog::info('payfast.callback.received', null, [
            'basket_id' => $basketId,
            'err_code' => $errCode,
            'is_redirect' => $isRedirect,
            'method' => $request->method(),
        ]);

        $txn = PaymentTransaction::where('basket_id', $basketId)->first();
        if (! $txn) {
            PaymentLog::warning('payfast.callback.unknown_basket', null, ['basket_id' => $basketId]);

            return $this->fail($isRedirect, 'Unknown order.', 404);
        }

        if ($txn->isTerminal()) {
            PaymentLog::info('payfast.callback.already_terminal', $txn, ['is_redirect' => $isRedirect]);

            return $this->respond($txn, $isRedirect, alreadyProcessed: true);
        }

        if (! $this->validator->validateHash($validationHash, $basketId, $errCode)) {
            PaymentLog::warning('payfast.callback.invalid_hash', $txn, [
                'expected' => $this->validator->expectedHash($basketId, $errCode),
                'received' => $validationHash,
                'is_redirect' => $isRedirect,
            ]);

            $txn->update([
                'payfast_error_payload' => ['stage' => 'callback_hash', 'received' => $payload],
            ]);

            return $this->fail($isRedirect, 'Order could not be authorized.', 401);
        }

        PaymentLog::info('payfast.callback.hash_ok', $txn);

        $isSuccess = $errCode === '000';

        $txn->forceFill([
            'payfast_transaction_id' => $transactionId ?: $txn->payfast_transaction_id,
            'payfast_status_code' => $errCode ?: $txn->payfast_status_code,
            'payfast_status_message' => $errMsg ?: $txn->payfast_status_message,
            'payfast_validation_hash' => $validationHash,
            'payment_notification_method' => $isRedirect
                ? PaymentTransaction::NOTIFICATION_REDIRECT
                : PaymentTransaction::NOTIFICATION_IPN,
            'payfast_webhook_payload' => $payload,
            'response_payload' => array_merge((array) $txn->response_payload, [
                ($isRedirect ? 'redirect' : 'ipn') => $payload,
            ]),
        ]);

        if ($isSuccess) {
            $txn->status = PaymentTransaction::STATUS_SUCCESS;
            $txn->completed_at = now();
        } else {
            $txn->status = PaymentTransaction::STATUS_FAILED;
            $txn->failed_at = now();
            $txn->payfast_error_payload = ['stage' => 'callback', 'payload' => $payload];
        }

        $txn->save();

        PaymentLog::info('payfast.callback.state_changed', $txn, ['is_redirect' => $isRedirect]);

        $this->backfillUserProfile($txn);
        $this->sendCustomerEmail($txn);
        $this->webhookNotifier->notify($txn);

        return $this->respond($txn, $isRedirect);
    }

    protected function backfillUserProfile(PaymentTransaction $txn): void
    {
        $user = $txn->user;
        if (! $user) {
            return;
        }

        $dirty = [];
        foreach (['first_name' => 'customer_first_name', 'last_name' => 'customer_last_name', 'mobile' => 'customer_mobile'] as $userCol => $txnCol) {
            if (empty($user->{$userCol}) && ! empty($txn->{$txnCol})) {
                $dirty[$userCol] = $txn->{$txnCol};
            }
        }

        if ($dirty !== []) {
            $user->update($dirty);
            PaymentLog::info('payfast.user.profile_backfilled', $txn, ['fields' => array_keys($dirty)]);
        }
    }

    protected function sendCustomerEmail(PaymentTransaction $txn): void
    {
        $email = $txn->customer_email ?: optional($txn->user)->email;
        if (! $email) {
            return;
        }

        $mailable = $txn->isSuccessful()
            ? config('payfast-laravel-package.mail.payment_receipt')
            : config('payfast-laravel-package.mail.payment_failed');

        if (! $mailable) {
            return;
        }

        try {
            Mail::to($email)->send(new $mailable($txn));
            PaymentLog::info('payfast.customer_email.sent', $txn, ['email' => $email]);
        } catch (\Throwable $e) {
            PaymentLog::warning('payfast.customer_email.failed', $txn, [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function respond(PaymentTransaction $txn, bool $isRedirect, bool $alreadyProcessed = false): View|JsonResponse
    {
        if (! $isRedirect) {
            return response()->json([
                'ok' => true,
                'stored' => ! $alreadyProcessed,
                'status' => $txn->status,
            ]);
        }

        $template = $txn->isSuccessful() ? 'payfast-laravel-package::checkout.success' : 'payfast-laravel-package::checkout.failed';

        return view($template, [
            'transaction' => $txn,
            'redirect_url' => $txn->isSuccessful()
                ? config('payfast-laravel-package.redirect.success_url')
                : config('payfast-laravel-package.redirect.failed_url'),
            'auto_redirect_seconds' => (int) config('payfast-laravel-package.redirect.auto_redirect_seconds', 8),
            'branding' => config('payfast-laravel-package.branding'),
        ]);
    }

    protected function fail(bool $isRedirect, string $message, int $status): View|JsonResponse|HttpResponse
    {
        if (! $isRedirect) {
            return response()->json(['ok' => false, 'error' => $message], $status);
        }

        $response = view('payfast-laravel-package::checkout.failed', [
            'transaction' => null,
            'error' => $message,
            'redirect_url' => config('payfast-laravel-package.redirect.failed_url'),
            'auto_redirect_seconds' => (int) config('payfast-laravel-package.redirect.auto_redirect_seconds', 8),
            'branding' => config('payfast-laravel-package.branding'),
        ]);

        return response($response, $status);
    }
}

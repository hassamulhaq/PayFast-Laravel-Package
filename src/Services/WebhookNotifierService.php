<?php

namespace Payfastlaravelpackage\PayFastLaravelPackage\Services;

use Illuminate\Support\Facades\Http;
use Payfastlaravelpackage\PayFastLaravelPackage\Models\PaymentTransaction;
use Payfastlaravelpackage\PayFastLaravelPackage\Support\PaymentLog;

/**
 * Forwards settled payment status to the storefront's own webhook URL.
 * Payload is HMAC-signed with the same secret used for inbound checkout URLs.
 */
class WebhookNotifierService
{
    public function __construct(
        protected ?string $notifyUrl,
        protected string $secret,
        protected int $timeout = 10,
    ) {}

    public function notify(PaymentTransaction $txn): bool
    {
        if (! $this->notifyUrl) {
            PaymentLog::info('payfast.webhook.skipped', $txn, ['reason' => 'no_url']);

            return false;
        }

        $body = [
            'basket_id' => $txn->basket_id,
            'source_order_id' => $txn->source_order_id,
            'status' => $txn->status,
            'amount' => (string) $txn->amount,
            'currency' => $txn->currency,
            'payfast_transaction_id' => $txn->payfast_transaction_id,
            'payfast_status_code' => $txn->payfast_status_code,
            'completed_at' => optional($txn->completed_at)->toIso8601String(),
            'failed_at' => optional($txn->failed_at)->toIso8601String(),
            'ts' => (string) now()->getTimestamp(),
        ];

        $body['sig'] = $this->sign($body);

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->post($this->notifyUrl, $body);

            if (! $response->successful()) {
                PaymentLog::warning('payfast.webhook.failed', $txn, [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            PaymentLog::info('payfast.webhook.delivered', $txn, ['status' => $response->status()]);

            return true;
        } catch (\Throwable $e) {
            PaymentLog::warning('payfast.webhook.exception', $txn, ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function sign(array $body): string
    {
        ksort($body);

        return hash_hmac('sha256', http_build_query($body), $this->secret);
    }
}

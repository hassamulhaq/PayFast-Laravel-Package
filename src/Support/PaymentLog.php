<?php

namespace Payfastlaravelpackage\PayFastLaravelPackage\Support;

use Illuminate\Support\Facades\Log;
use Payfastlaravelpackage\PayFastLaravelPackage\Models\PaymentTransaction;

/**
 * Channel-pinned, txn-correlated logger for the PayFast flow.
 *
 *   PaymentLog::info('payfast.checkout.show', $txn, ['source' => 'wix']);
 *   PaymentLog::error('payfast.token.failed', $txn, ['status' => 502]);
 */
class PaymentLog
{
    public static function info(string $event, ?PaymentTransaction $txn = null, array $context = []): void
    {
        self::log('info', $event, $txn, $context);
    }

    public static function warning(string $event, ?PaymentTransaction $txn = null, array $context = []): void
    {
        self::log('warning', $event, $txn, $context);
    }

    public static function error(string $event, ?PaymentTransaction $txn = null, array $context = []): void
    {
        self::log('error', $event, $txn, $context);
    }

    public static function debug(string $event, ?PaymentTransaction $txn = null, array $context = []): void
    {
        self::log('debug', $event, $txn, $context);
    }

    private static function log(string $level, string $event, ?PaymentTransaction $txn, array $context): void
    {
        $ctx = [];
        if ($txn) {
            $ctx['uuid'] = $txn->uuid;
            $ctx['basket_id'] = $txn->basket_id;
            $ctx['status'] = $txn->status;
            $ctx['amount'] = (string) $txn->amount;
            $ctx['currency'] = $txn->currency;
            if ($txn->source_order_id) {
                $ctx['source_order_id'] = $txn->source_order_id;
            }
            if ($txn->payfast_transaction_id) {
                $ctx['payfast_transaction_id'] = $txn->payfast_transaction_id;
            }
        }

        Log::channel((string) config('payfast-laravel-package.log_channel', 'stack'))
            ->{$level}($event, array_merge($ctx, $context));
    }
}

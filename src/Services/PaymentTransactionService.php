<?php

namespace Payfastlaravelpackage\PayFastLaravelPackage\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Payfastlaravelpackage\PayFastLaravelPackage\Exceptions\PayfastException;
use Payfastlaravelpackage\PayFastLaravelPackage\Models\PaymentTransaction;
use Payfastlaravelpackage\PayFastLaravelPackage\Support\PaymentLog;

class PaymentTransactionService
{
    public function __construct(
        protected PayfastClient $client,
        protected BasketIdResolver $basketIdResolver,
    ) {}

    /**
     * @return array{transaction: PaymentTransaction, new_user_created: bool}
     */
    public function startFromExternal(array $external): array
    {
        return DB::transaction(function () use ($external) {
            ['user' => $user, 'created' => $newUserCreated] = $this->resolveUser($external);

            $existing = PaymentTransaction::query()
                ->where('source', PaymentTransaction::SOURCE_EXTERNAL)
                ->where('source_order_id', $external['checkout_id'] ?? null)
                ->whereIn('status', [PaymentTransaction::STATUS_PENDING, PaymentTransaction::STATUS_INITIATED])
                ->first();

            if ($existing) {
                $dirty = [];
                foreach (['email' => 'customer_email', 'first_name' => 'customer_first_name', 'last_name' => 'customer_last_name', 'mobile' => 'customer_mobile'] as $extKey => $col) {
                    if (! $existing->{$col} && ! empty($external[$extKey])) {
                        $dirty[$col] = $external[$extKey];
                    }
                }
                if (! $existing->user_id && $user) {
                    $dirty['user_id'] = $user->getAuthIdentifier();
                }
                if ($dirty !== []) {
                    $existing->update($dirty);
                    PaymentLog::info('payfast.checkout.reused.backfilled', $existing, ['fields' => array_keys($dirty)]);
                } else {
                    PaymentLog::info('payfast.checkout.reused', $existing);
                }

                return ['transaction' => $existing, 'new_user_created' => $newUserCreated];
            }

            $resolved = $this->basketIdResolver->resolve(sourceOrderId: $external['checkout_id'] ?? null);

            $txn = PaymentTransaction::create([
                'uuid' => (string) Str::ulid(),
                'source' => PaymentTransaction::SOURCE_EXTERNAL,
                'source_order_id' => $external['checkout_id'] ?? null,
                'basket_id' => $resolved['basket_id'],
                'basket_id_source' => $resolved['strategy'],
                'user_id' => $user?->getAuthIdentifier(),
                'customer_email' => $external['email'] ?? null,
                'customer_mobile' => $external['mobile'] ?? null,
                'customer_first_name' => $external['first_name'] ?? null,
                'customer_last_name' => $external['last_name'] ?? null,
                'amount' => (float) ($external['amount'] ?? 0),
                'currency' => (string) ($external['currency'] ?? config('payfast-laravel-package.currency', 'PKR')),
                'store_id' => config('payfast-laravel-package.store_id'),
                'return_url' => $external['return_url'] ?? config('payfast-laravel-package.redirect.success_url'),
                'success_url' => $external['return_url'] ?? config('payfast-laravel-package.redirect.success_url'),
                'failure_url' => config('payfast-laravel-package.redirect.failed_url'),
                'status' => PaymentTransaction::STATUS_PENDING,
                'request_payload' => ['external' => $external],
            ]);

            PaymentLog::info('payfast.checkout.created', $txn, [
                'basket_id_strategy' => $resolved['strategy'],
                'user_id' => $user?->getAuthIdentifier(),
                'new_user_created' => $newUserCreated,
            ]);

            return ['transaction' => $txn, 'new_user_created' => $newUserCreated];
        });
    }

    public function buildHostedPayload(PaymentTransaction $txn, string $callbackUrl): array
    {
        PaymentLog::info('payfast.token.requesting', $txn);

        try {
            $token = $this->client->getAccessToken(
                basketId: $txn->basket_id,
                amount: (string) $txn->amount,
                currency: (string) $txn->currency,
            );
        } catch (PayfastException $e) {
            PaymentLog::error('payfast.token.failed', $txn, [
                'error' => $e->getMessage(),
                'context' => $e->context,
            ]);
            throw $e;
        }

        PaymentLog::info('payfast.token.received', $txn, ['token_preview' => substr($token, 0, 8).'…']);

        $orderDate = Carbon::now()->format('Y-m-d H:i:s');

        $payload = $this->client->buildHostedCheckoutPayload([
            'token' => $token,
            'basket_id' => $txn->basket_id,
            'amount' => (string) $txn->amount,
            'currency' => $txn->currency,
            'customer_email' => $txn->customer_email ?? '',
            'customer_mobile' => $txn->customer_mobile ?? '',
            'description' => trim('Order '.$txn->basket_id),
            'success_url' => $callbackUrl,
            'failure_url' => $callbackUrl,
            'checkout_url' => $callbackUrl,
            'order_date' => $orderDate,
        ]);

        $txn->update([
            'payfast_token' => $token,
            'payfast_signature' => $payload['SIGNATURE'],
            'order_date' => $orderDate,
            'initiated_at' => Carbon::now(),
            'status' => PaymentTransaction::STATUS_INITIATED,
            'request_payload' => array_merge((array) $txn->request_payload, ['hosted_form' => $this->redact($payload)]),
        ]);

        PaymentLog::info('payfast.hosted_form.built', $txn, ['action_url' => $this->client->postTransactionUrl()]);

        return [
            'action_url' => $this->client->postTransactionUrl(),
            'fields' => $payload,
        ];
    }

    /**
     * @return array{user: ?Authenticatable, created: bool}
     */
    protected function resolveUser(array $external): array
    {
        $email = $external['email'] ?? null;
        if (! $email || ! config('payfast-laravel-package.auto_create_user', true)) {
            return ['user' => null, 'created' => false];
        }

        $userModel = config('payfast-laravel-package.user_model');
        if (! $userModel || ! class_exists($userModel)) {
            return ['user' => null, 'created' => false];
        }

        $existing = $userModel::where('email', $email)->first();
        if ($existing) {
            return ['user' => $existing, 'created' => false];
        }

        $name = trim(($external['first_name'] ?? '').' '.($external['last_name'] ?? '')) ?: $email;
        $plainPassword = $this->generateReadablePassword();

        try {
            $created = $userModel::create([
                'name' => $name,
                'first_name' => $external['first_name'] ?? null,
                'last_name' => $external['last_name'] ?? null,
                'email' => $email,
                'mobile' => $external['mobile'] ?? null,
                'password' => Hash::make($plainPassword),
            ]);

            if (method_exists($created, 'forceFill')) {
                $created->forceFill(['email_verified_at' => now()])->save();
            }

            $role = config('payfast-laravel-package.auto_assign_role');
            if ($role && method_exists($created, 'assignRole')) {
                try {
                    $created->assignRole($role);
                } catch (\Throwable $e) {
                    PaymentLog::warning('payfast.user.role_assign_failed', null, [
                        'email' => $email,
                        'role' => $role,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->sendWelcomeEmail($created, $plainPassword);

            return ['user' => $created, 'created' => true];
        } catch (\Throwable $e) {
            PaymentLog::warning('payfast.user.create_failed', null, [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return ['user' => null, 'created' => false];
        }
    }

    protected function generateReadablePassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $digits = '23456789';
        $word = '';
        for ($i = 0; $i < 4; $i++) {
            $word .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $num = '';
        for ($i = 0; $i < 4; $i++) {
            $num .= $digits[random_int(0, strlen($digits) - 1)];
        }

        return $word.'-'.$num;
    }

    protected function sendWelcomeEmail($user, string $plainPassword): void
    {
        $mailable = config('payfast-laravel-package.mail.welcome_with_credentials');
        if (! $mailable || ! $user?->email) {
            return;
        }

        try {
            Mail::to($user->email)->send(new $mailable($user, $plainPassword));
            PaymentLog::info('payfast.user.welcome_email_sent', null, ['email' => $user->email]);
        } catch (\Throwable $e) {
            PaymentLog::warning('payfast.user.welcome_email_failed', null, [
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function redact(array $payload): array
    {
        if (isset($payload['TOKEN'])) {
            $payload['TOKEN'] = substr((string) $payload['TOKEN'], 0, 8).'…';
        }

        return $payload;
    }
}

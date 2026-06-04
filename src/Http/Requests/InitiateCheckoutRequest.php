<?php

namespace Payfastlaravelpackage\PayFastLaravelPackage\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Payfastlaravelpackage\PayFastLaravelPackage\Services\HmacSignatureService;

class InitiateCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(HmacSignatureService::class)->verify($this->query());
    }

    public function rules(): array
    {
        return [
            'checkout_id' => ['required', 'string', 'max:128'],
            'amount' => ['required', 'numeric', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
            'email' => ['nullable', 'email'],
            'first_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'mobile' => ['nullable', 'string', 'max:32'],
            'return_url' => ['nullable', 'url'],
            'ts' => ['required', 'integer'],
            'sig' => ['required', 'string'],
        ];
    }

    /**
     * Normalised payload passed to PaymentTransactionService::startFromExternal().
     */
    public function externalPayload(): array
    {
        return [
            'checkout_id' => (string) $this->query('checkout_id'),
            'amount' => (string) $this->query('amount'),
            'currency' => (string) ($this->query('currency') ?: config('payfast-laravel-package.currency', 'PKR')),
            'email' => $this->query('email'),
            'first_name' => $this->query('first_name'),
            'last_name' => $this->query('last_name'),
            'mobile' => $this->query('mobile'),
            'return_url' => $this->query('return_url'),
        ];
    }
}

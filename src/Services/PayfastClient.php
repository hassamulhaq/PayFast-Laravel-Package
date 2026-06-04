<?php

namespace Payfastlaravelpackage\PayFastLaravelPackage\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Payfastlaravelpackage\PayFastLaravelPackage\Exceptions\PayfastException;
use Payfastlaravelpackage\PayFastLaravelPackage\Support\RequestId;

class PayfastClient
{
    public function __construct(protected array $config) {}

    public function baseUrl(): string
    {
        $mode = $this->config['mode'] ?? 'sandbox';
        $url = $this->config['base_url'][$mode] ?? null;

        if (! $url) {
            throw PayfastException::missingConfig("base_url.$mode");
        }

        return rtrim((string) $url, '/');
    }

    /**
     * GetAccessToken — uppercase form fields, plus X-Request-ID header.
     *
     * Live API requires CURRENCY_CODE. Sandbox accepts requests without it.
     * Field set matches the working WooCommerce PayFast plugin.
     */
    public function getAccessToken(string $basketId, string|float $amount, ?string $currency = null): string
    {
        $merchantId = (string) ($this->config['merchant_id'] ?? '');
        $securedKey = (string) ($this->config['secured_key'] ?? '');
        $currencyCode = (string) ($currency ?: ($this->config['currency'] ?? 'PKR'));

        if ($merchantId === '' || $securedKey === '') {
            throw PayfastException::missingConfig('merchant_id / secured_key');
        }

        $url = $this->baseUrl().'/Ecommerce/api/Transaction/GetAccessToken';

        $body = sprintf(
            'MERCHANT_ID=%s&SECURED_KEY=%s&TXNAMT=%s&BASKET_ID=%s&CURRENCY_CODE=%s',
            rawurlencode($merchantId),
            rawurlencode($securedKey),
            rawurlencode((string) $amount),
            rawurlencode($basketId),
            rawurlencode($currencyCode),
        );

        $response = $this->httpClient()
            ->withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8',
                'X-Request-ID' => RequestId::generate(),
                'User-Agent' => 'PayfastLaravelPackage/1.0 (Laravel)',
            ])
            ->withBody($body, 'application/x-www-form-urlencoded')
            ->post($url);

        $json = $this->safeJson($response);
        $token = $json['ACCESS_TOKEN'] ?? null;

        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw PayfastException::tokenFetchFailed($response->status(), $json);
        }

        return $token;
    }

    /**
     * Build the auto-submit form payload (hidden inputs) for
     * POST <base>/Ecommerce/api/Transaction/PostTransaction.
     */
    public function buildHostedCheckoutPayload(array $params): array
    {
        $required = ['basket_id', 'amount', 'token', 'order_date', 'success_url', 'failure_url', 'checkout_url'];
        foreach ($required as $key) {
            if (! isset($params[$key]) || $params[$key] === '') {
                throw PayfastException::missingConfig("hosted_payload.$key");
            }
        }

        return [
            'MERCHANT_ID' => (string) ($this->config['merchant_id'] ?? ''),
            'MERCHANT_NAME' => (string) ($this->config['merchant_name'] ?? ''),
            'TOKEN' => (string) $params['token'],
            'PROCCODE' => '00',
            'TXNAMT' => (string) $params['amount'],
            'CUSTOMER_MOBILE_NO' => (string) ($params['customer_mobile'] ?? ''),
            'CUSTOMER_EMAIL_ADDRESS' => (string) ($params['customer_email'] ?? ''),
            'SIGNATURE' => hash('sha256', (string) $params['basket_id']),
            'PLUGIN_VERSION' => (string) ($this->config['plugin_version'] ?? 'PAYFAST-LARAVEL-1.0'),
            'TXNDESC' => (string) ($params['description'] ?? 'Order'),
            'SUCCESS_URL' => (string) $params['success_url'],
            'FAILURE_URL' => (string) $params['failure_url'],
            'BASKET_ID' => (string) $params['basket_id'],
            'ORDER_DATE' => (string) $params['order_date'],
            'CHECKOUT_URL' => (string) $params['checkout_url'],
            'TRAN_TYPE' => 'ECOMM_PURCHASE',
            'STORE_ID' => (string) ($this->config['store_id'] ?? ''),
            'CURRENCY_CODE' => (string) ($params['currency'] ?? $this->config['currency'] ?? 'PKR'),
        ];
    }

    public function postTransactionUrl(): string
    {
        return $this->baseUrl().'/Ecommerce/api/Transaction/PostTransaction';
    }

    protected function httpClient()
    {
        return Http::timeout((int) ($this->config['http']['timeout'] ?? 20))
            ->connectTimeout((int) ($this->config['http']['connect_timeout'] ?? 10))
            ->withOptions([
                'verify' => (bool) ($this->config['http']['verify_ssl'] ?? true),
            ]);
    }

    protected function safeJson(Response $response): array
    {
        try {
            $data = $response->json();
        } catch (\Throwable $e) {
            $data = null;
        }

        return is_array($data) ? $data : ['raw' => (string) $response->body()];
    }
}

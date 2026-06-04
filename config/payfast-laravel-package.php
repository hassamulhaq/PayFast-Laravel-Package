<?php

use Payfastlaravelpackage\PayFastLaravelPackage\Mail\PaymentFailedMail;
use Payfastlaravelpackage\PayFastLaravelPackage\Mail\PaymentReceiptMail;
use Payfastlaravelpackage\PayFastLaravelPackage\Mail\WelcomeWithCredentialsMail;

/*
 * PayFast Pakistan (gopayfast.com) Laravel integration.
 *
 * All values are env-driven so deployments can override without touching files.
 * Publish with:
 *   php artisan vendor:publish --tag="payfast-laravel-package-config"
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Mode
    |--------------------------------------------------------------------------
    | "sandbox" or "production". Picks which base URL is used by the client.
    */
    'mode' => env('PAYFAST_MODE', 'sandbox'),

    'base_url' => [
        'sandbox' => env('PAYFAST_SANDBOX_BASE_URL', 'https://ipguat.apps.net.pk'),
        'production' => env('PAYFAST_PRODUCTION_BASE_URL', 'https://ipg1.apps.net.pk'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Merchant credentials (issued by PayFast)
    |--------------------------------------------------------------------------
    */
    'merchant_id' => env('PAYFAST_MERCHANT_ID'),
    'merchant_name' => env('PAYFAST_MERCHANT_NAME', 'Example Merchant'),
    'secured_key' => env('PAYFAST_SECURED_KEY'),
    'store_id' => env('PAYFAST_STORE_ID'),

    'currency' => env('PAYFAST_CURRENCY', 'PKR'),
    'plugin_version' => env('PAYFAST_PLUGIN_VERSION', 'PAYFAST-LARAVEL-1.0'),

    /*
    |--------------------------------------------------------------------------
    | Basket ID strategy
    |--------------------------------------------------------------------------
    | "laravel" mints a unique ULID-based id; "external" uses whatever id the
    | source storefront supplied via the signed URL.
    */
    'order_id_source' => env('PAYFAST_ORDER_ID_SOURCE', 'laravel'),
    'basket_id_prefix' => env('PAYFAST_BASKET_PREFIX', 'pf_'),

    /*
    |--------------------------------------------------------------------------
    | HTTP behaviour for outbound calls to PayFast
    |--------------------------------------------------------------------------
    */
    'http' => [
        'timeout' => (int) env('PAYFAST_HTTP_TIMEOUT', 20),
        'connect_timeout' => (int) env('PAYFAST_HTTP_CONNECT_TIMEOUT', 10),
        'verify_ssl' => (bool) env('PAYFAST_HTTP_VERIFY_SSL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | External storefront (Wix, Shopify, custom) → Laravel handoff
    |--------------------------------------------------------------------------
    | The signed URL flow uses HMAC-SHA256 to prove the request came from your
    | storefront and was not tampered with. Keep this secret server-side.
    */
    'external' => [
        'hmac_secret' => env('PAYFAST_EXTERNAL_HMAC_SECRET'),
        'hmac_ttl_seconds' => (int) env('PAYFAST_EXTERNAL_HMAC_TTL', 600),
        'allowed_origins' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('PAYFAST_EXTERNAL_ALLOWED_ORIGINS', 'https://example.com,https://www.example.com'))
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | After-payment redirect targets (sent back to the buyer via the UI)
    |--------------------------------------------------------------------------
    */
    'redirect' => [
        'success_url' => env('PAYFAST_SUCCESS_URL', 'https://example.com/checkout/success'),
        'failed_url' => env('PAYFAST_FAILED_URL', 'https://example.com/checkout/failed'),
        'auto_redirect_seconds' => (int) env('PAYFAST_AUTO_REDIRECT_SECONDS', 8),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storefront webhook — optional. If set, the package POSTs payment status
    | back to this URL after every successful/failed callback so the storefront
    | can update its order record.
    |--------------------------------------------------------------------------
    */
    'webhook' => [
        'url' => env('PAYFAST_STOREFRONT_WEBHOOK_URL'),
        'timeout' => (int) env('PAYFAST_STOREFRONT_WEBHOOK_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Customer user model — used to auto-create / auto-login the buyer.
    |--------------------------------------------------------------------------
    | The model must be Authenticatable with at least `name`, `email`,
    | `password` fillable. Set auto_create_user to false to disable.
    */
    'user_model' => env('PAYFAST_USER_MODEL', 'App\\Models\\User'),
    'auto_create_user' => (bool) env('PAYFAST_AUTO_CREATE_USER', true),
    'auto_login_user' => (bool) env('PAYFAST_AUTO_LOGIN_USER', true),
    'auto_assign_role' => env('PAYFAST_AUTO_ASSIGN_ROLE'),

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    | The package ships routes/payfast.php. Set "register" to false if you
    | publish + customise routes yourself.
    */
    'routes' => [
        'register' => (bool) env('PAYFAST_REGISTER_ROUTES', true),
        'prefix' => env('PAYFAST_ROUTE_PREFIX', 'payment'),
        'api_prefix' => env('PAYFAST_API_ROUTE_PREFIX', 'api/payfast'),
        'middleware' => ['web'],
        'api_middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tester UI
    |--------------------------------------------------------------------------
    | Exposes /paymentgateway/testing/payfast for generating signed URLs.
    | Always allowed in non-production; in production set this to true.
    */
    'allow_testing_page' => (bool) env('PAYFAST_ALLOW_TESTING_PAGE', false),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'log_channel' => env('PAYFAST_LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Email
    |--------------------------------------------------------------------------
    | Mailable classes the package will send. Override with your own subclasses
    | if you want different envelopes or queueing.
    */
    'mail' => [
        'welcome_with_credentials' => WelcomeWithCredentialsMail::class,
        'payment_receipt' => PaymentReceiptMail::class,
        'payment_failed' => PaymentFailedMail::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Branding (shown on emails and UI)
    |--------------------------------------------------------------------------
    */
    'branding' => [
        'app_name' => env('PAYFAST_APP_NAME', env('APP_NAME', 'My Store')),
        'support_email' => env('PAYFAST_SUPPORT_EMAIL', 'admin@example.com'),
        'storefront_url' => env('PAYFAST_STOREFRONT_URL', 'https://example.com'),
    ],
];

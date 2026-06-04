# Laravel integration for PayFast

[![Latest Version on Packagist](https://img.shields.io/packagist/v/hassam/payfast-laravel-package.svg?style=flat-square)](https://packagist.org/packages/hassam/payfast-laravel-package)
[![GitHub Tests Action Status](https://github.com/spatie/package-payfast-laravel-package-laravel/actions/workflows/run-tests.yml/badge.svg)](https://github.com/hassam/payfast-laravel-package/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://github.com/spatie/package-payfast-laravel-package-laravel/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/hassam/payfast-laravel-package/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/hassam/payfast-laravel-package.svg?style=flat-square)](https://packagist.org/packages/hassam/payfast-laravel-package)

This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/PayFast-Laravel-Package.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/PayFast-Laravel-Package)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require hassamulhaq/payfast-laravel-package
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="payfast-laravel-package-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="payfast-laravel-package-config"
```

This is the contents of the published config file:

```php
return [
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="payfast-laravel-package-views"
```

## Usage

```php
$payFastLaravelPackage = new Payfastlaravelpackage\PayFastLaravelPackage();
echo $payFastLaravelPackage->echoPhrase('Hello, Payfastlaravelpackage!');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [hassamulhaq](https://github.com/hassamulhaq)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

---

# PayFast (gopayfast.com) — Real-world integration guide

This package wraps the **PayFast Pakistan** hosted-checkout gateway behind a
clean Laravel integration. It was built and battle-tested with a Wix storefront
→ Laravel bridge → PayFast hosted card page → IPN/redirect → back to storefront.

The package handles:

- HMAC-signed external storefront → Laravel handoff (Wix, Shopify, custom).
- Customer auto-create + auto-login on first visit, welcome email with a
  readable temporary password.
- PayFast token fetch (`GetAccessToken`) with the live API's required
  `CURRENCY_CODE` field that the public docs omit.
- Browser-auto-submitting hosted form to PayFast's `PostTransaction`.
- IPN + redirect callback validation (`sha256(basket|key|merchant|err)`),
  idempotent state transitions, customer email, storefront webhook notify-back.
- Drop-in Blade UI for review / success / failed screens (override in your app
  with `payfast-laravel-package::checkout.show` etc.).
- Admin "tester" page that signs URLs from a form for QA.
- Read-only payment history page (own / all).
- Structured logging via `Payfastlaravelpackage\PayFastLaravelPackage\Support\PaymentLog`.

## High-level flow

```
External storefront            Laravel (this package)                PayFast (gopayfast.com)
       │                                  │                                  │
       │  GET /payment/checkout?…&sig=…   │                                  │
       │─────────────────────────────────▶│                                  │
       │                                  │ verify HMAC, create/find user,   │
       │                                  │ auto-login, render review page   │
       │                                  │                                  │
       │   [buyer clicks "Pay"]           │  POST GetAccessToken             │
       │                                  │─────────────────────────────────▶│
       │                                  │  ◀── ACCESS_TOKEN ───────────────│
       │                                  │                                  │
       │                                  │  auto-submit hidden form         │
       │                                  │  POST PostTransaction ──────────▶│  hosted card UI
       │                                  │  GET callback?redirect=Y&…  ◀────│  redirect back
       │                                  │  POST callback (IPN, S2S)   ◀────│  webhook
       │                                  │                                  │
       │                                  │  validate sha256 hash,           │
       │                                  │  store payload, mail buyer,      │
       │                                  │  notify storefront (optional)    │
       │  302 → success_url / failed_url  │                                  │
       │◀─────────────────────────────────│                                  │
```

## Installation

```bash
composer require hassamulhaq/payfast-laravel-package

php artisan vendor:publish --tag="payfast-laravel-package-config"
php artisan vendor:publish --tag="payfast-laravel-package-migrations"
php artisan migrate
```

Optional publishes:

```bash
# Publish routes (for customization). Then set PAYFAST_REGISTER_ROUTES=false.
php artisan vendor:publish --tag="payfast-laravel-package-routes"

# Publish Blade views to override the default checkout UI.
php artisan vendor:publish --tag="payfast-laravel-package-views"

# Publish the JS injector for your storefront's Custom Code.
php artisan vendor:publish --tag="payfast-laravel-package-injector"
```

## Configuration

All knobs are env-driven; the published `config/payfast-laravel-package.php`
documents each block. Minimum viable `.env`:

```dotenv
PAYFAST_MODE=sandbox
PAYFAST_MERCHANT_ID=
PAYFAST_MERCHANT_NAME="Example Merchant"
PAYFAST_SECURED_KEY=
PAYFAST_STORE_ID=
PAYFAST_CURRENCY=PKR

# Storefront handoff (Wix/Shopify/custom client-side JS calls the sign endpoint)
PAYFAST_EXTERNAL_HMAC_SECRET=<long random string>
PAYFAST_EXTERNAL_ALLOWED_ORIGINS=https://example.com,https://www.example.com

# Where the buyer ends up after success / failure
PAYFAST_SUCCESS_URL=https://example.com/checkout/success
PAYFAST_FAILED_URL=https://example.com/checkout/failed

# Optional: notify the storefront back when payment settles
PAYFAST_STOREFRONT_WEBHOOK_URL=
```

### Mode switching

`PAYFAST_MODE=sandbox` uses `https://ipguat.apps.net.pk`.
`PAYFAST_MODE=production` uses `https://ipg1.apps.net.pk`.

> **Live API requires `CURRENCY_CODE` in the token request.** This package always
> sends it; sandbox accepted requests without it, live does not. If you see
> `{"errorCode":"003","errorDescription":"Amount , Basket Id and Currency code must be required"}`,
> your merchant or proxy is stripping the field.

### Basket ID strategy

`PAYFAST_ORDER_ID_SOURCE`:

- `laravel` (default) — mints `pf_<ulid>`. The external `checkout_id` is stored
  on the row as `source_order_id` for reconciliation. Recommended.
- `external` — sends the storefront's `checkout_id` verbatim as `BASKET_ID`.
  Easier to read in the PayFast dashboard, but the storefront must guarantee
  uniqueness.

## Routes (auto-registered unless `PAYFAST_REGISTER_ROUTES=false`)

| Method | URL | Name | Auth |
|---|---|---|---|
| GET | `/payment/checkout` | `payfast.checkout` | HMAC `sig` query (no session) |
| POST | `/payment/payfast/initiate/{uuid}` | `payfast.initiate` | Web session |
| GET, POST | `/payment/payfast/callback` | `payfast.callback` | None (CSRF excluded) — validated by hash |
| POST | `/api/payfast/sign-checkout` | `payfast.api.sign-checkout` | CORS-restricted |
| GET | `/payments` | `payfast.payments.index` | `auth` |
| GET | `/paymentgateway/testing/payfast` | `payfast.testing.show` | `auth` |
| POST | `/paymentgateway/testing/payfast` | `payfast.testing.generate` | `auth` |

The callback route is exempted from CSRF inside `routes/payfast.php`. If you
have a global `validateCsrfTokens(except:)` list in `bootstrap/app.php`, the
package's exemption still applies.

### Signed URL spec (storefront → Laravel)

Query parameters:

```
checkout_id   required   storefront's order/cart id
amount        required   decimal string (e.g. "199.50")
currency      required   3-char ISO
email         optional   enables customer auto-create
first_name    optional
last_name     optional
mobile        optional
return_url    optional   where the buyer ends up post-payment (default: PAYFAST_SUCCESS_URL)
ts            required   unix timestamp (TTL enforced)
sig           required   hmac_sha256(canonical_query, PAYFAST_EXTERNAL_HMAC_SECRET)
```

Canonicalization: alphabetical key sort, urlencode each value, `&`-join,
then HMAC-SHA256. Mirror this on the storefront if you sign there directly.

### Storefront webhook (Laravel → storefront)

If `PAYFAST_STOREFRONT_WEBHOOK_URL` is set, this package POSTs JSON to it after
every settled callback:

```json
{
  "basket_id": "pf_01HXXX…",
  "source_order_id": "wix_abc",
  "status": "success",
  "amount": "199.50",
  "currency": "PKR",
  "payfast_transaction_id": "pf_txn_…",
  "payfast_status_code": "000",
  "completed_at": "2026-05-10T…",
  "failed_at": null,
  "ts": "1715…",
  "sig": "<hmac-sha256(sorted query, PAYFAST_EXTERNAL_HMAC_SECRET)>"
}
```

Verify `sig` on the receiving end the same way before trusting it.

## Storefront integration: JS injector

The package ships a vanilla-JS injector for Wix-style storefronts (works with
Shopify too with minor selector tweaks). Publish it:

```bash
php artisan vendor:publish --tag="payfast-laravel-package-injector"
```

It lands at `public/vendor/payfast/checkout-injector.js`. Drop a `<script src>`
tag into your storefront, or paste the contents into Wix Custom Code, then edit
the `CONFIG` block at the top:

```js
var CONFIG = {
    signEndpoint: 'https://payments.example.com/api/payfast/sign-checkout',
    successUrl: 'https://example.com/checkout/success',
    // ...
};
```

The injector:

1. Hides the storefront's native Place Order button via CSS.
2. Scrapes `checkoutId` (URL), cart total (DOM), and contact fields (inputs or
   collapsed summary text).
3. POSTs to `/api/payfast/sign-checkout`, gets back a signed URL.
4. Re-signs (debounced) when the buyer edits an input.
5. On click, opens the signed URL.

Debug: `window.PAYFAST_DEBUG = true` (default) logs every step.
`window.PAYFAST` is exposed for manual inspection: `PAYFAST.scrape()`,
`PAYFAST.refresh()`, `PAYFAST.mount()`.

## Customer model expectations

By default the package auto-creates a User on first checkout if `email` is
present. The model is read from `payfast-laravel-package.user_model` (default
`App\Models\User`). Required attributes: `name`, `email`, `password` fillable.
Optional: `first_name`, `last_name`, `mobile` columns (the callback backfills
these from the txn after a successful payment).

Disable auto-create:

```dotenv
PAYFAST_AUTO_CREATE_USER=false
PAYFAST_AUTO_LOGIN_USER=false
```

Auto-assign a Spatie role on create:

```dotenv
PAYFAST_AUTO_ASSIGN_ROLE=customer
```

(Requires `spatie/laravel-permission`. Silent no-op if the trait is absent.)

## Logging

All events log to the channel named by `PAYFAST_LOG_CHANNEL` (default
`stack`). Event names are dotted for easy grepping:

```
payfast.checkout.show.entered
payfast.checkout.created / .reused / .reused.backfilled
payfast.checkout.auto_login
payfast.token.requesting / .received / .failed
payfast.hosted_form.built
payfast.initiate.start / .success / .failed
payfast.callback.received / .unknown_basket / .already_terminal
payfast.callback.invalid_hash / .hash_ok / .state_changed
payfast.customer_email.sent / .failed
payfast.user.welcome_email_sent / _failed
payfast.user.profile_backfilled
payfast.user.create_failed
payfast.user.role_assign_failed
payfast.webhook.delivered / .failed / .exception / .skipped
payfast.external.sign.issued
```

Each line carries `uuid`, `basket_id`, `status`, `amount`, `currency`,
`source_order_id`, `payfast_transaction_id` (when a txn is in context).

Trace a single payment:

```bash
tail -f storage/logs/laravel.log | grep "pf_01HXXX…"
```

## Overriding mailables

Subclass any of:

- `Payfastlaravelpackage\PayFastLaravelPackage\Mail\WelcomeWithCredentialsMail`
- `Payfastlaravelpackage\PayFastLaravelPackage\Mail\PaymentReceiptMail`
- `Payfastlaravelpackage\PayFastLaravelPackage\Mail\PaymentFailedMail`

then point the config to your subclass:

```php
'mail' => [
    'welcome_with_credentials' => App\Mail\MyCustomWelcomeMail::class,
    // ...
],
```

## Customising the UI

Override any Blade template by publishing views, then editing
`resources/views/vendor/payfast-laravel-package/`:

- `checkout/show.blade.php`
- `checkout/success.blade.php`
- `checkout/failed.blade.php`
- `history/index.blade.php`
- `testing/tester.blade.php`
- `emails/*.blade.php`

If you use Inertia + React/Vue, replace the controller's `view(...)` call by
extending the controller or publishing the routes file and pointing routes at
your own controllers.

## Reference: WooCommerce plugin

The PayFast API surface this package targets was reverse-engineered from the
official PayFast WooCommerce plugin. Field names (`MERCHANT_ID`, `TXNAMT`,
`BASKET_ID`, `CURRENCY_CODE`) and the callback hash formula
(`sha256(basket|key|merchant|err)`) match what the woo plugin actually sends.
Anything in PayFast's PDF docs that disagrees with the woo plugin is wrong.

Direct-API endpoints (`/customer/validate`, `/transaction`) return 404 on
public sandbox merchants; only `GetAccessToken` and `PostTransaction` (hosted)
are exposed. That's why this package uses the hosted form, not direct card
capture.

## Security notes

- The `PAYFAST_EXTERNAL_HMAC_SECRET` never leaves the server. The storefront
  signs by POSTing to `/api/payfast/sign-checkout`, never by computing locally.
- Signed URLs have a TTL (`PAYFAST_EXTERNAL_HMAC_TTL`, default 600 s).
- The PayFast callback handler validates the merchant→Laravel hash on every
  non-terminal callback. Once a txn is terminal (success/failed/expired),
  the redirect path is rendered without re-checking the hash because PayFast's
  redirect hash uses different inputs from the IPN hash.
- IPN is the authoritative state source. The redirect path is best-effort UX.
- Rotate the secured key + HMAC secret if you ever paste them in chat / Slack
  / a screenshot.

## Deploy checklist

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
npm ci && npm run build   # only if you customised views with Vite assets
```

Set on the host:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `PAYFAST_MODE=production`
- Live merchant credentials
- Real `PAYFAST_EXTERNAL_HMAC_SECRET` (rotated)
- `PAYFAST_ALLOW_TESTING_PAGE=false` (default) hides the tester behind 404 in prod

## Versioning + path-repo development

If you embed this package as a path repository in a monorepo, set
`"symlink": true` so source edits propagate to `vendor/` immediately. With
`"symlink": false` (default for `path` repos when symlinking isn't possible)
you must run `composer reinstall hassamulhaq/payfast-laravel-package` after every
edit, otherwise the consuming app keeps using the stale mirror in `vendor/`.

The package code is plain Spatie-Laravel-Package-Tools — no facade or static
state, all services are bound as singletons by the service provider. Override
any service by re-binding it in your app's `AppServiceProvider::register()`.

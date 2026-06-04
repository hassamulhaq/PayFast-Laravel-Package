<?php

namespace Payfastlaravelpackage\PayFastLaravelPackage;

use Payfastlaravelpackage\PayFastLaravelPackage\Services\BasketIdResolver;
use Payfastlaravelpackage\PayFastLaravelPackage\Services\HmacSignatureService;
use Payfastlaravelpackage\PayFastLaravelPackage\Services\PayfastCallbackValidator;
use Payfastlaravelpackage\PayFastLaravelPackage\Services\PayfastClient;
use Payfastlaravelpackage\PayFastLaravelPackage\Services\WebhookNotifierService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PayFastLaravelPackageServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('payfast-laravel-package')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_payment_transactions_table');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(PayfastClient::class, function ($app) {
            return new PayfastClient($app['config']->get('payfast-laravel-package'));
        });

        $this->app->singleton(PayfastCallbackValidator::class, function ($app) {
            $cfg = $app['config']->get('payfast-laravel-package');

            return new PayfastCallbackValidator(
                merchantId: (string) ($cfg['merchant_id'] ?? ''),
                securedKey: (string) ($cfg['secured_key'] ?? ''),
            );
        });

        $this->app->singleton(HmacSignatureService::class, function ($app) {
            $cfg = $app['config']->get('payfast-laravel-package.external');

            return new HmacSignatureService(
                secret: (string) ($cfg['hmac_secret'] ?? ''),
                ttlSeconds: (int) ($cfg['hmac_ttl_seconds'] ?? 600),
            );
        });

        $this->app->singleton(BasketIdResolver::class, function ($app) {
            $cfg = $app['config']->get('payfast-laravel-package');

            return new BasketIdResolver(
                defaultStrategy: (string) ($cfg['order_id_source'] ?? 'laravel'),
                prefix: (string) ($cfg['basket_id_prefix'] ?? 'pf_'),
            );
        });

        $this->app->singleton(WebhookNotifierService::class, function ($app) {
            $cfg = $app['config']->get('payfast-laravel-package');

            return new WebhookNotifierService(
                notifyUrl: $cfg['webhook']['url'] ?? null,
                secret: (string) ($cfg['external']['hmac_secret'] ?? ''),
                timeout: (int) ($cfg['webhook']['timeout'] ?? 10),
            );
        });
    }

    public function packageBooted(): void
    {
        if (config('payfast-laravel-package.routes.register', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/payfast.php');
        }

        $this->publishes([
            __DIR__.'/../routes/payfast.php' => base_path('routes/payfast.php'),
        ], 'payfast-laravel-package-routes');

        $this->publishes([
            __DIR__.'/../resources/js/checkout-injector.js' => public_path('vendor/payfast/checkout-injector.js'),
        ], 'payfast-laravel-package-injector');
    }
}

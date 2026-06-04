<?php

use Illuminate\Support\Facades\Route;
use Payfastlaravelpackage\PayFastLaravelPackage\Http\Controllers\CheckoutController;
use Payfastlaravelpackage\PayFastLaravelPackage\Http\Controllers\ExternalCheckoutSignController;
use Payfastlaravelpackage\PayFastLaravelPackage\Http\Controllers\PayfastCallbackController;
use Payfastlaravelpackage\PayFastLaravelPackage\Http\Controllers\PayfastTestingController;
use Payfastlaravelpackage\PayFastLaravelPackage\Http\Controllers\PaymentHistoryController;

$cfg = config('payfast-laravel-package.routes');
$prefix = (string) ($cfg['prefix'] ?? 'payment');
$apiPrefix = (string) ($cfg['api_prefix'] ?? 'api/payfast');
$webMw = (array) ($cfg['middleware'] ?? ['web']);
$apiMw = (array) ($cfg['api_middleware'] ?? ['api']);

Route::middleware($webMw)->group(function () use ($prefix) {
    Route::prefix($prefix)->name('payfast.')->group(function () {
        Route::get('checkout', [CheckoutController::class, 'show'])->name('checkout');
        Route::post('payfast/initiate/{uuid}', [CheckoutController::class, 'initiate'])->name('initiate');
        Route::match(['get', 'post'], 'payfast/callback', PayfastCallbackController::class)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->name('callback');
    });

    Route::middleware('auth')->group(function () {
        Route::get('payments', [PaymentHistoryController::class, 'index'])->name('payfast.payments.index');
    });

    Route::middleware(['auth'])->prefix('paymentgateway/testing')->name('payfast.testing.')->group(function () {
        Route::get('payfast', [PayfastTestingController::class, 'show'])->name('show');
        Route::post('payfast', [PayfastTestingController::class, 'generate'])->name('generate');
    });
});

Route::middleware($apiMw)->prefix($apiPrefix)->group(function () {
    Route::post('sign-checkout', ExternalCheckoutSignController::class)->name('payfast.api.sign-checkout');
});

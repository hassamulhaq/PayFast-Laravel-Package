<?php

namespace Payfastlaravelpackage\PayFastLaravelPackage;

use Payfastlaravelpackage\PayFastLaravelPackage\Commands\PayFastLaravelPackageCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PayFastLaravelPackageServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('payfast-laravel-package')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_payfast_laravel_package_table')
            ->hasCommand(PayFastLaravelPackageCommand::class);
    }
}

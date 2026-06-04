<?php

namespace Payfastlaravelpackage\PayFastLaravelPackage\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Payfastlaravelpackage\PayFastLaravelPackage\PayFastLaravelPackage
 */
class PayFastLaravelPackage extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Payfastlaravelpackage\PayFastLaravelPackage\PayFastLaravelPackage::class;
    }
}

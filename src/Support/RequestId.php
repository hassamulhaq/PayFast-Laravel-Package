<?php

namespace Payfastlaravelpackage\PayFastLaravelPackage\Support;

class RequestId
{
    public static function generate(): string
    {
        return hash('sha256', uniqid((string) time(), true));
    }
}

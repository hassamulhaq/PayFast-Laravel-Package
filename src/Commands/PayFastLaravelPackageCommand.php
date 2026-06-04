<?php

namespace Payfastlaravelpackage\PayFastLaravelPackage\Commands;

use Illuminate\Console\Command;

class PayFastLaravelPackageCommand extends Command
{
    public $signature = 'payfast-laravel-package';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}

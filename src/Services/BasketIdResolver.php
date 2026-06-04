<?php

namespace Payfastlaravelpackage\PayFastLaravelPackage\Services;

use Illuminate\Support\Str;

class BasketIdResolver
{
    public function __construct(
        protected string $defaultStrategy = 'laravel',
        protected string $prefix = 'pf_',
    ) {}

    /**
     * @return array{basket_id: string, strategy: string}
     */
    public function resolve(?string $sourceOrderId, ?string $strategyOverride = null): array
    {
        $strategy = $strategyOverride ?: $this->defaultStrategy;

        if ($strategy === 'external' && $sourceOrderId) {
            return ['basket_id' => $sourceOrderId, 'strategy' => 'external'];
        }

        return [
            'basket_id' => $this->prefix.Str::ulid()->toBase32(),
            'strategy' => 'laravel',
        ];
    }
}

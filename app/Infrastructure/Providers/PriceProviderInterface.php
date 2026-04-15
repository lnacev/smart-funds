<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

interface PriceProviderInterface
{
    /**
     * @return array{price: float, currency: string}|null
     */
    public function fetchPrice(string $providerSymbol): ?array;

    /**
     * @param  string[] $symbols
     * @return array<string, array{price: float, currency: string}>
     */
    public function fetchBatch(array $symbols): array;
}

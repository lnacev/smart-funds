<?php

declare(strict_types=1);

namespace App\Domain\Price;

interface PriceRepositoryInterface
{
    /**
     * @return array{price: float, currency: string, fetched_at: \DateTimeImmutable}|null
     */
    public function findBySecurity(int $securityId): ?array;

    public function upsert(int $securityId, float $price, string $currency, \DateTimeImmutable $fetchedAt): void;
}

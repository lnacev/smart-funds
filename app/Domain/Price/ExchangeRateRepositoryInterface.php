<?php

declare(strict_types=1);

namespace App\Domain\Price;

interface ExchangeRateRepositoryInterface
{
    public function findRate(string $from, string $to): ?float;

    public function upsert(string $from, string $to, float $rate, \DateTimeImmutable $fetchedAt): void;
}

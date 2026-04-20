<?php

declare(strict_types=1);

namespace App\Application\Prices;

use App\Domain\Price\ExchangeRateRepositoryInterface;
use App\Domain\Price\PriceRepositoryInterface;
use DateTimeImmutable;

final class PriceService
{
    private const STALE_HOURS = 48;

    public function __construct(
        private readonly PriceRepositoryInterface $priceRepository,
        private readonly ExchangeRateRepositoryInterface $exchangeRateRepository,
    ) {
    }

    /**
     * @return array{price: float, currency: string, fetched_at: DateTimeImmutable}|null
     */
    public function getLatestPrice(int $securityId): ?array
    {
        return $this->priceRepository->findBySecurity($securityId);
    }

    public function getExchangeRate(string $from, string $to): ?float
    {
        return $this->exchangeRateRepository->findRate($from, $to);
    }

    public function convertToCzk(float $amount, string $currency): ?float
    {
        if ($currency === 'CZK') {
            return $amount;
        }
        $rate = $this->exchangeRateRepository->findRate($currency, 'CZK');
        return $rate !== null ? $amount * $rate : null;
    }

    public function getLastFetchedAt(): ?DateTimeImmutable
    {
        return $this->priceRepository->findLastFetchedAt();
    }

    public function isDataStale(?DateTimeImmutable $fetchedAt): bool
    {
        if ($fetchedAt === null) {
            return true;
        }
        $diff = (new DateTimeImmutable())->getTimestamp() - $fetchedAt->getTimestamp();
        return $diff > self::STALE_HOURS * 3600;
    }
}

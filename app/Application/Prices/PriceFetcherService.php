<?php

declare(strict_types=1);

namespace App\Application\Prices;

use App\Domain\Price\ExchangeRateRepositoryInterface;
use App\Domain\Price\PriceRepositoryInterface;
use App\Domain\Security\SecurityRepositoryInterface;
use App\Infrastructure\Providers\AlphaVantageProvider;
use App\Infrastructure\Providers\CoinGeckoProvider;
use App\Infrastructure\Providers\YahooFinanceProvider;
use DateTimeImmutable;

final class PriceFetcherService
{
    private const COOLDOWN_HOURS = 23;

    public function __construct(
        private readonly SecurityRepositoryInterface $securityRepository,
        private readonly PriceRepositoryInterface $priceRepository,
        private readonly ExchangeRateRepositoryInterface $exchangeRateRepository,
        private readonly AlphaVantageProvider $alphaVantage,
        private readonly CoinGeckoProvider $coinGecko,
        private readonly YahooFinanceProvider $yahoo,
    ) {
    }

    /**
     * @return array{ok: int, errors: int, skipped: int}
     */
    public function fetchAll(bool $force = false): array
    {
        $stats = ['ok' => 0, 'errors' => 0, 'skipped' => 0];

        $securities = $this->securityRepository->findAllActive();

        $byProvider = ['alpha_vantage' => [], 'coingecko' => [], 'yahoo' => []];
        foreach ($securities as $security) {
            if (!$force) {
                $existing = $this->priceRepository->findBySecurity($security->id);
                if ($existing !== null) {
                    $age = (new DateTimeImmutable())->getTimestamp() - $existing['fetched_at']->getTimestamp();
                    if ($age < self::COOLDOWN_HOURS * 3600) {
                        $stats['skipped']++;
                        continue;
                    }
                }
            }
            $byProvider[$security->provider][$security->providerSymbol] = $security;
        }

        // Alpha Vantage
        if (!empty($byProvider['alpha_vantage'])) {
            $symbols = \array_keys($byProvider['alpha_vantage']);
            $results = $this->alphaVantage->fetchBatch($symbols);
            foreach ($byProvider['alpha_vantage'] as $symbol => $security) {
                if (isset($results[$symbol])) {
                    $this->priceRepository->upsert(
                        $security->id,
                        $results[$symbol]['price'],
                        $results[$symbol]['currency'],
                        new DateTimeImmutable(),
                    );
                    $stats['ok']++;
                } else {
                    $stats['errors']++;
                    \fwrite(\STDERR, "AlphaVantage: chyba pro {$symbol}\n");
                }
            }
        }

        // CoinGecko
        if (!empty($byProvider['coingecko'])) {
            $symbols = \array_keys($byProvider['coingecko']);
            $results = $this->coinGecko->fetchBatch($symbols);
            foreach ($byProvider['coingecko'] as $symbol => $security) {
                if (isset($results[$symbol])) {
                    $this->priceRepository->upsert(
                        $security->id,
                        $results[$symbol]['price'],
                        $results[$symbol]['currency'],
                        new DateTimeImmutable(),
                    );
                    $stats['ok']++;
                } else {
                    $stats['errors']++;
                    \fwrite(\STDERR, "CoinGecko: chyba pro {$symbol}\n");
                }
            }
        }

        // Yahoo Finance
        if (!empty($byProvider['yahoo'])) {
            $symbols = \array_keys($byProvider['yahoo']);
            $results = $this->yahoo->fetchBatch($symbols);
            foreach ($byProvider['yahoo'] as $symbol => $security) {
                if (isset($results[$symbol])) {
                    $this->priceRepository->upsert(
                        $security->id,
                        $results[$symbol]['price'],
                        $results[$symbol]['currency'],
                        new DateTimeImmutable(),
                    );
                    $stats['ok']++;
                } else {
                    $stats['errors']++;
                    \fwrite(\STDERR, "Yahoo: chyba pro {$symbol}\n");
                }
            }
        }

        $this->fetchExchangeRates();

        return $stats;
    }

    public function fetchExchangeRates(): void
    {
        $rates = $this->alphaVantage->fetchExchangeRates();
        $now = new DateTimeImmutable();
        foreach ($rates as $from => $rate) {
            $this->exchangeRateRepository->upsert($from, 'CZK', $rate, $now);
        }
    }
}

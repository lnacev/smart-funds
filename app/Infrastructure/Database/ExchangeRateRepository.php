<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Domain\Price\ExchangeRateRepositoryInterface;
use Nette\Database\Explorer;

final class ExchangeRateRepository implements ExchangeRateRepositoryInterface
{
    public function __construct(
        private readonly Explorer $database,
    ) {
    }

    public function findRate(string $from, string $to): ?float
    {
        if ($from === $to) {
            return 1.0;
        }

        $row = $this->database->table('exchange_rates')
            ->where('from_currency', $from)
            ->where('to_currency', $to)
            ->fetch();

        return $row !== null ? (float) $row->rate : null;
    }

    public function upsert(string $from, string $to, float $rate, \DateTimeImmutable $fetchedAt): void
    {
        $this->database->query(
            'INSERT INTO exchange_rates (from_currency, to_currency, rate, fetched_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE rate = VALUES(rate), fetched_at = VALUES(fetched_at)',
            $from,
            $to,
            $rate,
            $fetchedAt->format('Y-m-d H:i:s'),
        );
    }
}

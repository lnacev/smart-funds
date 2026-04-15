<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Domain\Price\PriceRepositoryInterface;
use DateTimeImmutable;
use Nette\Database\Explorer;

final class PriceRepository implements PriceRepositoryInterface
{
    public function __construct(
        private readonly Explorer $database,
    ) {
    }

    public function findBySecurity(int $securityId): ?array
    {
        $row = $this->database->table('security_prices')
            ->where('security_id', $securityId)
            ->fetch();

        if ($row === null) {
            return null;
        }

        return [
            'price'      => (float) $row->price,
            'currency'   => $row->currency,
            'fetched_at' => DateTimeImmutable::createFromInterface($row->fetched_at),
        ];
    }

    public function upsert(int $securityId, float $price, string $currency, DateTimeImmutable $fetchedAt): void
    {
        $this->database->query(
            'INSERT INTO security_prices (security_id, price, currency, fetched_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE price = VALUES(price), currency = VALUES(currency), fetched_at = VALUES(fetched_at)',
            $securityId,
            $price,
            $currency,
            $fetchedAt->format('Y-m-d H:i:s'),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Portfolio;

final readonly class PortfolioPosition
{
    public function __construct(
        public ?int $id,
        public int $investorId,
        public int $securityId,
        public float $quantity,
        public float $purchasePrice,
        public string $purchaseCurrency,
        public \DateTimeImmutable $purchasedAt,
        public ?string $note,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}

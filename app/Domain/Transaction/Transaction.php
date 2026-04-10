<?php

declare(strict_types=1);

namespace App\Domain\Transaction;

final class Transaction
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $fundId,
        public readonly int $investorId,
        public readonly float $amount,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }
}

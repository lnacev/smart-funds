<?php

declare(strict_types=1);

namespace App\Domain\Fund;

final class Fund
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }
}

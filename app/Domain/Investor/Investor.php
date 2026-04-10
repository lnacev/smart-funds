<?php

declare(strict_types=1);

namespace App\Domain\Investor;

final class Investor
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }
}

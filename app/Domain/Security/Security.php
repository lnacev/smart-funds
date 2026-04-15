<?php

declare(strict_types=1);

namespace App\Domain\Security;

use DateTimeImmutable;

final readonly class Security
{
    public function __construct(
        public ?int $id,
        public string $ticker,
        public string $name,
        public string $type,
        public string $exchange,
        public string $currency,
        public string $provider,
        public string $providerSymbol,
        public bool $active,
        public DateTimeImmutable $createdAt,
    ) {
    }
}

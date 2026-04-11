<?php

declare(strict_types=1);

namespace App\Domain\User;

final class User
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly string $role,
        public readonly ?int $investorId,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }
}

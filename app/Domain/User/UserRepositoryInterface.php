<?php

declare(strict_types=1);

namespace App\Domain\User;

interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function findById(int $id): ?User;

    public function findByInvestorId(int $investorId): ?User;

    public function save(User $user): int;

    public function deleteByInvestorId(int $investorId): void;
}

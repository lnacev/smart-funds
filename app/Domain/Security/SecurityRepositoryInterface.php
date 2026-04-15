<?php

declare(strict_types=1);

namespace App\Domain\Security;

interface SecurityRepositoryInterface
{
    /** @return mixed[] */
    public function findAll(): array;

    /** @return Security[] */
    public function findAllActive(): array;

    public function findById(int $id): ?Security;

    public function findByTicker(string $ticker): ?Security;

    public function save(Security $security): int;

    public function delete(int $id): void;
}

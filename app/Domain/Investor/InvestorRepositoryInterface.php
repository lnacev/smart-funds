<?php

declare(strict_types=1);

namespace App\Domain\Investor;

interface InvestorRepositoryInterface
{
    /** @return mixed[] */
    public function findAll(): array;

    public function findById(int $id): ?Investor;

    public function save(Investor $investor): int;

    public function delete(int $id): void;
}

<?php

declare(strict_types=1);

namespace App\Domain\Fund;

interface FundRepositoryInterface
{
    /** @return mixed[] */
    public function findAll(): array;

    public function findById(int $id): ?Fund;

    public function save(Fund $fund): void;
}

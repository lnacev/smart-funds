<?php

declare(strict_types=1);

namespace App\Domain\Portfolio;

interface PortfolioRepositoryInterface
{
    /** @return PortfolioPosition[] */
    public function findByInvestorId(int $investorId): array;

    public function findById(int $id): ?PortfolioPosition;

    public function save(PortfolioPosition $position): int;

    public function delete(int $id): void;
}

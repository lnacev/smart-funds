<?php

declare(strict_types=1);

namespace App\Domain\Watchlist;

interface WatchlistRepositoryInterface
{
    /** @return int[] security_id[] */
    public function findSecurityIdsByInvestorId(int $investorId): array;

    public function add(int $investorId, int $securityId): void;

    public function remove(int $investorId, int $securityId): void;

    public function exists(int $investorId, int $securityId): bool;
}

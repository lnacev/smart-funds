<?php

declare(strict_types=1);

namespace App\Application\Watchlist;

use App\Application\Prices\PriceService;
use App\Domain\Security\Security;
use App\Domain\Security\SecurityRepositoryInterface;
use App\Domain\Watchlist\WatchlistRepositoryInterface;

final class WatchlistService
{
    public function __construct(
        private readonly WatchlistRepositoryInterface $watchlistRepository,
        private readonly SecurityRepositoryInterface $securityRepository,
        private readonly PriceService $priceService,
    ) {
    }

    /**
     * @return array{
     *   security: Security,
     *   currentPrice: float|null,
     *   priceCurrency: string|null,
     * }[]
     */
    public function getWatchlistWithPrices(int $investorId): array
    {
        $securityIds = $this->watchlistRepository->findSecurityIdsByInvestorId($investorId);
        $result = [];

        foreach ($securityIds as $securityId) {
            $security = $this->securityRepository->findById($securityId);
            if ($security === null) {
                continue;
            }

            $priceData = $this->priceService->getLatestPrice($securityId);
            $result[] = [
                'security'      => $security,
                'currentPrice'  => $priceData !== null ? $priceData['price'] : null,
                'priceCurrency' => $priceData !== null ? $priceData['currency'] : null,
            ];
        }

        return $result;
    }

    public function add(int $investorId, int $securityId): void
    {
        $this->watchlistRepository->add($investorId, $securityId);
    }

    public function remove(int $investorId, int $securityId): void
    {
        $this->watchlistRepository->remove($investorId, $securityId);
    }

    public function isInWatchlist(int $investorId, int $securityId): bool
    {
        return $this->watchlistRepository->exists($investorId, $securityId);
    }
}

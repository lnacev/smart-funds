<?php

declare(strict_types=1);

namespace App\Application\Portfolio;

use App\Application\Prices\PriceService;
use App\Domain\Portfolio\PortfolioPosition;
use App\Domain\Portfolio\PortfolioRepositoryInterface;
use App\Domain\Security\Security;
use App\Domain\Security\SecurityRepositoryInterface;
use DateTimeImmutable;

final class PortfolioService
{
    public function __construct(
        private readonly PortfolioRepositoryInterface $portfolioRepository,
        private readonly SecurityRepositoryInterface $securityRepository,
        private readonly PriceService $priceService,
    ) {
    }

    /**
     * @return array{
     *   position: PortfolioPosition,
     *   security: Security,
     *   currentPrice: float|null,
     *   valueCzk: float|null,
     *   pnlCzk: float|null,
     *   pnlPercent: float|null,
     * }[]
     */
    public function getPositionsWithValues(int $investorId): array
    {
        $positions = $this->portfolioRepository->findByInvestorId($investorId);
        $result = [];

        foreach ($positions as $position) {
            $security = $this->securityRepository->findById($position->securityId);
            if ($security === null) {
                continue;
            }

            $priceData = $this->priceService->getLatestPrice($position->securityId);
            $currentPrice = $priceData !== null ? $priceData['price'] : null;
            $priceCurrency = $priceData !== null ? $priceData['currency'] : $security->currency;

            $valueCzk = null;
            $pnlCzk = null;
            $pnlPercent = null;

            if ($currentPrice !== null) {
                $valueCzk = $this->priceService->convertToCzk($currentPrice * $position->quantity, $priceCurrency);
                $costCzk  = $this->priceService->convertToCzk($position->purchasePrice * $position->quantity, $position->purchaseCurrency);
                if ($valueCzk !== null && $costCzk !== null && $costCzk > 0) {
                    $pnlCzk     = $valueCzk - $costCzk;
                    $pnlPercent = (($valueCzk / $costCzk) - 1) * 100;
                }
            }

            $result[] = [
                'position'     => $position,
                'security'     => $security,
                'currentPrice' => $currentPrice,
                'valueCzk'     => $valueCzk,
                'pnlCzk'       => $pnlCzk,
                'pnlPercent'   => $pnlPercent,
            ];
        }

        return $result;
    }

    public function addPosition(
        int $investorId,
        int $securityId,
        float $quantity,
        float $purchasePrice,
        string $purchaseCurrency,
        string $purchasedAt,
        ?string $note,
    ): void {
        $position = new PortfolioPosition(
            id:               null,
            investorId:       $investorId,
            securityId:       $securityId,
            quantity:         $quantity,
            purchasePrice:    $purchasePrice,
            purchaseCurrency: \strtoupper($purchaseCurrency),
            purchasedAt:      new DateTimeImmutable($purchasedAt),
            note:             ($note !== '' && $note !== null) ? $note : null,
            createdAt:        new DateTimeImmutable(),
        );
        $this->portfolioRepository->save($position);
    }

    public function deletePosition(int $id): void
    {
        $this->portfolioRepository->delete($id);
    }
}

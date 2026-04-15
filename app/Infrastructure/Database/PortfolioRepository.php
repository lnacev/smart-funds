<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Domain\Portfolio\PortfolioPosition;
use App\Domain\Portfolio\PortfolioRepositoryInterface;
use DateTimeImmutable;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;

final class PortfolioRepository implements PortfolioRepositoryInterface
{
    public function __construct(
        private readonly Explorer $database,
    ) {
    }

    public function findByInvestorId(int $investorId): array
    {
        $rows = $this->database->table('portfolio_positions')
            ->where('investor_id', $investorId)
            ->order('created_at DESC')
            ->fetchAll();

        return \array_map(fn($row) => $this->rowToPosition($row), $rows);
    }

    public function findById(int $id): ?PortfolioPosition
    {
        $row = $this->database->table('portfolio_positions')->get($id);
        return $row !== null ? $this->rowToPosition($row) : null;
    }

    public function save(PortfolioPosition $position): int
    {
        if ($position->id === null) {
            $row = $this->database->table('portfolio_positions')->insert([
                'investor_id'       => $position->investorId,
                'security_id'       => $position->securityId,
                'quantity'          => $position->quantity,
                'purchase_price'    => $position->purchasePrice,
                'purchase_currency' => $position->purchaseCurrency,
                'purchased_at'      => $position->purchasedAt->format('Y-m-d'),
                'note'              => $position->note,
                'created_at'        => $position->createdAt->format('Y-m-d H:i:s'),
            ]);
            return (int) $row->id;
        }

        $this->database->table('portfolio_positions')->get($position->id)?->update([
            'quantity'          => $position->quantity,
            'purchase_price'    => $position->purchasePrice,
            'purchase_currency' => $position->purchaseCurrency,
            'purchased_at'      => $position->purchasedAt->format('Y-m-d'),
            'note'              => $position->note,
        ]);

        return $position->id;
    }

    public function delete(int $id): void
    {
        $this->database->table('portfolio_positions')->get($id)?->delete();
    }

    private function rowToPosition(ActiveRow $row): PortfolioPosition
    {
        return new PortfolioPosition(
            id:               $row->id,
            investorId:       $row->investor_id,
            securityId:       $row->security_id,
            quantity:         (float) $row->quantity,
            purchasePrice:    (float) $row->purchase_price,
            purchaseCurrency: $row->purchase_currency,
            purchasedAt:      DateTimeImmutable::createFromInterface($row->purchased_at),
            note:             $row->note,
            createdAt:        DateTimeImmutable::createFromInterface($row->created_at),
        );
    }
}

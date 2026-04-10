<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Domain\Transaction\Transaction;
use App\Domain\Transaction\TransactionRepositoryInterface;
use Nette\Database\Explorer;

final class TransactionRepository implements TransactionRepositoryInterface
{
    public function __construct(
        private readonly Explorer $database,
    ) {
    }

    public function findAll(): array
    {
        return $this->database
            ->table('transactions')
            ->fetchAll();
    }

    public function findById(int $id): ?Transaction
    {
        $row = $this->database
            ->table('transactions')
            ->get($id);

        if ($row === null) {
            return null;
        }

        return new Transaction(
            id: $row->id,
            fundId: $row->fund_id,
            investorId: $row->investor_id,
            amount: (float) $row->amount,
            createdAt: new \DateTimeImmutable($row->created_at),
        );
    }

    public function save(Transaction $transaction): void
    {
        if ($transaction->id === null) {
            $this->database->table('transactions')->insert([
                'fund_id' => $transaction->fundId,
                'investor_id' => $transaction->investorId,
                'amount' => $transaction->amount,
                'created_at' => $transaction->createdAt->format('Y-m-d H:i:s'),
            ]);
        } else {
            $this->database->table('transactions')->get($transaction->id)?->update([
                'fund_id' => $transaction->fundId,
                'investor_id' => $transaction->investorId,
                'amount' => $transaction->amount,
            ]);
        }
    }

    public function delete(int $id): void
    {
        $this->database->table('transactions')->get($id)?->delete();
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Transaction;

use App\Domain\Transaction\Transaction;
use App\Domain\Transaction\TransactionRepositoryInterface;

final class TransactionService
{
    public function __construct(
        private readonly TransactionRepositoryInterface $transactionRepository,
    ) {
    }

    /** @return mixed[] */
    public function getAll(): array
    {
        return $this->transactionRepository->findAll();
    }

    public function getById(int $id): ?Transaction
    {
        return $this->transactionRepository->findById($id);
    }

    public function create(int $fundId, int $investorId, float $amount): void
    {
        $transaction = new Transaction(
            id: null,
            fundId: $fundId,
            investorId: $investorId,
            amount: $amount,
            createdAt: new \DateTimeImmutable(),
        );

        $this->transactionRepository->save($transaction);
    }

    public function update(int $id, int $fundId, int $investorId, float $amount): void
    {
        $transaction = new Transaction(
            id: $id,
            fundId: $fundId,
            investorId: $investorId,
            amount: $amount,
            createdAt: new \DateTimeImmutable(),
        );

        $this->transactionRepository->save($transaction);
    }

    public function delete(int $id): void
    {
        $this->transactionRepository->delete($id);
    }
}

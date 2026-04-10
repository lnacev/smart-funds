<?php

declare(strict_types=1);

namespace App\Domain\Transaction;

interface TransactionRepositoryInterface
{
    /** @return mixed[] */
    public function findAll(): array;

    public function findById(int $id): ?Transaction;

    public function save(Transaction $transaction): void;

    public function delete(int $id): void;
}

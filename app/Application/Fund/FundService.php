<?php

declare(strict_types=1);

namespace App\Application\Fund;

use App\Domain\Fund\Fund;
use App\Domain\Fund\FundRepositoryInterface;

final class FundService
{
    public function __construct(
        private readonly FundRepositoryInterface $fundRepository,
    ) {
    }

    /** @return mixed[] */
    public function getAll(): array
    {
        return $this->fundRepository->findAll();
    }

    public function getById(int $id): ?Fund
    {
        return $this->fundRepository->findById($id);
    }

    public function create(string $name): void
    {
        $fund = new Fund(
            id: null,
            name: $name,
            createdAt: new \DateTimeImmutable(),
        );

        $this->fundRepository->save($fund);
    }
}

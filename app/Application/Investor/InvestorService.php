<?php

declare(strict_types=1);

namespace App\Application\Investor;

use App\Domain\Investor\Investor;
use App\Domain\Investor\InvestorRepositoryInterface;

final class InvestorService
{
    public function __construct(
        private readonly InvestorRepositoryInterface $investorRepository,
    ) {
    }

    /** @return mixed[] */
    public function getAll(): array
    {
        return $this->investorRepository->findAll();
    }

    public function getById(int $id): ?Investor
    {
        return $this->investorRepository->findById($id);
    }

    public function create(string $name, string $email): void
    {
        $investor = new Investor(
            id: null,
            name: $name,
            email: $email,
            createdAt: new \DateTimeImmutable(),
        );

        $this->investorRepository->save($investor);
    }
}

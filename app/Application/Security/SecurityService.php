<?php

declare(strict_types=1);

namespace App\Application\Security;

use App\Domain\Security\Security;
use App\Domain\Security\SecurityRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;

final class SecurityService
{
    public function __construct(
        private readonly SecurityRepositoryInterface $securityRepository,
    ) {
    }

    /** @return mixed[] ActiveRow[] pro Latte templates */
    public function getAll(): array
    {
        return $this->securityRepository->findAll();
    }

    /** @return Security[] */
    public function getAllActive(): array
    {
        return $this->securityRepository->findAllActive();
    }

    public function getById(int $id): ?Security
    {
        return $this->securityRepository->findById($id);
    }

    public function findOrCreate(
        string $ticker,
        string $name,
        string $type,
        string $exchange,
        string $currency,
        string $provider,
        string $providerSymbol,
    ): int {
        $existing = $this->securityRepository->findByTicker(\strtoupper(\trim($ticker)));
        if ($existing !== null) {
            return $existing->id;
        }
        return $this->create($ticker, $name, $type, $exchange, $currency, $provider, $providerSymbol);
    }

    public function create(
        string $ticker,
        string $name,
        string $type,
        string $exchange,
        string $currency,
        string $provider,
        string $providerSymbol,
    ): int {
        $security = new Security(
            id:             null,
            ticker:         \strtoupper(\trim($ticker)),
            name:           $name,
            type:           $type,
            exchange:       \strtoupper($exchange),
            currency:       \strtoupper($currency),
            provider:       $provider,
            providerSymbol: $providerSymbol,
            active:         true,
            createdAt:      new DateTimeImmutable(),
        );
        return $this->securityRepository->save($security);
    }

    public function update(int $id, string $name, string $exchange, string $providerSymbol, bool $active): void
    {
        $existing = $this->securityRepository->findById($id);
        if ($existing === null) {
            throw new InvalidArgumentException("Security $id nenalezeno.");
        }

        $updated = new Security(
            id:             $id,
            ticker:         $existing->ticker,
            name:           $name,
            type:           $existing->type,
            exchange:       \strtoupper($exchange),
            currency:       $existing->currency,
            provider:       $existing->provider,
            providerSymbol: $providerSymbol,
            active:         $active,
            createdAt:      $existing->createdAt,
        );
        $this->securityRepository->save($updated);
    }

    public function delete(int $id): void
    {
        $this->securityRepository->delete($id);
    }
}

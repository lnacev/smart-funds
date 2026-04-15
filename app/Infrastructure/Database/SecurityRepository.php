<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Domain\Security\Security;
use App\Domain\Security\SecurityRepositoryInterface;
use DateTimeImmutable;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;

final class SecurityRepository implements SecurityRepositoryInterface
{
    public function __construct(
        private readonly Explorer $database,
    ) {
    }

    public function findAll(): array
    {
        return $this->database->table('securities')->order('ticker ASC')->fetchAll();
    }

    public function findAllActive(): array
    {
        $rows = $this->database->table('securities')
            ->where('active', 1)
            ->order('ticker ASC')
            ->fetchAll();

        return \array_map(fn($row) => $this->rowToSecurity($row), $rows);
    }

    public function findById(int $id): ?Security
    {
        $row = $this->database->table('securities')->get($id);
        return $row !== null ? $this->rowToSecurity($row) : null;
    }

    public function findByTicker(string $ticker): ?Security
    {
        $row = $this->database->table('securities')->where('ticker', $ticker)->fetch();
        return $row !== null ? $this->rowToSecurity($row) : null;
    }

    public function save(Security $security): int
    {
        if ($security->id === null) {
            $row = $this->database->table('securities')->insert([
                'ticker'          => $security->ticker,
                'name'            => $security->name,
                'type'            => $security->type,
                'exchange'        => $security->exchange,
                'currency'        => $security->currency,
                'provider'        => $security->provider,
                'provider_symbol' => $security->providerSymbol,
                'active'          => $security->active ? 1 : 0,
                'created_at'      => $security->createdAt->format('Y-m-d H:i:s'),
            ]);
            return (int) $row->id;
        }

        $this->database->table('securities')->get($security->id)?->update([
            'ticker'          => $security->ticker,
            'name'            => $security->name,
            'type'            => $security->type,
            'exchange'        => $security->exchange,
            'currency'        => $security->currency,
            'provider'        => $security->provider,
            'provider_symbol' => $security->providerSymbol,
            'active'          => $security->active ? 1 : 0,
        ]);

        return $security->id;
    }

    public function delete(int $id): void
    {
        $this->database->table('securities')->get($id)?->delete();
    }

    private function rowToSecurity(ActiveRow $row): Security
    {
        return new Security(
            id:             $row->id,
            ticker:         $row->ticker,
            name:           $row->name,
            type:           $row->type,
            exchange:       $row->exchange,
            currency:       $row->currency,
            provider:       $row->provider,
            providerSymbol: $row->provider_symbol,
            active:         (bool) $row->active,
            createdAt:      DateTimeImmutable::createFromInterface($row->created_at),
        );
    }
}

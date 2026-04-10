<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Domain\Fund\Fund;
use App\Domain\Fund\FundRepositoryInterface;
use Nette\Database\Explorer;

final class FundRepository implements FundRepositoryInterface
{
    public function __construct(
        private readonly Explorer $database,
    ) {
    }

    public function findAll(): array
    {
        return $this->database
            ->table('funds')
            ->fetchAll();
    }

    public function findById(int $id): ?Fund
    {
        $row = $this->database
            ->table('funds')
            ->get($id);

        if ($row === null) {
            return null;
        }

        return new Fund(
            id: $row->id,
            name: $row->name,
            createdAt: new \DateTimeImmutable($row->created_at),
        );
    }

    public function save(Fund $fund): void
    {
        if ($fund->id === null) {
            $this->database->table('funds')->insert([
                'name' => $fund->name,
                'created_at' => $fund->createdAt->format('Y-m-d H:i:s'),
            ]);
        } else {
            $this->database->table('funds')->get($fund->id)?->update([
                'name' => $fund->name,
            ]);
        }
    }

    public function delete(int $id): void
    {
        $this->database->table('funds')->get($id)?->delete();
    }
}

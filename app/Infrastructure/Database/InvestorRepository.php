<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Domain\Investor\Investor;
use App\Domain\Investor\InvestorRepositoryInterface;
use Nette\Database\Explorer;

final class InvestorRepository implements InvestorRepositoryInterface
{
    public function __construct(
        private readonly Explorer $database,
    ) {
    }

    public function findAll(): array
    {
        return $this->database
            ->table('investors')
            ->fetchAll();
    }

    public function findById(int $id): ?Investor
    {
        $row = $this->database
            ->table('investors')
            ->get($id);

        if ($row === null) {
            return null;
        }

        return new Investor(
            id: $row->id,
            name: $row->name,
            email: $row->email,
            createdAt: new \DateTimeImmutable($row->created_at),
        );
    }

    public function save(Investor $investor): void
    {
        if ($investor->id === null) {
            $this->database->table('investors')->insert([
                'name' => $investor->name,
                'email' => $investor->email,
                'created_at' => $investor->createdAt->format('Y-m-d H:i:s'),
            ]);
        } else {
            $this->database->table('investors')->get($investor->id)?->update([
                'name' => $investor->name,
                'email' => $investor->email,
            ]);
        }
    }

    public function delete(int $id): void
    {
        $this->database->table('investors')->get($id)?->delete();
    }
}

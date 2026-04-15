<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Domain\Watchlist\WatchlistRepositoryInterface;
use Nette\Database\Explorer;

final class WatchlistRepository implements WatchlistRepositoryInterface
{
    public function __construct(
        private readonly Explorer $database,
    ) {
    }

    public function findSecurityIdsByInvestorId(int $investorId): array
    {
        return $this->database->table('watchlist')
            ->where('investor_id', $investorId)
            ->fetchPairs(null, 'security_id');
    }

    public function add(int $investorId, int $securityId): void
    {
        if ($this->exists($investorId, $securityId)) {
            return;
        }

        $this->database->table('watchlist')->insert([
            'investor_id' => $investorId,
            'security_id' => $securityId,
            'added_at'    => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    public function remove(int $investorId, int $securityId): void
    {
        $this->database->table('watchlist')
            ->where('investor_id', $investorId)
            ->where('security_id', $securityId)
            ->delete();
    }

    public function exists(int $investorId, int $securityId): bool
    {
        return $this->database->table('watchlist')
            ->where('investor_id', $investorId)
            ->where('security_id', $securityId)
            ->count('*') > 0;
    }
}

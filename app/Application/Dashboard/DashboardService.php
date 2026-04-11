<?php

declare(strict_types=1);

namespace App\Application\Dashboard;

use Nette\Database\Explorer;

final class DashboardService
{
    public function __construct(
        private readonly Explorer $database,
    ) {
    }

    /**
     * Vrací globální statistiku — celkový objem, počty transakcí, investorů a fondů.
     * @return array{totalVolume: float, transactionCount: int, investorCount: int, fundCount: int}
     */
    public function getGlobalStats(): array
    {
        return [
            'totalVolume'      => (float) ($this->database->table('transactions')->sum('amount') ?? 0),
            'transactionCount' => $this->database->table('transactions')->count('*'),
            'investorCount'    => $this->database->table('investors')->count('*'),
            'fundCount'        => $this->database->table('funds')->count('*'),
        ];
    }

    /**
     * Vrací per-fond statistiku seřazenou sestupně podle celkového objemu.
     * Fondy bez transakcí jsou zahrnuty s nulovými hodnotami.
     * @return array<int, array{id: int, name: string, investorCount: int, totalAmount: float, avgAmount: float, lastTransaction: ?\DateTimeImmutable}>
     */
    public function getFundStats(): array
    {
        $statsRows = $this->database
            ->table('transactions')
            ->select('fund_id,
                COUNT(DISTINCT investor_id) AS investor_count,
                SUM(amount) AS total_amount,
                AVG(amount) AS avg_amount,
                MAX(created_at) AS last_transaction')
            ->group('fund_id')
            ->fetchAll();

        $statsByFund = [];
        foreach ($statsRows as $row) {
            $statsByFund[$row->fund_id] = [
                'investorCount'   => (int) $row->investor_count,
                'totalAmount'     => (float) $row->total_amount,
                'avgAmount'       => (float) $row->avg_amount,
                'lastTransaction' => $row->last_transaction !== null
                    ? \DateTimeImmutable::createFromInterface($row->last_transaction)
                    : null,
            ];
        }

        $funds = $this->database->table('funds')->order('name')->fetchAll();
        $result = [];
        foreach ($funds as $fund) {
            $stats = $statsByFund[$fund->id] ?? [
                'investorCount'   => 0,
                'totalAmount'     => 0.0,
                'avgAmount'       => 0.0,
                'lastTransaction' => null,
            ];
            $result[] = [
                'id'              => $fund->id,
                'name'            => $fund->name,
                'investorCount'   => $stats['investorCount'],
                'totalAmount'     => $stats['totalAmount'],
                'avgAmount'       => $stats['avgAmount'],
                'lastTransaction' => $stats['lastTransaction'],
            ];
        }

        usort($result, fn($a, $b) => $b['totalAmount'] <=> $a['totalAmount']);

        return $result;
    }
}

<?php

declare(strict_types=1);

namespace App\Presentation\Investor\Presenters;

use App\Application\Fund\FundService;
use App\Domain\Transaction\TransactionRepositoryInterface;

final class DashboardPresenter extends BaseInvestorPresenter
{
    public function __construct(
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly FundService $fundService,
    ) {
    }

    public function actionDefault(): void
    {
        $investorId = (int) $this->getUser()->getIdentity()->getData()['investorId'];

        $transactions = $this->transactionRepository->findByInvestorId($investorId);

        $totalInvested = array_sum(array_map(fn($t) => $t->amount, $transactions));
        $fundIds = array_unique(array_map(fn($t) => $t->fundId, $transactions));

        $fundNames = [];
        foreach ($this->fundService->getAll() as $fund) {
            $fundNames[$fund->id] = $fund->name;
        }

        $this->template->transactions = $transactions;
        $this->template->totalInvested = $totalInvested;
        $this->template->fundCount = count($fundIds);
        $this->template->transactionCount = count($transactions);
        $this->template->fundNames = $fundNames;
    }
}

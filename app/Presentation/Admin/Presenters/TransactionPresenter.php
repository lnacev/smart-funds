<?php

declare(strict_types=1);

namespace App\Presentation\Admin\Presenters;

use App\Application\Transaction\TransactionService;

final class TransactionPresenter extends BaseAdminPresenter
{
    public function __construct(
        private readonly TransactionService $transactionService,
    ) {
    }

    public function actionDefault(): void
    {
        $this->template->transactions = $this->transactionService->getAll();
    }
}

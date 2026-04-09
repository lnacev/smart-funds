<?php

declare(strict_types=1);

namespace App\Presentation\Admin\Presenters;

use App\Application\Investor\InvestorService;

final class InvestorPresenter extends BaseAdminPresenter
{
    public function __construct(
        private readonly InvestorService $investorService,
    ) {
    }

    public function actionDefault(): void
    {
        $this->template->investors = $this->investorService->getAll();
    }
}

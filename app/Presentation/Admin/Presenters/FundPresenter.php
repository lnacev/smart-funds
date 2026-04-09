<?php

declare(strict_types=1);

namespace App\Presentation\Admin\Presenters;

use App\Application\Fund\FundService;

final class FundPresenter extends BaseAdminPresenter
{
    public function __construct(
        private readonly FundService $fundService,
    ) {
    }

    public function actionDefault(): void
    {
        $this->template->funds = $this->fundService->getAll();
    }
}

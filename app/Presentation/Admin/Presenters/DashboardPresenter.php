<?php

declare(strict_types=1);

namespace App\Presentation\Admin\Presenters;

use App\Application\Dashboard\DashboardService;

final class DashboardPresenter extends BaseAdminPresenter
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {
    }

    public function actionDefault(): void
    {
        $this->template->globalStats = $this->dashboardService->getGlobalStats();
        $this->template->fundStats   = $this->dashboardService->getFundStats();
    }
}

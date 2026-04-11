<?php

declare(strict_types=1);

namespace App\Presentation\Investor\Presenters;

use Nette\Application\UI\Presenter;

abstract class BaseInvestorPresenter extends Presenter
{
    public function checkRequirements(mixed $element): void
    {
        parent::checkRequirements($element);
        if (!$this->getUser()->isLoggedIn() || !$this->getUser()->isInRole('investor')) {
            $this->redirect(':Front:Sign:in');
        }
    }
}

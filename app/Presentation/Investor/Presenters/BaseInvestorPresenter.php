<?php

declare(strict_types=1);

namespace App\Presentation\Investor\Presenters;

use Nette\Application\UI\Presenter;

abstract class BaseInvestorPresenter extends Presenter
{
    public function checkRequirements(mixed $element): void
    {
        parent::checkRequirements($element);
        // TODO: add investor authentication check here
    }
}

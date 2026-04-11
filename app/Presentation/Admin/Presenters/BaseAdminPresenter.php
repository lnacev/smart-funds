<?php

declare(strict_types=1);

namespace App\Presentation\Admin\Presenters;

use Nette\Application\UI\Presenter;

abstract class BaseAdminPresenter extends Presenter
{
    public function checkRequirements(mixed $element): void
    {
        parent::checkRequirements($element);
        if (!$this->getUser()->isLoggedIn() || !$this->getUser()->isInRole('admin')) {
            $this->redirect(':Front:Sign:in');
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Presentation\Admin\Presenters;

use Nette\Application\UI\Presenter;

abstract class BaseAdminPresenter extends Presenter
{
    public function checkRequirements(mixed $element): void
    {
        parent::checkRequirements($element);
        // TODO: add admin authentication check here
    }
}

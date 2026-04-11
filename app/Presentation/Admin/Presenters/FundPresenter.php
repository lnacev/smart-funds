<?php

declare(strict_types=1);

namespace App\Presentation\Admin\Presenters;

use App\Application\Fund\FundService;
use Nette\Application\UI\Form;

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

    protected function createComponentFundForm(): Form
    {
        $form = new Form;
        $form->addHidden('id');
        $form->addText('name', 'Název')
            ->setRequired('Zadejte název fondu.');
        $form->addSubmit('save', 'Uložit')
            ->setHtmlAttribute('class', 'btn btn-primary');
        $form->getElementPrototype()->addClass('ajax');

        $form->onSuccess[] = function (Form $form, \stdClass $values): void {
            if ($values->id !== '') {
                $this->fundService->update((int) $values->id, $values->name);
            } else {
                $this->fundService->create($values->name);
            }

            if ($this->isAjax()) {
                $this->template->funds = $this->fundService->getAll();
                $this->redrawControl('list');
                $this->payload->closeModal = true;
            } else {
                $this->redirect('default');
            }
        };

        $form->onError[] = function (Form $form): void {
            if ($this->isAjax()) {
                $this->redrawControl('modal');
            }
        };

        return $form;
    }

    protected function createComponentDeleteForm(): Form
    {
        $form = new Form;
        $form->addProtection();
        $form->addHidden('id');
        $form->addSubmit('delete', 'Smazat')
            ->setHtmlAttribute('class', 'btn btn-danger')
            ->setHtmlAttribute('id', 'deleteSubmit');
        $form->getElementPrototype()->addClass('ajax');

        $form->onSuccess[] = function (Form $form, \stdClass $values): void {
            $this->fundService->delete((int) $values->id);

            if ($this->isAjax()) {
                $this->template->funds = $this->fundService->getAll();
                $this->redrawControl('list');
                $this->payload->closeModal = true;
            } else {
                $this->redirect('default');
            }
        };

        return $form;
    }
}

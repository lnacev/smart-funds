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

    public function handleDelete(int $id): void
    {
        $this->fundService->delete($id);

        if ($this->isAjax()) {
            $this->template->funds = $this->fundService->getAll();
            $this->redrawControl('list');
            $this->payload->closeModal = true;
        } else {
            $this->redirect('default');
        }
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
}

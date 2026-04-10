<?php

declare(strict_types=1);

namespace App\Presentation\Admin\Presenters;

use App\Application\Investor\InvestorService;
use Nette\Application\UI\Form;

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

    public function handleDelete(int $id): void
    {
        $this->investorService->delete($id);

        if ($this->isAjax()) {
            $this->template->investors = $this->investorService->getAll();
            $this->redrawControl('list');
            $this->payload->closeModal = true;
        } else {
            $this->redirect('default');
        }
    }

    protected function createComponentInvestorForm(): Form
    {
        $form = new Form;
        $form->addHidden('id');
        $form->addText('name', 'Jméno')
            ->setRequired('Zadejte jméno investora.');
        $form->addEmail('email', 'E-mail')
            ->setRequired('Zadejte e-mail.');
        $form->addSubmit('save', 'Uložit')
            ->setHtmlAttribute('class', 'btn btn-primary');
        $form->getElementPrototype()->addClass('ajax');

        $form->onSuccess[] = function (Form $form, \stdClass $values): void {
            if ($values->id !== '') {
                $this->investorService->update((int) $values->id, $values->name, $values->email);
            } else {
                $this->investorService->create($values->name, $values->email);
            }

            if ($this->isAjax()) {
                $this->template->investors = $this->investorService->getAll();
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

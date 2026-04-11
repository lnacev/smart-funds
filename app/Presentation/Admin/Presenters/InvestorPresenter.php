<?php

declare(strict_types=1);

namespace App\Presentation\Admin\Presenters;

use App\Application\Investor\InvestorService;
use App\Application\User\UserService;
use Nette\Application\UI\Form;

final class InvestorPresenter extends BaseAdminPresenter
{
    public function __construct(
        private readonly UserService $userService,
        private readonly InvestorService $investorService,
    ) {
    }

    public function actionDefault(): void
    {
        $this->template->investors = $this->investorService->getAll();
    }

    protected function createComponentInvestorForm(): Form
    {
        $form = new Form;
        $form->addHidden('id');
        $form->addText('name', 'Jméno')
            ->setRequired('Zadejte jméno investora.');
        $form->addEmail('email', 'E-mail')
            ->setRequired('Zadejte e-mail.');
        $form->addPassword('password', 'Heslo')
            ->addConditionOn($form['id'], Form::Equal, '')
                ->setRequired('Zadejte heslo pro nového investora.');
        $form->addSubmit('save', 'Uložit')
            ->setHtmlAttribute('class', 'btn btn-primary');
        $form->getElementPrototype()->addClass('ajax');

        $form->onSuccess[] = function (Form $form, \stdClass $values): void {
            if ($values->id !== '') {
                $this->userService->updateInvestor((int) $values->id, $values->name, $values->email);
            } else {
                $this->userService->createInvestor($values->name, $values->email, $values->password);
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
            $this->userService->deleteInvestor((int) $values->id);

            if ($this->isAjax()) {
                $this->template->investors = $this->investorService->getAll();
                $this->redrawControl('list');
                $this->payload->closeModal = true;
            } else {
                $this->redirect('default');
            }
        };

        return $form;
    }
}

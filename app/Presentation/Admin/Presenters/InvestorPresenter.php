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
        $this->template->investorIdsWithAccount = array_flip(
            $this->userService->getInvestorIdsWithAccounts()
        );
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

    protected function createComponentChangePasswordForm(): Form
    {
        $form = new Form;
        $form->addHidden('id');
        $form->addPassword('new_password', 'Nové heslo:')
            ->setRequired('Zadejte heslo.')
            ->addRule(Form::MinLength, 'Minimálně 7 znaků.', 7)
            ->addRule(Form::Pattern, 'Musí obsahovat alespoň 1 velké písmeno.', '.*[A-Z].*');
        $form->addPassword('new_password_confirm', 'Potvrzení hesla:')
            ->setRequired('Potvrďte heslo.')
            ->addRule(Form::Equal, 'Hesla se neshodují.', $form['new_password']);
        $form->addProtection();
        $form->addSubmit('save', 'Uložit')
            ->setHtmlAttribute('class', 'btn btn-primary');
        $form->getElementPrototype()->addClass('ajax');

        $form->onSuccess[] = $this->changePasswordFormSucceeded(...);
        $form->onError[] = function (): void {
            if ($this->isAjax()) {
                $this->redrawControl('passwordModal');
            }
        };

        return $form;
    }

    private function changePasswordFormSucceeded(Form $form, \stdClass $values): void
    {
        $investorId = (int) $values->id;
        $changed = $this->userService->changeInvestorPassword($investorId, $values->new_password);

        if (!$changed) {
            $form->addError('Tento investor nemá přiřazený uživatelský účet.');
            if ($this->isAjax()) {
                $this->redrawControl('passwordModal');
            }
            return;
        }

        if ($this->isAjax()) {
            $this->payload->closeModal = true;
            $this->redrawControl('passwordModal');
        } else {
            $this->redirect('default');
        }
    }

    protected function createComponentAssignUserForm(): Form
    {
        $form = new Form;
        $form->addHidden('id');
        $form->addPassword('password', 'Heslo:')
            ->setRequired('Zadejte heslo.')
            ->addRule(Form::MinLength, 'Minimálně 7 znaků.', 7)
            ->addRule(Form::Pattern, 'Musí obsahovat alespoň 1 velké písmeno.', '.*[A-Z].*');
        $form->addPassword('password_confirm', 'Potvrzení hesla:')
            ->setRequired('Potvrďte heslo.')
            ->addRule(Form::Equal, 'Hesla se neshodují.', $form['password']);
        $form->addProtection();
        $form->addSubmit('save', 'Vytvořit účet')
            ->setHtmlAttribute('class', 'btn btn-primary');
        $form->getElementPrototype()->addClass('ajax');

        $form->onSuccess[] = $this->assignUserFormSucceeded(...);
        $form->onError[] = function (): void {
            if ($this->isAjax()) {
                $this->redrawControl('assignUserModal');
            }
        };

        return $form;
    }

    private function assignUserFormSucceeded(Form $form, \stdClass $values): void
    {
        try {
            $this->userService->assignUserToInvestor((int) $values->id, $values->password);
        } catch (\InvalidArgumentException $e) {
            $form->addError($e->getMessage());
            if ($this->isAjax()) {
                $this->redrawControl('assignUserModal');
            }
            return;
        }

        if ($this->isAjax()) {
            $this->template->investors = $this->investorService->getAll();
            $this->template->investorIdsWithAccount = array_flip(
                $this->userService->getInvestorIdsWithAccounts()
            );
            $this->redrawControl('list');
            $this->redrawControl('assignUserModal');
            $this->payload->closeModal = true;
        } else {
            $this->redirect('default');
        }
    }
}

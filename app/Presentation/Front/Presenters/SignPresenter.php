<?php

declare(strict_types=1);

namespace App\Presentation\Front\Presenters;

use Nette\Application\UI\Form;
use Nette\Security\AuthenticationException;

final class SignPresenter extends BasePresenter
{
    public function actionIn(): void
    {
        if ($this->getUser()->isLoggedIn()) {
            $this->redirectByRole();
        }
    }

    public function actionOut(): void
    {
        $this->getUser()->logout();
        $this->redirect('in');
    }

    protected function createComponentSignInForm(): Form
    {
        $form = new Form;
        $form->addEmail('email', 'E-mail:')
            ->setRequired('Zadejte e-mail.');
        $form->addPassword('password', 'Heslo:')
            ->setRequired('Zadejte heslo.');
        $form->addSubmit('send', 'Přihlásit se')
            ->setHtmlAttribute('class', 'btn btn-primary w-100');

        $form->onSuccess[] = function (Form $form, \stdClass $values): void {
            try {
                $this->getUser()->login($values->email, $values->password);
                $this->redirectByRole();
            } catch (AuthenticationException $e) {
                $form->addError($e->getMessage());
            }
        };

        return $form;
    }

    private function redirectByRole(): void
    {
        if ($this->getUser()->isInRole('admin')) {
            $this->redirect(':Admin:Dashboard:default');
        } else {
            $this->redirect(':Investor:Dashboard:default');
        }
    }
}

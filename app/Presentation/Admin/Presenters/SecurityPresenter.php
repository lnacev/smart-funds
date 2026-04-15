<?php

declare(strict_types=1);

namespace App\Presentation\Admin\Presenters;

use App\Application\Prices\PriceFetcherService;
use App\Application\Security\SecurityService;
use Nette\Application\UI\Form;

final class SecurityPresenter extends BaseAdminPresenter
{
    public function __construct(
        private readonly SecurityService $securityService,
        private readonly PriceFetcherService $priceFetcherService,
    ) {
    }

    public function actionDefault(): void
    {
        $this->template->securities = $this->securityService->getAll();
    }

    public function handleFetchNow(): void
    {
        $result = $this->priceFetcherService->fetchAll(force: true);
        $this->flashMessage(
            "Fetch dokončen — OK: {$result['ok']}, Chyby: {$result['errors']}",
            $result['errors'] > 0 ? 'warning' : 'success',
        );
        $this->redirect('default');
    }

    protected function createComponentSecurityForm(): Form
    {
        $form = new Form;
        $form->addHidden('id');
        $form->addText('ticker', 'Ticker')
            ->setRequired('Zadejte ticker (AAPL, BTC...).')
            ->setHtmlAttribute('placeholder', 'AAPL');
        $form->addText('name', 'Název')
            ->setRequired('Zadejte název.')
            ->setHtmlAttribute('placeholder', 'Apple Inc.');
        $form->addSelect('type', 'Typ', ['stock' => 'Akcie', 'etf' => 'ETF', 'crypto' => 'Krypto'])
            ->setRequired();
        $form->addText('exchange', 'Burza')
            ->setRequired('Zadejte burzu.')
            ->setHtmlAttribute('placeholder', 'NYSE');
        $form->addText('currency', 'Měna')
            ->setRequired('Zadejte měnu (USD, EUR, CZK).')
            ->setHtmlAttribute('placeholder', 'USD');
        $form->addSelect('provider', 'Provider', [
            'alpha_vantage' => 'Alpha Vantage',
            'coingecko'     => 'CoinGecko',
            'yahoo'         => 'Yahoo Finance',
        ])->setRequired();
        $form->addText('provider_symbol', 'Symbol pro API')
            ->setRequired('Zadejte symbol pro API.')
            ->setHtmlAttribute('placeholder', 'AAPL / bitcoin / CEZ.PR');
        $form->addCheckbox('active', 'Aktivní');
        $form->addSubmit('save', 'Uložit')
            ->setHtmlAttribute('class', 'btn btn-primary');
        $form->getElementPrototype()->addClass('ajax');

        $form->onSuccess[] = function (Form $form, \stdClass $values): void {
            if ($values->id !== '') {
                $this->securityService->update(
                    (int) $values->id,
                    $values->name,
                    $values->exchange,
                    $values->provider_symbol,
                    (bool) $values->active,
                );
            } else {
                $this->securityService->create(
                    $values->ticker,
                    $values->name,
                    $values->type,
                    $values->exchange,
                    $values->currency,
                    $values->provider,
                    $values->provider_symbol,
                );
            }

            if ($this->isAjax()) {
                $this->template->securities = $this->securityService->getAll();
                $this->redrawControl('list');
                $this->payload->closeModal = true;
            } else {
                $this->redirect('default');
            }
        };

        $form->onError[] = function (): void {
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
            $this->securityService->delete((int) $values->id);

            if ($this->isAjax()) {
                $this->template->securities = $this->securityService->getAll();
                $this->redrawControl('list');
                $this->payload->closeModal = true;
            } else {
                $this->redirect('default');
            }
        };

        return $form;
    }
}

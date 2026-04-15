<?php

declare(strict_types=1);

namespace App\Presentation\Investor\Presenters;

use App\Application\Fund\FundService;
use App\Application\Portfolio\PortfolioService;
use App\Application\Prices\PriceFetcherService;
use App\Application\Prices\PriceService;
use App\Application\Security\SecurityService;
use App\Application\Watchlist\WatchlistService;
use App\Domain\Transaction\TransactionRepositoryInterface;
use Nette\Application\UI\Form;

final class DashboardPresenter extends BaseInvestorPresenter
{
    public function __construct(
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly FundService $fundService,
        private readonly PortfolioService $portfolioService,
        private readonly WatchlistService $watchlistService,
        private readonly SecurityService $securityService,
        private readonly PriceService $priceService,
        private readonly PriceFetcherService $priceFetcherService,
    ) {
    }

    public function actionDefault(): void
    {
        $investorId = $this->getInvestorId();

        // -- Transakce (stávající) --
        $transactions = $this->transactionRepository->findByInvestorId($investorId);
        $totalInvested = array_sum(array_map(fn($t) => $t->amount, $transactions));
        $fundIds = array_unique(array_map(fn($t) => $t->fundId, $transactions));
        $fundNames = [];
        foreach ($this->fundService->getAll() as $fund) {
            $fundNames[$fund->id] = $fund->name;
        }
        $this->template->transactions     = $transactions;
        $this->template->totalInvested    = $totalInvested;
        $this->template->fundCount        = count($fundIds);
        $this->template->transactionCount = count($transactions);
        $this->template->fundNames        = $fundNames;

        // -- Portfolio --
        $positions = $this->portfolioService->getPositionsWithValues($investorId);
        $this->template->positions = $positions;

        $totalPortfolioCzk = array_sum(array_filter(array_map(fn($p) => $p['valueCzk'], $positions)));
        $this->template->totalPortfolioCzk = $totalPortfolioCzk;

        // -- Watchlist --
        $this->template->watchlist = $this->watchlistService->getWatchlistWithPrices($investorId);

        // -- Securities pro formuláře --
        $this->template->availableSecurities = $this->securityService->getAllActive();

        // -- Stav cen --
        $this->template->priceService = $this->priceService;

        // -- Refresh cooldown --
        $session = $this->getSession('prices');
        $lastRefresh = $session->get('lastRefresh');
        $this->template->canRefresh = $lastRefresh === null
            || (time() - $lastRefresh) > 3600;
    }

    public function handleRefreshPrices(): void
    {
        $session = $this->getSession('prices');
        $lastRefresh = $session->get('lastRefresh');

        if ($lastRefresh !== null && (time() - $lastRefresh) < 3600) {
            $this->flashMessage('Ceny byly aktualizovány nedávno. Zkuste za hodinu.', 'warning');
            $this->redirect('default');
            return;
        }

        $result = $this->priceFetcherService->fetchAll(force: true);
        $session->set('lastRefresh', time());

        $this->flashMessage("Ceny aktualizovány — OK: {$result['ok']}, Chyby: {$result['errors']}", 'success');
        $this->redirect('default');
    }

    protected function createComponentAddPositionForm(): Form
    {
        $securities = $this->securityService->getAllActive();
        $options = [];
        foreach ($securities as $s) {
            $options[$s->id] = "{$s->ticker} — {$s->name}";
        }

        $form = new Form;
        $form->addHidden('security_id')->setRequired();
        $form->addSelect('security_id_display', 'Cenný papír', $options)
            ->setRequired('Vyberte cenný papír.');
        $form->addText('quantity', 'Počet kusů')
            ->setRequired('Zadejte počet.')
            ->addRule(Form::Float, 'Musí být číslo.')
            ->addRule(Form::Min, 'Musí být kladné.', 0.000001)
            ->setHtmlAttribute('step', 'any');
        $form->addText('purchase_price', 'Nákupní cena za kus')
            ->setRequired('Zadejte cenu.')
            ->addRule(Form::Float, 'Musí být číslo.')
            ->addRule(Form::Min, 'Musí být kladná.', 0.01)
            ->setHtmlAttribute('step', 'any');
        $form->addSelect('purchase_currency', 'Měna', ['USD' => 'USD', 'EUR' => 'EUR', 'CZK' => 'CZK'])
            ->setDefaultValue('USD');
        $form->addText('purchased_at', 'Datum nákupu')
            ->setRequired('Zadejte datum.')
            ->setHtmlAttribute('type', 'date');
        $form->addText('note', 'Poznámka (volitelné)')
            ->setMaxLength(255);
        $form->addSubmit('save', 'Přidat')
            ->setHtmlAttribute('class', 'btn btn-primary');
        $form->getElementPrototype()->addClass('ajax');

        $form->onSuccess[] = function (Form $form, \stdClass $values): void {
            $investorId = $this->getInvestorId();
            $this->portfolioService->addPosition(
                $investorId,
                (int) $values->security_id,
                (float) str_replace(',', '.', $values->quantity),
                (float) str_replace(',', '.', $values->purchase_price),
                $values->purchase_currency,
                $values->purchased_at,
                $values->note !== '' ? $values->note : null,
            );

            if ($this->isAjax()) {
                $positions = $this->portfolioService->getPositionsWithValues($investorId);
                $this->template->positions = $positions;
                $this->template->totalPortfolioCzk = array_sum(
                    array_filter(array_map(fn($p) => $p['valueCzk'], $positions))
                );
                $this->redrawControl('portfolioContent');
                $this->payload->closeModal = true;
            } else {
                $this->redirect('default');
            }
        };

        $form->onError[] = function (): void {
            if ($this->isAjax()) {
                $this->redrawControl('addPositionModal');
            }
        };

        return $form;
    }

    protected function createComponentAddWatchlistForm(): Form
    {
        $securities = $this->securityService->getAllActive();
        $options = [];
        foreach ($securities as $s) {
            $options[$s->id] = "{$s->ticker} — {$s->name}";
        }

        $form = new Form;
        $form->addSelect('security_id', 'Cenný papír', $options)
            ->setRequired('Vyberte cenný papír.');
        $form->addSubmit('save', 'Přidat')
            ->setHtmlAttribute('class', 'btn btn-primary');
        $form->getElementPrototype()->addClass('ajax');

        $form->onSuccess[] = function (Form $form, \stdClass $values): void {
            $investorId = $this->getInvestorId();
            $this->watchlistService->add($investorId, (int) $values->security_id);

            if ($this->isAjax()) {
                $this->template->watchlist = $this->watchlistService->getWatchlistWithPrices($investorId);
                $this->redrawControl('watchlistContent');
                $this->payload->closeModal = true;
            } else {
                $this->redirect('default');
            }
        };

        $form->onError[] = function (): void {
            if ($this->isAjax()) {
                $this->redrawControl('addWatchlistModal');
            }
        };

        return $form;
    }

    public function handleRemoveWatchlist(int $securityId): void
    {
        $investorId = $this->getInvestorId();
        $this->watchlistService->remove($investorId, $securityId);

        if ($this->isAjax()) {
            $this->template->watchlist = $this->watchlistService->getWatchlistWithPrices($investorId);
            $this->redrawControl('watchlistContent');
        } else {
            $this->redirect('default');
        }
    }

    public function handleDeletePosition(int $id): void
    {
        $this->portfolioService->deletePosition($id);

        if ($this->isAjax()) {
            $investorId = $this->getInvestorId();
            $positions = $this->portfolioService->getPositionsWithValues($investorId);
            $this->template->positions = $positions;
            $this->template->totalPortfolioCzk = array_sum(
                array_filter(array_map(fn($p) => $p['valueCzk'], $positions))
            );
            $this->redrawControl('portfolioContent');
        } else {
            $this->redirect('default');
        }
    }

    private function getInvestorId(): int
    {
        return (int) $this->getUser()->getIdentity()->getData()['investorId'];
    }
}

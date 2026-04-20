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
use App\Infrastructure\Providers\AlphaVantageProvider;
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
        private readonly AlphaVantageProvider $alphaVantage,
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

        // -- Stav cen --
        $this->template->priceService = $this->priceService;

        // -- Refresh cooldown (globální, z DB) --
        $lastFetch = $this->priceService->getLastFetchedAt();
        $hasMissingPrices = \array_filter($positions, fn($p) => $p['currentPrice'] === null) !== []
            || \array_filter($this->template->watchlist, fn($w) => $w['currentPrice'] === null) !== [];
        $this->template->canRefresh = $hasMissingPrices
            || $lastFetch === null
            || (time() - $lastFetch->getTimestamp()) > 3600;
    }

    public function handleSearchTicker(string $q = ''): void
    {
        if (\strlen($q) < 2) {
            $this->sendJson([]);
        }

        $matches = $this->alphaVantage->searchSymbols($q);
        $out = [];
        foreach ($matches as $m) {
            $out[] = [
                'symbol'   => $m['1. symbol'] ?? '',
                'name'     => $m['2. name'] ?? '',
                'type'     => $m['3. type'] ?? '',
                'region'   => $m['4. region'] ?? '',
                'currency' => $m['8. currency'] ?? 'USD',
            ];
        }
        $this->sendJson($out);
    }

    public function handleRefreshPrices(): void
    {
        $investorId = $this->getInvestorId();
        $positions = $this->portfolioService->getPositionsWithValues($investorId);
        $watchlist = $this->watchlistService->getWatchlistWithPrices($investorId);
        $hasMissing = \array_filter($positions, fn($p) => $p['currentPrice'] === null) !== []
            || \array_filter($watchlist, fn($w) => $w['currentPrice'] === null) !== [];

        $lastFetch = $this->priceService->getLastFetchedAt();
        if (!$hasMissing && $lastFetch !== null && (time() - $lastFetch->getTimestamp()) < 3600) {
            $this->flashMessage('Ceny byly aktualizovány nedávno. Zkuste za hodinu.', 'warning');
            $this->redirect('default');
            return;
        }

        $result = $this->priceFetcherService->fetchAll(force: true);

        if (!empty($result['rateLimited'])) {
            $this->flashMessage('Alpha Vantage: denní limit API volání byl vyčerpán. Zkuste zítra.', 'warning');
        } else {
            $this->flashMessage("Ceny aktualizovány — OK: {$result['ok']}, Chyby: {$result['errors']}", $result['errors'] > 0 ? 'warning' : 'success');
        }
        $this->redirect('default');
    }

    protected function createComponentAddPositionForm(): Form
    {
        $form = new Form;
        $form->addText('ticker', 'Ticker')
            ->setRequired('Zadejte ticker.')
            ->setHtmlAttribute('placeholder', 'Hledat ticker…')
            ->setHtmlAttribute('autocomplete', 'off');
        $form->addHidden('security_name');
        $form->addHidden('security_type');
        $form->addHidden('security_exchange');
        $form->addHidden('security_currency');
        $form->addHidden('security_provider');
        $form->addHidden('security_provider_symbol');
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

            $securityId = $this->securityService->findOrCreate(
                $values->ticker,
                $values->security_name ?: $values->ticker,
                $values->security_type ?: 'stock',
                $values->security_exchange ?: 'NYSE',
                $values->security_currency ?: 'USD',
                $values->security_provider ?: 'alpha_vantage',
                $values->security_provider_symbol ?: $values->ticker,
            );

            $this->portfolioService->addPosition(
                $investorId,
                $securityId,
                (float) $values->quantity,
                (float) $values->purchase_price,
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
        $form = new Form;
        $form->addText('ticker', 'Ticker')
            ->setRequired('Zadejte ticker.')
            ->setHtmlAttribute('placeholder', 'Hledat ticker…')
            ->setHtmlAttribute('autocomplete', 'off');
        $form->addHidden('security_name');
        $form->addHidden('security_type');
        $form->addHidden('security_exchange');
        $form->addHidden('security_currency');
        $form->addHidden('security_provider');
        $form->addHidden('security_provider_symbol');
        $form->addSubmit('save', 'Přidat')
            ->setHtmlAttribute('class', 'btn btn-primary');
        $form->getElementPrototype()->addClass('ajax');

        $form->onSuccess[] = function (Form $form, \stdClass $values): void {
            $investorId = $this->getInvestorId();

            $securityId = $this->securityService->findOrCreate(
                $values->ticker,
                $values->security_name ?: $values->ticker,
                $values->security_type ?: 'stock',
                $values->security_exchange ?: 'NYSE',
                $values->security_currency ?: 'USD',
                $values->security_provider ?: 'alpha_vantage',
                $values->security_provider_symbol ?: $values->ticker,
            );

            $this->watchlistService->add($investorId, $securityId);

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

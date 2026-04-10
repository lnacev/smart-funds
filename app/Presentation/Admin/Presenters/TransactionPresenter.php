<?php

declare(strict_types=1);

namespace App\Presentation\Admin\Presenters;

use App\Application\Fund\FundService;
use App\Application\Investor\InvestorService;
use App\Application\Transaction\TransactionService;
use Nette\Application\UI\Form;

final class TransactionPresenter extends BaseAdminPresenter
{
    public function __construct(
        private readonly TransactionService $transactionService,
        private readonly FundService $fundService,
        private readonly InvestorService $investorService,
    ) {
    }

    public function actionDefault(): void
    {
        $this->template->transactions = $this->transactionService->getAll();
        $this->template->selectFunds = $this->buildSelectOptions($this->fundService->getAll(), 'name');
        $this->template->selectInvestors = $this->buildSelectOptions($this->investorService->getAll(), 'name');
    }

    public function handleDelete(int $id): void
    {
        $this->transactionService->delete($id);

        if ($this->isAjax()) {
            $this->template->transactions = $this->transactionService->getAll();
            $this->template->selectFunds = $this->buildSelectOptions($this->fundService->getAll(), 'name');
            $this->template->selectInvestors = $this->buildSelectOptions($this->investorService->getAll(), 'name');
            $this->redrawControl('list');
            $this->payload->closeModal = true;
        } else {
            $this->redirect('default');
        }
    }

    protected function createComponentTransactionForm(): Form
    {
        $fundOptions = $this->buildSelectOptions($this->fundService->getAll(), 'name');
        $investorOptions = $this->buildSelectOptions($this->investorService->getAll(), 'name');

        $form = new Form;
        $form->addHidden('id');
        $form->addSelect('fund_id', 'Fond', $fundOptions)
            ->setPrompt('— vyberte fond —')
            ->setRequired('Vyberte fond.');
        $form->addSelect('investor_id', 'Investor', $investorOptions)
            ->setPrompt('— vyberte investora —')
            ->setRequired('Vyberte investora.');
        $form->addText('amount', 'Částka')
            ->setRequired('Zadejte částku.')
            ->addRule(Form::Float, 'Částka musí být číslo.');
        $form->addSubmit('save', 'Uložit')
            ->setHtmlAttribute('class', 'btn btn-primary');
        $form->getElementPrototype()->addClass('ajax');

        $form->onSuccess[] = function (Form $form, \stdClass $values): void {
            if ($values->id !== '') {
                $this->transactionService->update(
                    (int) $values->id,
                    (int) $values->fund_id,
                    (int) $values->investor_id,
                    (float) $values->amount,
                );
            } else {
                $this->transactionService->create(
                    (int) $values->fund_id,
                    (int) $values->investor_id,
                    (float) $values->amount,
                );
            }

            if ($this->isAjax()) {
                $this->template->transactions = $this->transactionService->getAll();
                $this->template->selectFunds = $this->buildSelectOptions($this->fundService->getAll(), 'name');
                $this->template->selectInvestors = $this->buildSelectOptions($this->investorService->getAll(), 'name');
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

    /**
     * @param mixed[] $rows
     * @return array<int, string>
     */
    private function buildSelectOptions(array $rows, string $labelColumn): array
    {
        $options = [];
        foreach ($rows as $row) {
            $options[$row->id] = $row->$labelColumn;
        }
        return $options;
    }
}

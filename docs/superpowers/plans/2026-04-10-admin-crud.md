# Admin CRUD UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementovat plné CRUD rozhraní pro Fund, Investor a Transaction v admin modulu s Bootstrap 5, Nette Forms a AJAX (naja) — formuláře jako Bootstrap modaly, akce bez překreslení stránky.

**Architecture:** Každý presenter obsahuje form komponentu (`createComponent*Form`), signál `handleDelete`, a AJAX snippet redraw (`list` po úspěchu, `modal` při validační chybě). Naja zachytí submit a odešle jako AJAX; Nette vrátí JSON se snippety a payload `closeModal`.

**Tech Stack:** PHP 8.5, Nette 3.2, Nette Forms, Latte 3, Bootstrap 5.3 (CDN), naja 2.x (CDN)

---

## Přehled souborů

| Soubor | Akce |
|--------|------|
| `app/Domain/Fund/FundRepositoryInterface.php` | + `delete()` |
| `app/Domain/Investor/InvestorRepositoryInterface.php` | + `delete()` |
| `app/Domain/Transaction/TransactionRepositoryInterface.php` | + `delete()` |
| `app/Infrastructure/Database/FundRepository.php` | + `delete()` |
| `app/Infrastructure/Database/InvestorRepository.php` | + `delete()` |
| `app/Infrastructure/Database/TransactionRepository.php` | + `delete()` |
| `app/Application/Fund/FundService.php` | + `update()`, `delete()` |
| `app/Application/Investor/InvestorService.php` | + `update()`, `delete()` |
| `app/Application/Transaction/TransactionService.php` | + `update()`, `delete()` |
| `app/Presentation/Admin/templates/@layout.latte` | Bootstrap 5 + naja CDN |
| `app/Presentation/Admin/Presenters/FundPresenter.php` | form komponenta + handleDelete |
| `app/Presentation/Admin/templates/Fund/default.latte` | nová |
| `app/Presentation/Admin/Presenters/InvestorPresenter.php` | form komponenta + handleDelete |
| `app/Presentation/Admin/templates/Investor/default.latte` | nová |
| `app/Presentation/Admin/Presenters/TransactionPresenter.php` | form komponenta + handleDelete |
| `app/Presentation/Admin/templates/Transaction/default.latte` | nová |

---

## Task 1: Repository interfaces — přidat `delete()`

**Files:**
- Modify: `app/Domain/Fund/FundRepositoryInterface.php`
- Modify: `app/Domain/Investor/InvestorRepositoryInterface.php`
- Modify: `app/Domain/Transaction/TransactionRepositoryInterface.php`

- [ ] **Step 1: Přidat `delete()` do `FundRepositoryInterface`**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Fund;

interface FundRepositoryInterface
{
    /** @return mixed[] */
    public function findAll(): array;

    public function findById(int $id): ?Fund;

    public function save(Fund $fund): void;

    public function delete(int $id): void;
}
```

- [ ] **Step 2: Přidat `delete()` do `InvestorRepositoryInterface`**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Investor;

interface InvestorRepositoryInterface
{
    /** @return mixed[] */
    public function findAll(): array;

    public function findById(int $id): ?Investor;

    public function save(Investor $investor): void;

    public function delete(int $id): void;
}
```

- [ ] **Step 3: Přidat `delete()` do `TransactionRepositoryInterface`**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Transaction;

interface TransactionRepositoryInterface
{
    /** @return mixed[] */
    public function findAll(): array;

    public function findById(int $id): ?Transaction;

    public function save(Transaction $transaction): void;

    public function delete(int $id): void;
}
```

- [ ] **Step 4: Commit**

```bash
git add app/Domain/Fund/FundRepositoryInterface.php \
        app/Domain/Investor/InvestorRepositoryInterface.php \
        app/Domain/Transaction/TransactionRepositoryInterface.php
git commit -m "feat: add delete() to repository interfaces"
```

---

## Task 2: Infrastructure — implementace `delete()`

**Files:**
- Modify: `app/Infrastructure/Database/FundRepository.php`
- Modify: `app/Infrastructure/Database/InvestorRepository.php`
- Modify: `app/Infrastructure/Database/TransactionRepository.php`

Pozn.: `save()` již zvládá update (větev `$fund->id !== null`). Stačí přidat `delete()`.

- [ ] **Step 1: Přidat `delete()` do `FundRepository`**

```php
public function delete(int $id): void
{
    $this->database->table('funds')->get($id)?->delete();
}
```

Přidat jako metodu třídy `FundRepository` za `save()`.

- [ ] **Step 2: Přidat `delete()` do `InvestorRepository`**

```php
public function delete(int $id): void
{
    $this->database->table('investors')->get($id)?->delete();
}
```

- [ ] **Step 3: Přidat `delete()` do `TransactionRepository`**

```php
public function delete(int $id): void
{
    $this->database->table('transactions')->get($id)?->delete();
}
```

- [ ] **Step 4: Ověřit, že PHP nemá syntaktické chyby**

```bash
docker compose exec app php -l app/Infrastructure/Database/FundRepository.php
docker compose exec app php -l app/Infrastructure/Database/InvestorRepository.php
docker compose exec app php -l app/Infrastructure/Database/TransactionRepository.php
```

Očekávaný výstup: `No syntax errors detected in ...`

- [ ] **Step 5: Commit**

```bash
git add app/Infrastructure/Database/FundRepository.php \
        app/Infrastructure/Database/InvestorRepository.php \
        app/Infrastructure/Database/TransactionRepository.php
git commit -m "feat: implement delete() in DB repositories"
```

---

## Task 3: Application services — `update()` a `delete()`

**Files:**
- Modify: `app/Application/Fund/FundService.php`
- Modify: `app/Application/Investor/InvestorService.php`
- Modify: `app/Application/Transaction/TransactionService.php`

- [ ] **Step 1: Přidat `update()` a `delete()` do `FundService`**

Celý soubor po úpravě:

```php
<?php

declare(strict_types=1);

namespace App\Application\Fund;

use App\Domain\Fund\Fund;
use App\Domain\Fund\FundRepositoryInterface;

final class FundService
{
    public function __construct(
        private readonly FundRepositoryInterface $fundRepository,
    ) {
    }

    /** @return mixed[] */
    public function getAll(): array
    {
        return $this->fundRepository->findAll();
    }

    public function getById(int $id): ?Fund
    {
        return $this->fundRepository->findById($id);
    }

    public function create(string $name): void
    {
        $fund = new Fund(
            id: null,
            name: $name,
            createdAt: new \DateTimeImmutable(),
        );

        $this->fundRepository->save($fund);
    }

    public function update(int $id, string $name): void
    {
        $fund = new Fund(
            id: $id,
            name: $name,
            createdAt: new \DateTimeImmutable(),
        );

        $this->fundRepository->save($fund);
    }

    public function delete(int $id): void
    {
        $this->fundRepository->delete($id);
    }
}
```

- [ ] **Step 2: Přidat `update()` a `delete()` do `InvestorService`**

Celý soubor po úpravě:

```php
<?php

declare(strict_types=1);

namespace App\Application\Investor;

use App\Domain\Investor\Investor;
use App\Domain\Investor\InvestorRepositoryInterface;

final class InvestorService
{
    public function __construct(
        private readonly InvestorRepositoryInterface $investorRepository,
    ) {
    }

    /** @return mixed[] */
    public function getAll(): array
    {
        return $this->investorRepository->findAll();
    }

    public function getById(int $id): ?Investor
    {
        return $this->investorRepository->findById($id);
    }

    public function create(string $name, string $email): void
    {
        $investor = new Investor(
            id: null,
            name: $name,
            email: $email,
            createdAt: new \DateTimeImmutable(),
        );

        $this->investorRepository->save($investor);
    }

    public function update(int $id, string $name, string $email): void
    {
        $investor = new Investor(
            id: $id,
            name: $name,
            email: $email,
            createdAt: new \DateTimeImmutable(),
        );

        $this->investorRepository->save($investor);
    }

    public function delete(int $id): void
    {
        $this->investorRepository->delete($id);
    }
}
```

- [ ] **Step 3: Přidat `update()` a `delete()` do `TransactionService`**

Celý soubor po úpravě:

```php
<?php

declare(strict_types=1);

namespace App\Application\Transaction;

use App\Domain\Transaction\Transaction;
use App\Domain\Transaction\TransactionRepositoryInterface;

final class TransactionService
{
    public function __construct(
        private readonly TransactionRepositoryInterface $transactionRepository,
    ) {
    }

    /** @return mixed[] */
    public function getAll(): array
    {
        return $this->transactionRepository->findAll();
    }

    public function getById(int $id): ?Transaction
    {
        return $this->transactionRepository->findById($id);
    }

    public function create(int $fundId, int $investorId, float $amount): void
    {
        $transaction = new Transaction(
            id: null,
            fundId: $fundId,
            investorId: $investorId,
            amount: $amount,
            createdAt: new \DateTimeImmutable(),
        );

        $this->transactionRepository->save($transaction);
    }

    public function update(int $id, int $fundId, int $investorId, float $amount): void
    {
        $transaction = new Transaction(
            id: $id,
            fundId: $fundId,
            investorId: $investorId,
            amount: $amount,
            createdAt: new \DateTimeImmutable(),
        );

        $this->transactionRepository->save($transaction);
    }

    public function delete(int $id): void
    {
        $this->transactionRepository->delete($id);
    }
}
```

- [ ] **Step 4: Ověřit syntaxi**

```bash
docker compose exec app php -l app/Application/Fund/FundService.php
docker compose exec app php -l app/Application/Investor/InvestorService.php
docker compose exec app php -l app/Application/Transaction/TransactionService.php
```

Očekávaný výstup: `No syntax errors detected in ...`

- [ ] **Step 5: Commit**

```bash
git add app/Application/Fund/FundService.php \
        app/Application/Investor/InvestorService.php \
        app/Application/Transaction/TransactionService.php
git commit -m "feat: add update() and delete() to application services"
```

---

## Task 4: Admin layout — Bootstrap 5 + naja

**Files:**
- Modify: `app/Presentation/Admin/templates/@layout.latte`

- [ ] **Step 1: Aktualizovat layout**

Celý soubor po úpravě:

```latte
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{block title}Smart Funds Admin{/block}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
            <span class="navbar-brand">Smart Funds Admin</span>
            <div class="navbar-nav">
                <a class="nav-link" n:href="Dashboard:default">Dashboard</a>
                <a class="nav-link" n:href="Fund:default">Fondy</a>
                <a class="nav-link" n:href="Investor:default">Investoři</a>
                <a class="nav-link" n:href="Transaction:default">Transakce</a>
            </div>
        </div>
    </nav>

    <main class="container">
        {block content}{/block}
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/naja@2/dist/Naja.min.js"></script>
    <script>
        const naja = window.naja.default;
        naja.initialize();
        naja.addEventListener('success', function (event) {
            if (event.detail.payload && event.detail.payload.closeModal) {
                document.querySelectorAll('.modal.show').forEach(function (el) {
                    bootstrap.Modal.getInstance(el)?.hide();
                });
            }
        });
    </script>
    {block scripts}{/block}
</body>
</html>
```

- [ ] **Step 2: Ověřit, že aplikace běží bez chyby**

Otevřít http://localhost:8080/admin — stránka se načte s Bootstrap navigací.

- [ ] **Step 3: Commit**

```bash
git add app/Presentation/Admin/templates/@layout.latte
git commit -m "feat: add Bootstrap 5 and naja to admin layout"
```

---

## Task 5: Fund CRUD — presenter + šablona

**Files:**
- Modify: `app/Presentation/Admin/Presenters/FundPresenter.php`
- Create: `app/Presentation/Admin/templates/Fund/default.latte`

- [ ] **Step 1: Přepsat `FundPresenter`**

```php
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
```

- [ ] **Step 2: Vytvořit `Fund/default.latte`**

```bash
mkdir -p app/Presentation/Admin/templates/Fund
```

Obsah souboru `app/Presentation/Admin/templates/Fund/default.latte`:

```latte
{block content}

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Fondy</h2>
    <button type="button" class="btn btn-primary"
            data-bs-toggle="modal" data-bs-target="#fundModal"
            onclick="openFundModal(null, '')">
        + Přidat fond
    </button>
</div>

{snippet list}
<table class="table table-striped table-bordered">
    <thead class="table-dark">
        <tr>
            <th>#</th>
            <th>Název</th>
            <th>Vytvořeno</th>
            <th class="text-end">Akce</th>
        </tr>
    </thead>
    <tbody>
        {foreach $funds as $fund}
        <tr>
            <td>{$fund->id}</td>
            <td>{$fund->name}</td>
            <td>{$fund->created_at|date:'j. n. Y'}</td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-primary"
                        data-bs-toggle="modal" data-bs-target="#fundModal"
                        onclick="openFundModal({$fund->id}, {$fund->name|json})">
                    Upravit
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger"
                        data-id="{$fund->id}"
                        data-name="{$fund->name|escapeHtmlAttr}"
                        data-delete-url="{link handleDelete!, id: $fund->id}"
                        onclick="openDeleteModal(this)">
                    Smazat
                </button>
            </td>
        </tr>
        {else}
        <tr><td colspan="4" class="text-center text-muted">Žádné fondy</td></tr>
        {/foreach}
    </tbody>
</table>
{/snippet}

{* Formulářový modal *}
{snippet modal}
<div class="modal fade" id="fundModal" tabindex="-1" aria-labelledby="fundModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fundModalLabel">Fond</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                {control fundForm}
            </div>
        </div>
    </div>
</div>
{/snippet}

{* Delete confirm modal *}
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">Smazat fond</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Opravdu chcete smazat fond <strong id="deleteFundName"></strong>? Tuto akci nelze vrátit.</p>
            </div>
            <div class="modal-footer">
                <form id="deleteForm" method="post" class="ajax">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zrušit</button>
                    <button type="submit" class="btn btn-danger">Smazat</button>
                </form>
            </div>
        </div>
    </div>
</div>

{/block}

{block scripts}
<script>
function openFundModal(id, name) {
    const modal = document.getElementById('fundModal');
    const form = modal.querySelector('form');
    document.getElementById('fundModalLabel').textContent = id ? 'Upravit fond' : 'Nový fond';
    form.querySelector('[name="id"]').value = id ?? '';
    form.querySelector('[name="name"]').value = name ?? '';
    new bootstrap.Modal(modal).show();
}

function openDeleteModal(btn) {
    document.getElementById('deleteFundName').textContent = btn.dataset.name;
    const form = document.getElementById('deleteForm');
    form.action = btn.dataset.deleteUrl;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
{/block}
```

- [ ] **Step 3: Ověřit v prohlížeči**

1. Otevřít http://localhost:8080/admin/funds — tabulka se zobrazí (prázdná, pokud není DB)
2. Kliknout "+ Přidat fond" — modal se otevře s prázdným formulářem
3. Odeslat prázdný formulář — modal zůstane otevřen, zobrazí se chybová hláška "Zadejte název fondu."

- [ ] **Step 4: Commit**

```bash
git add app/Presentation/Admin/Presenters/FundPresenter.php \
        app/Presentation/Admin/templates/Fund/default.latte
git commit -m "feat: Fund CRUD — presenter, modal form, delete confirm, AJAX snippets"
```

---

## Task 6: Investor CRUD — presenter + šablona

**Files:**
- Modify: `app/Presentation/Admin/Presenters/InvestorPresenter.php`
- Create: `app/Presentation/Admin/templates/Investor/default.latte`

- [ ] **Step 1: Přepsat `InvestorPresenter`**

```php
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
```

- [ ] **Step 2: Vytvořit `Investor/default.latte`**

```bash
mkdir -p app/Presentation/Admin/templates/Investor
```

Obsah souboru `app/Presentation/Admin/templates/Investor/default.latte`:

```latte
{block content}

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Investoři</h2>
    <button type="button" class="btn btn-primary"
            data-bs-toggle="modal" data-bs-target="#investorModal"
            onclick="openInvestorModal(null, '', '')">
        + Přidat investora
    </button>
</div>

{snippet list}
<table class="table table-striped table-bordered">
    <thead class="table-dark">
        <tr>
            <th>#</th>
            <th>Jméno</th>
            <th>E-mail</th>
            <th>Vytvořeno</th>
            <th class="text-end">Akce</th>
        </tr>
    </thead>
    <tbody>
        {foreach $investors as $investor}
        <tr>
            <td>{$investor->id}</td>
            <td>{$investor->name}</td>
            <td>{$investor->email}</td>
            <td>{$investor->created_at|date:'j. n. Y'}</td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-primary"
                        data-bs-toggle="modal" data-bs-target="#investorModal"
                        onclick="openInvestorModal({$investor->id}, {$investor->name|json}, {$investor->email|json})">
                    Upravit
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger"
                        data-id="{$investor->id}"
                        data-name="{$investor->name|escapeHtmlAttr}"
                        data-delete-url="{link handleDelete!, id: $investor->id}"
                        onclick="openDeleteModal(this)">
                    Smazat
                </button>
            </td>
        </tr>
        {else}
        <tr><td colspan="5" class="text-center text-muted">Žádní investoři</td></tr>
        {/foreach}
    </tbody>
</table>
{/snippet}

{snippet modal}
<div class="modal fade" id="investorModal" tabindex="-1" aria-labelledby="investorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="investorModalLabel">Investor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                {control investorForm}
            </div>
        </div>
    </div>
</div>
{/snippet}

<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">Smazat investora</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Opravdu chcete smazat investora <strong id="deleteInvestorName"></strong>? Tuto akci nelze vrátit.</p>
            </div>
            <div class="modal-footer">
                <form id="deleteForm" method="post" class="ajax">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zrušit</button>
                    <button type="submit" class="btn btn-danger">Smazat</button>
                </form>
            </div>
        </div>
    </div>
</div>

{/block}

{block scripts}
<script>
function openInvestorModal(id, name, email) {
    const modal = document.getElementById('investorModal');
    const form = modal.querySelector('form');
    document.getElementById('investorModalLabel').textContent = id ? 'Upravit investora' : 'Nový investor';
    form.querySelector('[name="id"]').value = id ?? '';
    form.querySelector('[name="name"]').value = name ?? '';
    form.querySelector('[name="email"]').value = email ?? '';
    new bootstrap.Modal(modal).show();
}

function openDeleteModal(btn) {
    document.getElementById('deleteInvestorName').textContent = btn.dataset.name;
    const form = document.getElementById('deleteForm');
    form.action = btn.dataset.deleteUrl;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
{/block}
```

- [ ] **Step 3: Ověřit v prohlížeči**

1. Otevřít http://localhost:8080/admin/investors — tabulka se zobrazí
2. Kliknout "+ Přidat investora" — modal se otevře s poli Jméno a E-mail
3. Odeslat prázdný formulář — modal zůstane otevřen s validačními chybami

- [ ] **Step 4: Commit**

```bash
git add app/Presentation/Admin/Presenters/InvestorPresenter.php \
        app/Presentation/Admin/templates/Investor/default.latte
git commit -m "feat: Investor CRUD — presenter, modal form, delete confirm, AJAX snippets"
```

---

## Task 7: Transaction CRUD — presenter + šablona (se selecty)

**Files:**
- Modify: `app/Presentation/Admin/Presenters/TransactionPresenter.php`
- Create: `app/Presentation/Admin/templates/Transaction/default.latte`

- [ ] **Step 1: Přepsat `TransactionPresenter`**

```php
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
```

- [ ] **Step 2: Vytvořit `Transaction/default.latte`**

```bash
mkdir -p app/Presentation/Admin/templates/Transaction
```

Obsah souboru `app/Presentation/Admin/templates/Transaction/default.latte`:

```latte
{block content}

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Transakce</h2>
    <button type="button" class="btn btn-primary"
            data-bs-toggle="modal" data-bs-target="#transactionModal"
            onclick="openTransactionModal(null, '', '', '')">
        + Přidat transakci
    </button>
</div>

{snippet list}
<table class="table table-striped table-bordered">
    <thead class="table-dark">
        <tr>
            <th>#</th>
            <th>Fond</th>
            <th>Investor</th>
            <th>Částka</th>
            <th>Datum</th>
            <th class="text-end">Akce</th>
        </tr>
    </thead>
    <tbody>
        {foreach $transactions as $tx}
        <tr>
            <td>{$tx->id}</td>
            <td>{isset($selectFunds[$tx->fund_id]) ? $selectFunds[$tx->fund_id] : $tx->fund_id}</td>
            <td>{isset($selectInvestors[$tx->investor_id]) ? $selectInvestors[$tx->investor_id] : $tx->investor_id}</td>
            <td>{$tx->amount|number:2} Kč</td>
            <td>{$tx->created_at|date:'j. n. Y'}</td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-primary"
                        data-bs-toggle="modal" data-bs-target="#transactionModal"
                        onclick="openTransactionModal({$tx->id}, {$tx->fund_id}, {$tx->investor_id}, {$tx->amount})">
                    Upravit
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger"
                        data-id="{$tx->id}"
                        data-name="transakci #{$tx->id}"
                        data-delete-url="{link handleDelete!, id: $tx->id}"
                        onclick="openDeleteModal(this)">
                    Smazat
                </button>
            </td>
        </tr>
        {else}
        <tr><td colspan="6" class="text-center text-muted">Žádné transakce</td></tr>
        {/foreach}
    </tbody>
</table>
{/snippet}

{snippet modal}
<div class="modal fade" id="transactionModal" tabindex="-1" aria-labelledby="transactionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="transactionModalLabel">Transakce</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                {control transactionForm}
            </div>
        </div>
    </div>
</div>
{/snippet}

<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">Smazat transakci</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Opravdu chcete smazat <strong id="deleteTransactionName"></strong>? Tuto akci nelze vrátit.</p>
            </div>
            <div class="modal-footer">
                <form id="deleteForm" method="post" class="ajax">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zrušit</button>
                    <button type="submit" class="btn btn-danger">Smazat</button>
                </form>
            </div>
        </div>
    </div>
</div>

{/block}

{block scripts}
<script>
function openTransactionModal(id, fundId, investorId, amount) {
    const modal = document.getElementById('transactionModal');
    const form = modal.querySelector('form');
    document.getElementById('transactionModalLabel').textContent = id ? 'Upravit transakci' : 'Nová transakce';
    form.querySelector('[name="id"]').value = id ?? '';
    if (fundId) form.querySelector('[name="fund_id"]').value = fundId;
    if (investorId) form.querySelector('[name="investor_id"]').value = investorId;
    form.querySelector('[name="amount"]').value = amount ?? '';
    new bootstrap.Modal(modal).show();
}

function openDeleteModal(btn) {
    document.getElementById('deleteTransactionName').textContent = btn.dataset.name;
    const form = document.getElementById('deleteForm');
    form.action = btn.dataset.deleteUrl;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
{/block}
```

- [ ] **Step 3: Ověřit v prohlížeči**

1. Otevřít http://localhost:8080/admin/transactions — tabulka se zobrazí
2. Kliknout "+ Přidat transakci" — modal se otevře se selecty Fond a Investor (naplněnými z DB)
3. Odeslat prázdný formulář — validační chyby se zobrazí v modalu

- [ ] **Step 4: Commit**

```bash
git add app/Presentation/Admin/Presenters/TransactionPresenter.php \
        app/Presentation/Admin/templates/Transaction/default.latte
git commit -m "feat: Transaction CRUD — presenter, modal form with selects, delete confirm, AJAX snippets"
```

---

## Celková verifikace po dokončení všech tasků

1. `docker compose up -d && docker compose exec app composer install`
2. Importovat schéma: `docker compose exec -T db mariadb -u USER -pPASS DBNAME < db/schema.sql`
3. Přidat fond → modal se zavře, fond se zobrazí v tabulce bez překreslení stránky
4. Upravit fond → modal se předplní stávajícím názvem
5. Smazat fond → delete modal zobrazí název, po potvrzení zmizí z tabulky
6. Totéž pro Investory a Transakce
7. Při chybné validaci zůstane modal otevřen s chybovými hláškami
8. Transaction form: selecty naplněny fondy a investory z DB

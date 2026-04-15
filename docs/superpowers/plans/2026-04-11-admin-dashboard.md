# Admin Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Doplnit admin dashboard o 4 stat karty (celkový objem, transakce, investoři, fondy) a per-fond tabulku s objemem, počtem investorů, průměrnou transakcí a datem posledního vkladu.

**Architecture:** Nová `DashboardService` injektuje `Nette\Database\Explorer` přímo a provádí agregační dotazy přes Nette Selection API (`table()->select(...)->group('fund_id')`). PHP-side LEFT JOIN doplní fondy bez transakcí. `DashboardPresenter` injektuje service a předává data šabloně.

**Tech Stack:** PHP 8.5, Nette Database Explorer (Selection API), Latte 3, Bootstrap 5, MariaDB

---

## Soubory

| Soubor | Akce |
|--------|------|
| `app/Application/Dashboard/DashboardService.php` | vytvořit |
| `app/Presentation/Admin/Presenters/DashboardPresenter.php` | upravit (inject + actionDefault) |
| `app/Presentation/Admin/templates/Dashboard/default.latte` | přepsat |
| `config/services.neon` | přidat DashboardService |

---

### Task 1: DashboardService

**Files:**
- Create: `app/Application/Dashboard/DashboardService.php`
- Modify: `config/services.neon`

- [x] **Krok 1: Vytvořit DashboardService**

Vytvořit soubor `app/Application/Dashboard/DashboardService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Application\Dashboard;

use Nette\Database\Explorer;

final class DashboardService
{
    public function __construct(
        private readonly Explorer $database,
    ) {
    }

    /** @return array{totalVolume: float, transactionCount: int, investorCount: int, fundCount: int} */
    public function getGlobalStats(): array
    {
        return [
            'totalVolume'      => (float) ($this->database->table('transactions')->sum('amount') ?? 0),
            'transactionCount' => $this->database->table('transactions')->count('*'),
            'investorCount'    => $this->database->table('investors')->count('*'),
            'fundCount'        => $this->database->table('funds')->count('*'),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, investorCount: int, totalAmount: float, avgAmount: float, lastTransaction: ?\DateTimeImmutable}>
     */
    public function getFundStats(): array
    {
        $statsRows = $this->database
            ->table('transactions')
            ->select('fund_id,
                COUNT(DISTINCT investor_id) AS investor_count,
                SUM(amount) AS total_amount,
                AVG(amount) AS avg_amount,
                MAX(created_at) AS last_transaction')
            ->group('fund_id')
            ->fetchAll();

        $statsByFund = [];
        foreach ($statsRows as $row) {
            $lastTx = $row->last_transaction;
            $statsByFund[$row->fund_id] = [
                'investorCount'   => (int) $row->investor_count,
                'totalAmount'     => (float) $row->total_amount,
                'avgAmount'       => (float) $row->avg_amount,
                'lastTransaction' => $lastTx instanceof \DateTimeInterface
                    ? \DateTimeImmutable::createFromInterface($lastTx)
                    : ($lastTx !== null ? new \DateTimeImmutable($lastTx) : null),
            ];
        }

        $funds = $this->database->table('funds')->order('name')->fetchAll();
        $result = [];
        foreach ($funds as $fund) {
            $stats = $statsByFund[$fund->id] ?? [
                'investorCount'   => 0,
                'totalAmount'     => 0.0,
                'avgAmount'       => 0.0,
                'lastTransaction' => null,
            ];
            $result[] = [
                'id'              => $fund->id,
                'name'            => $fund->name,
                'investorCount'   => $stats['investorCount'],
                'totalAmount'     => $stats['totalAmount'],
                'avgAmount'       => $stats['avgAmount'],
                'lastTransaction' => $stats['lastTransaction'],
            ];
        }

        usort($result, fn($a, $b) => $b['totalAmount'] <=> $a['totalAmount']);

        return $result;
    }
}
```

- [x] **Krok 2: Registrovat v DI**

Upravit `config/services.neon` — přidat za `App\Application\Transaction\TransactionService`:

```neon
    - App\Application\Dashboard\DashboardService
```

- [x] **Krok 3: PHP syntax check**

```bash
php -l app/Application/Dashboard/DashboardService.php
```

Očekáváno: `No syntax errors detected`

- [x] **Krok 4: Commit**

```bash
git add app/Application/Dashboard/DashboardService.php config/services.neon
git commit -m "feat: DashboardService — globální stats a per-fond agregace"
```

---

### Task 2: DashboardPresenter

**Files:**
- Modify: `app/Presentation/Admin/Presenters/DashboardPresenter.php`

- [x] **Krok 1: Přepsat DashboardPresenter**

Nahradit celý obsah souboru `app/Presentation/Admin/Presenters/DashboardPresenter.php`:

```php
<?php

declare(strict_types=1);

namespace App\Presentation\Admin\Presenters;

use App\Application\Dashboard\DashboardService;

final class DashboardPresenter extends BaseAdminPresenter
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {
    }

    public function actionDefault(): void
    {
        $this->template->globalStats = $this->dashboardService->getGlobalStats();
        $this->template->fundStats   = $this->dashboardService->getFundStats();
    }
}
```

- [x] **Krok 2: PHP syntax check**

```bash
php -l app/Presentation/Admin/Presenters/DashboardPresenter.php
```

Očekáváno: `No syntax errors detected`

- [x] **Krok 3: Commit**

```bash
git add app/Presentation/Admin/Presenters/DashboardPresenter.php
git commit -m "feat: DashboardPresenter — inject DashboardService, předání dat šabloně"
```

---

### Task 3: Dashboard šablona

**Files:**
- Modify: `app/Presentation/Admin/templates/Dashboard/default.latte`

- [x] **Krok 1: Přepsat šablonu**

Nahradit celý obsah souboru `app/Presentation/Admin/templates/Dashboard/default.latte`:

```latte
{layout '../@layout.latte'}
{block title}Dashboard — Smart Funds Admin{/block}
{block content}

<h2 class="mb-4">Dashboard</h2>

<div class="row g-3 mb-5">
    <div class="col-sm-6 col-xl-3">
        <div class="card text-center border-primary">
            <div class="card-body">
                <h6 class="card-subtitle mb-1 text-muted">Celkový objem</h6>
                <p class="card-text fs-4 fw-bold text-primary">{number_format($globalStats['totalVolume'], 2, ',', ' ')} Kč</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="card-subtitle mb-1 text-muted">Transakcí</h6>
                <p class="card-text fs-4 fw-bold">{$globalStats['transactionCount']}</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="card-subtitle mb-1 text-muted">Investorů</h6>
                <p class="card-text fs-4 fw-bold">{$globalStats['investorCount']}</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="card-subtitle mb-1 text-muted">Fondů</h6>
                <p class="card-text fs-4 fw-bold">{$globalStats['fundCount']}</p>
            </div>
        </div>
    </div>
</div>

<h4 class="mb-3">Přehled fondů</h4>

<table class="table table-striped table-bordered">
    <thead class="table-dark">
        <tr>
            <th>Fond</th>
            <th class="text-end">Investoři</th>
            <th class="text-end">Celkový objem</th>
            <th class="text-end">Průměrná transakce</th>
            <th>Poslední vklad</th>
        </tr>
    </thead>
    <tbody>
        {foreach $fundStats as $fund}
        <tr>
            <td>{$fund['name']}</td>
            <td class="text-end">{$fund['investorCount']}</td>
            <td class="text-end">{number_format($fund['totalAmount'], 2, ',', ' ')} Kč</td>
            <td class="text-end">{$fund['avgAmount'] > 0 ? number_format($fund['avgAmount'], 2, ',', ' ') . ' Kč' : '—'}</td>
            <td>{$fund['lastTransaction'] !== null ? $fund['lastTransaction']->format('j. n. Y') : '—'}</td>
        </tr>
        {else}
        <tr><td colspan="5" class="text-center text-muted">Žádné fondy</td></tr>
        {/foreach}
    </tbody>
</table>

{/block}
```

- [x] **Krok 2: Ověřit v prohlížeči**

```bash
# Docker musí běžet, přihlásit se jako admin
curl -sL -b <cookies> http://localhost:8080/admin | grep -o "Celkový objem\|Transakcí\|Investorů\|Fondů\|Přehled fondů"
```

Očekáváno: všechny 4 headingy a "Přehled fondů" přítomny, žádná Tracy chyba (HTTP 200).

- [x] **Krok 3: Commit**

```bash
git add app/Presentation/Admin/templates/Dashboard/default.latte
git commit -m "feat: admin dashboard — stat karty a per-fond tabulka"
```

---

### Task 4: Push a ověření

- [x] **Krok 1: Push do main**

```bash
git push origin main
```

- [x] **Krok 2: Manuální ověření dashboardu**

1. Otevřít `http://localhost:8080/admin` (přihlášen jako admin)
2. Ověřit 4 stat karty — hodnoty odpovídají datům v DB (`SELECT SUM(amount), COUNT(*) FROM transactions; SELECT COUNT(*) FROM investors; SELECT COUNT(*) FROM funds;`)
3. Ověřit per-fond tabulku — fondy bez transakcí zobrazeny se `—` a nulami
4. Ověřit řazení — fond s největším objemem nahoře
5. Ověřit formátování: desetinná čárka, mezery jako oddělovač tisíců, česky datum

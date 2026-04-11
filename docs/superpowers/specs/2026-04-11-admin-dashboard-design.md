# Admin Dashboard — Design spec

**Datum:** 2026-04-11  
**Stav:** schváleno

## Kontext

Admin dashboard (`/admin`) aktuálně zobrazuje pouze prázdné `<h2>Dashboard</h2>`. Cílem je doplnit přehledové statistiky — celkové počty a per-fond breakdown — aby admin na první pohled viděl stav portfolia.

## Co se buduje

### Stat karty (4 Bootstrap cards v horní řadě)

| Karta | Zdroj |
|-------|-------|
| Celkový objem (Kč) | `SUM(transactions.amount)` |
| Počet transakcí | `COUNT(transactions.*)` |
| Počet investorů | `COUNT(investors.*)` |
| Počet fondů | `COUNT(funds.*)` |

### Per-fond tabulka

Sloupce: **Fond | Investoři | Celkový objem | Průměrná transakce | Poslední vklad**

- Řazení: podle celkového objemu sestupně
- Fondy bez transakcí: zobrazeny s nulovými hodnotami (PHP-side LEFT JOIN — načteme všechny fondy, doplníme stats kde existují)
- Formátování: Kč s desetinnými místy, datum česky

## Architektura

### `DashboardService` (nový)

`app/Application/Dashboard/DashboardService.php`

Injektuje `Nette\Database\Explorer` přímo — agregační dotazy překračují hranice jedné entity.

```php
public function getGlobalStats(): array
// Vrací: ['totalVolume' => float, 'transactionCount' => int, 'investorCount' => int, 'fundCount' => int]

public function getFundStats(): array
// Vrací: array per fond s klíči:
//   id, name, investorCount, totalAmount, avgAmount, lastTransaction (?DateTimeImmutable)
// Pozn: s newDateTime:true vrací MAX(created_at) jako Nette\Database\DateTime → použít createFromInterface()
```

**Implementace `getFundStats()`** — Nette Selection API:

```php
$stats = $this->database
    ->table('transactions')
    ->select('fund_id,
              COUNT(DISTINCT investor_id) AS investor_count,
              SUM(amount) AS total_amount,
              AVG(amount) AS avg_amount,
              MAX(created_at) AS last_transaction')
    ->group('fund_id')
    ->fetchAll();

// Indexovat podle fund_id
// Načíst všechny fondy → LEFT JOIN v PHP
// Vrátit pole pro všechny fondy (i bez transakcí)
```

**Implementace `getGlobalStats()`:**

```php
[
    'totalVolume'      => (float) $this->database->table('transactions')->sum('amount'),
    'transactionCount' => $this->database->table('transactions')->count('*'),
    'investorCount'    => $this->database->table('investors')->count('*'),
    'fundCount'        => $this->database->table('funds')->count('*'),
]
```

### `DashboardPresenter` (rozšíření)

`app/Presentation/Admin/Presenters/DashboardPresenter.php`

- Inject `DashboardService`
- `actionDefault()`: předá `globalStats` a `fundStats` do šablony

### `Dashboard/default.latte` (přepis)

`app/Presentation/Admin/templates/Dashboard/default.latte`

- 4 Bootstrap stat karty v `row g-3`
- Bootstrap tabulka s per-fond daty
- Česky formátovaná čísla a datum

## Změny souborů

| Soubor | Akce |
|--------|------|
| `app/Application/Dashboard/DashboardService.php` | nový |
| `app/Presentation/Admin/Presenters/DashboardPresenter.php` | rozšíření (inject + actionDefault) |
| `app/Presentation/Admin/templates/Dashboard/default.latte` | přepis |
| `config/services.neon` | + `App\Application\Dashboard\DashboardService` |

## Ověření

1. Otevřít `http://localhost:8080/admin` (přihlášen jako admin)
2. Zkontrolovat 4 stat karty — hodnoty odpovídají datům v DB
3. Zkontrolovat per-fond tabulku — fondy bez transakcí zobrazeny s nulami
4. Ověřit správné formátování čísel (desetinná čárka, mezery jako oddělovač tisíců)
5. Ověřit datum posledního vkladu česky

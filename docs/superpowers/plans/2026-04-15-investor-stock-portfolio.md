# Investor Stock Portfolio & Watchlist — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Přidat portfolio sledování akcií/ETF/krypto a watchlist do investor dashboardu; ceny se cachují v DB přes cron skript.

**Architecture:** DDD 4-vrstvá architektura: Domain entity + repository interfaces → Infrastructure repositories + price providers → Application services → Presentation (Admin CRUD katalogu, Investor tabs). Ceny fetchuje `PriceFetcherService` volaný z `bin/fetch-prices.php` nebo AJAX signálu s 1h cooldownem.

**Tech Stack:** PHP 8.5, Nette Framework 3.x, Nette Database Explorer (MariaDB), Latte 3.x, Bootstrap 5, Alpha Vantage API, CoinGecko API, Yahoo Finance neoficiální API, Docker

---

## Vzory projektu (číst před implementací)

- `findAll()` vrací raw `ActiveRow[]` (pro Latte templates), `findById()` mapuje na domain entitu
- DateTime z DB: `\DateTimeImmutable::createFromInterface($row->created_at)` (kvůli `newDateTime: true`)
- DateTime do DB: `$date->format('Y-m-d H:i:s')`
- DI: constructor injection, bez `parent::__construct()`
- AJAX formulář: `$form->getElementPrototype()->addClass('ajax')` + `redrawControl('snippet')` + `$this->payload->closeModal = true`
- Presenter signál `handleFoo()` → v Latte `{link foo!}` (BEZ prefixu `handle`)
- Nové services registrovat v `config/services.neon` bez pojmenování: `- App\...\ClassName`

---

## Přehled souborů

### Nové soubory
| Soubor | Účel |
|--------|------|
| `db/migrations/001_securities.sql` | DDL 5 nových tabulek |
| `app/Domain/Security/Security.php` | Domain entita |
| `app/Domain/Security/SecurityRepositoryInterface.php` | Interface |
| `app/Domain/Portfolio/PortfolioPosition.php` | Domain entita |
| `app/Domain/Portfolio/PortfolioRepositoryInterface.php` | Interface |
| `app/Domain/Watchlist/WatchlistRepositoryInterface.php` | Interface |
| `app/Domain/Price/PriceRepositoryInterface.php` | Interface |
| `app/Domain/Price/ExchangeRateRepositoryInterface.php` | Interface |
| `app/Infrastructure/Database/SecurityRepository.php` | DB impl |
| `app/Infrastructure/Database/PortfolioRepository.php` | DB impl |
| `app/Infrastructure/Database/WatchlistRepository.php` | DB impl |
| `app/Infrastructure/Database/PriceRepository.php` | DB impl |
| `app/Infrastructure/Database/ExchangeRateRepository.php` | DB impl |
| `app/Infrastructure/Providers/PriceProviderInterface.php` | Interface |
| `app/Infrastructure/Providers/AlphaVantageProvider.php` | US/ETF/FX |
| `app/Infrastructure/Providers/CoinGeckoProvider.php` | Krypto |
| `app/Infrastructure/Providers/YahooFinanceProvider.php` | PSE |
| `app/Application/Security/SecurityService.php` | CRUD katalogu |
| `app/Application/Portfolio/PortfolioService.php` | Pozice investora |
| `app/Application/Watchlist/WatchlistService.php` | Watchlist |
| `app/Application/Prices/PriceService.php` | Čtení cen z cache |
| `app/Application/Prices/PriceFetcherService.php` | Orchestrace fetch |
| `bin/fetch-prices.php` | Cron entry-point |
| `app/Presentation/Admin/Presenters/SecurityPresenter.php` | Admin CRUD |
| `app/Presentation/Admin/templates/Security/default.latte` | Admin seznam |
| `app/Presentation/Investor/templates/Dashboard/_portfolio.latte` | Portfolio tab partial |
| `app/Presentation/Investor/templates/Dashboard/_watchlist.latte` | Watchlist tab partial |

### Modifikované soubory
| Soubor | Změna |
|--------|-------|
| `db/schema.sql` | Přidat 5 nových tabulek |
| `config/services.neon` | Registrovat 13 nových services |
| `config/common.neon` | Přidat `parameters: alphavantage:` sekci |
| `config/local.neon.example` | Přidat `alphavantage.apiKey` příklad |
| `app/Presentation/Admin/templates/@layout.latte` | Přidat link "Cenné papíry" do nav |
| `app/Presentation/Investor/Presenters/DashboardPresenter.php` | Přidat Portfolio + Watchlist |
| `app/Presentation/Investor/templates/Dashboard/default.latte` | Přidat Bootstrap tabs |

---

## Task 1: DB migrace

**Files:**
- Create: `db/migrations/001_securities.sql`
- Modify: `db/schema.sql`

- [ ] **Krok 1: Vytvořit migrační soubor**

```sql
-- db/migrations/001_securities.sql

CREATE TABLE `securities` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `ticker`          VARCHAR(20)     NOT NULL,
  `name`            VARCHAR(255)    NOT NULL,
  `type`            ENUM('stock','etf','crypto') NOT NULL,
  `exchange`        VARCHAR(20)     NOT NULL COMMENT 'NYSE, NASDAQ, PSE, CRYPTO',
  `currency`        CHAR(3)         NOT NULL COMMENT 'USD, EUR, CZK',
  `provider`        ENUM('alpha_vantage','coingecko','yahoo') NOT NULL,
  `provider_symbol` VARCHAR(50)     NOT NULL COMMENT 'Přesný symbol pro API',
  `active`          TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`      DATETIME        NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ticker` (`ticker`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `security_prices` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `security_id` INT UNSIGNED    NOT NULL,
  `price`       DECIMAL(15,4)   NOT NULL,
  `currency`    CHAR(3)         NOT NULL,
  `fetched_at`  DATETIME        NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_security` (`security_id`),
  CONSTRAINT `fk_sp_security` FOREIGN KEY (`security_id`) REFERENCES `securities` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `portfolio_positions` (
  `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `investor_id`       INT UNSIGNED    NOT NULL,
  `security_id`       INT UNSIGNED    NOT NULL,
  `quantity`          DECIMAL(15,6)   NOT NULL,
  `purchase_price`    DECIMAL(15,4)   NOT NULL,
  `purchase_currency` CHAR(3)         NOT NULL,
  `purchased_at`      DATE            NOT NULL,
  `note`              VARCHAR(255)    NULL,
  `created_at`        DATETIME        NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_pp_investor` FOREIGN KEY (`investor_id`) REFERENCES `investors` (`id`),
  CONSTRAINT `fk_pp_security` FOREIGN KEY (`security_id`) REFERENCES `securities` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `watchlist` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `investor_id` INT UNSIGNED    NOT NULL,
  `security_id` INT UNSIGNED    NOT NULL,
  `added_at`    DATETIME        NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_watchlist` (`investor_id`, `security_id`),
  CONSTRAINT `fk_wl_investor` FOREIGN KEY (`investor_id`) REFERENCES `investors` (`id`),
  CONSTRAINT `fk_wl_security` FOREIGN KEY (`security_id`) REFERENCES `securities` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `exchange_rates` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `from_currency` CHAR(3)         NOT NULL,
  `to_currency`   CHAR(3)         NOT NULL,
  `rate`          DECIMAL(15,6)   NOT NULL,
  `fetched_at`    DATETIME        NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pair` (`from_currency`, `to_currency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Krok 2: Přidat stejný SQL na konec `db/schema.sql`** (před nebo za tabulku `users`)

- [ ] **Krok 3: Aplikovat migraci v Dockeru**

```bash
docker compose exec -T db mariadb -u ${DB_USER} -p${DB_PASSWORD} ${DB_NAME} < db/migrations/001_securities.sql
```

Nahradit `${DB_USER}` atd. z `.env.docker`.

Ověření:
```bash
docker compose exec db mariadb -u ${DB_USER} -p${DB_PASSWORD} ${DB_NAME} -e "SHOW TABLES;"
```
Výstup musí obsahovat: `securities`, `security_prices`, `portfolio_positions`, `watchlist`, `exchange_rates`.

- [ ] **Krok 4: Commit**

```bash
git add db/migrations/001_securities.sql db/schema.sql
git commit -m "feat: DB migrace — securities, portfolio_positions, watchlist, security_prices, exchange_rates"
```

---

## Task 2: Domain entities a interfaces

**Files:**
- Create: `app/Domain/Security/Security.php`
- Create: `app/Domain/Security/SecurityRepositoryInterface.php`
- Create: `app/Domain/Portfolio/PortfolioPosition.php`
- Create: `app/Domain/Portfolio/PortfolioRepositoryInterface.php`
- Create: `app/Domain/Watchlist/WatchlistRepositoryInterface.php`
- Create: `app/Domain/Price/PriceRepositoryInterface.php`
- Create: `app/Domain/Price/ExchangeRateRepositoryInterface.php`

- [ ] **Krok 1: Vytvořit `app/Domain/Security/Security.php`**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Security;

final readonly class Security
{
    public function __construct(
        public ?int $id,
        public string $ticker,
        public string $name,
        public string $type,
        public string $exchange,
        public string $currency,
        public string $provider,
        public string $providerSymbol,
        public bool $active,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
```

- [ ] **Krok 2: Vytvořit `app/Domain/Security/SecurityRepositoryInterface.php`**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Security;

interface SecurityRepositoryInterface
{
    /** @return mixed[] */
    public function findAll(): array;

    /** @return Security[] */
    public function findAllActive(): array;

    public function findById(int $id): ?Security;

    public function findByTicker(string $ticker): ?Security;

    public function save(Security $security): int;

    public function delete(int $id): void;
}
```

- [ ] **Krok 3: Vytvořit `app/Domain/Portfolio/PortfolioPosition.php`**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Portfolio;

final readonly class PortfolioPosition
{
    public function __construct(
        public ?int $id,
        public int $investorId,
        public int $securityId,
        public float $quantity,
        public float $purchasePrice,
        public string $purchaseCurrency,
        public \DateTimeImmutable $purchasedAt,
        public ?string $note,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
```

- [ ] **Krok 4: Vytvořit `app/Domain/Portfolio/PortfolioRepositoryInterface.php`**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Portfolio;

interface PortfolioRepositoryInterface
{
    /** @return PortfolioPosition[] */
    public function findByInvestorId(int $investorId): array;

    public function findById(int $id): ?PortfolioPosition;

    public function save(PortfolioPosition $position): int;

    public function delete(int $id): void;
}
```

- [ ] **Krok 5: Vytvořit `app/Domain/Watchlist/WatchlistRepositoryInterface.php`**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Watchlist;

interface WatchlistRepositoryInterface
{
    /** @return int[] security_id[] */
    public function findSecurityIdsByInvestorId(int $investorId): array;

    public function add(int $investorId, int $securityId): void;

    public function remove(int $investorId, int $securityId): void;

    public function exists(int $investorId, int $securityId): bool;
}
```

- [ ] **Krok 6: Vytvořit `app/Domain/Price/PriceRepositoryInterface.php`**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Price;

interface PriceRepositoryInterface
{
    /**
     * @return array{price: float, currency: string, fetched_at: \DateTimeImmutable}|null
     */
    public function findBySecurity(int $securityId): ?array;

    public function upsert(int $securityId, float $price, string $currency, \DateTimeImmutable $fetchedAt): void;
}
```

- [ ] **Krok 7: Vytvořit `app/Domain/Price/ExchangeRateRepositoryInterface.php`**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Price;

interface ExchangeRateRepositoryInterface
{
    public function findRate(string $from, string $to): ?float;

    public function upsert(string $from, string $to, float $rate, \DateTimeImmutable $fetchedAt): void;
}
```

- [ ] **Krok 8: Commit**

```bash
git add app/Domain/
git commit -m "feat: Domain — Security, PortfolioPosition entity + repository interfaces"
```

---

## Task 3: Infrastructure — Database repositories

**Files:**
- Create: `app/Infrastructure/Database/SecurityRepository.php`
- Create: `app/Infrastructure/Database/PortfolioRepository.php`
- Create: `app/Infrastructure/Database/WatchlistRepository.php`
- Create: `app/Infrastructure/Database/PriceRepository.php`
- Create: `app/Infrastructure/Database/ExchangeRateRepository.php`

- [ ] **Krok 1: Vytvořit `app/Infrastructure/Database/SecurityRepository.php`**

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Domain\Security\Security;
use App\Domain\Security\SecurityRepositoryInterface;
use Nette\Database\Explorer;

final class SecurityRepository implements SecurityRepositoryInterface
{
    public function __construct(
        private readonly Explorer $database,
    ) {
    }

    public function findAll(): array
    {
        return $this->database->table('securities')->order('ticker ASC')->fetchAll();
    }

    public function findAllActive(): array
    {
        $rows = $this->database->table('securities')
            ->where('active', 1)
            ->order('ticker ASC')
            ->fetchAll();

        return array_map(fn($row) => $this->rowToSecurity($row), $rows);
    }

    public function findById(int $id): ?Security
    {
        $row = $this->database->table('securities')->get($id);
        return $row !== null ? $this->rowToSecurity($row) : null;
    }

    public function findByTicker(string $ticker): ?Security
    {
        $row = $this->database->table('securities')->where('ticker', $ticker)->fetch();
        return $row !== null ? $this->rowToSecurity($row) : null;
    }

    public function save(Security $security): int
    {
        if ($security->id === null) {
            $row = $this->database->table('securities')->insert([
                'ticker'          => $security->ticker,
                'name'            => $security->name,
                'type'            => $security->type,
                'exchange'        => $security->exchange,
                'currency'        => $security->currency,
                'provider'        => $security->provider,
                'provider_symbol' => $security->providerSymbol,
                'active'          => $security->active ? 1 : 0,
                'created_at'      => $security->createdAt->format('Y-m-d H:i:s'),
            ]);
            return (int) $row->id;
        }

        $this->database->table('securities')->get($security->id)?->update([
            'ticker'          => $security->ticker,
            'name'            => $security->name,
            'type'            => $security->type,
            'exchange'        => $security->exchange,
            'currency'        => $security->currency,
            'provider'        => $security->provider,
            'provider_symbol' => $security->providerSymbol,
            'active'          => $security->active ? 1 : 0,
        ]);

        return $security->id;
    }

    public function delete(int $id): void
    {
        $this->database->table('securities')->get($id)?->delete();
    }

    private function rowToSecurity(\Nette\Database\Table\ActiveRow $row): Security
    {
        return new Security(
            id:             $row->id,
            ticker:         $row->ticker,
            name:           $row->name,
            type:           $row->type,
            exchange:       $row->exchange,
            currency:       $row->currency,
            provider:       $row->provider,
            providerSymbol: $row->provider_symbol,
            active:         (bool) $row->active,
            createdAt:      \DateTimeImmutable::createFromInterface($row->created_at),
        );
    }
}
```

- [ ] **Krok 2: Vytvořit `app/Infrastructure/Database/PortfolioRepository.php`**

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Domain\Portfolio\PortfolioPosition;
use App\Domain\Portfolio\PortfolioRepositoryInterface;
use Nette\Database\Explorer;

final class PortfolioRepository implements PortfolioRepositoryInterface
{
    public function __construct(
        private readonly Explorer $database,
    ) {
    }

    public function findByInvestorId(int $investorId): array
    {
        $rows = $this->database->table('portfolio_positions')
            ->where('investor_id', $investorId)
            ->order('created_at DESC')
            ->fetchAll();

        return array_map(fn($row) => $this->rowToPosition($row), $rows);
    }

    public function findById(int $id): ?PortfolioPosition
    {
        $row = $this->database->table('portfolio_positions')->get($id);
        return $row !== null ? $this->rowToPosition($row) : null;
    }

    public function save(PortfolioPosition $position): int
    {
        if ($position->id === null) {
            $row = $this->database->table('portfolio_positions')->insert([
                'investor_id'       => $position->investorId,
                'security_id'       => $position->securityId,
                'quantity'          => $position->quantity,
                'purchase_price'    => $position->purchasePrice,
                'purchase_currency' => $position->purchaseCurrency,
                'purchased_at'      => $position->purchasedAt->format('Y-m-d'),
                'note'              => $position->note,
                'created_at'        => $position->createdAt->format('Y-m-d H:i:s'),
            ]);
            return (int) $row->id;
        }

        $this->database->table('portfolio_positions')->get($position->id)?->update([
            'quantity'          => $position->quantity,
            'purchase_price'    => $position->purchasePrice,
            'purchase_currency' => $position->purchaseCurrency,
            'purchased_at'      => $position->purchasedAt->format('Y-m-d'),
            'note'              => $position->note,
        ]);

        return $position->id;
    }

    public function delete(int $id): void
    {
        $this->database->table('portfolio_positions')->get($id)?->delete();
    }

    private function rowToPosition(\Nette\Database\Table\ActiveRow $row): PortfolioPosition
    {
        return new PortfolioPosition(
            id:               $row->id,
            investorId:       $row->investor_id,
            securityId:       $row->security_id,
            quantity:         (float) $row->quantity,
            purchasePrice:    (float) $row->purchase_price,
            purchaseCurrency: $row->purchase_currency,
            purchasedAt:      new \DateTimeImmutable($row->purchased_at),
            note:             $row->note,
            createdAt:        \DateTimeImmutable::createFromInterface($row->created_at),
        );
    }
}
```

- [ ] **Krok 3: Vytvořit `app/Infrastructure/Database/WatchlistRepository.php`**

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Domain\Watchlist\WatchlistRepositoryInterface;
use Nette\Database\Explorer;

final class WatchlistRepository implements WatchlistRepositoryInterface
{
    public function __construct(
        private readonly Explorer $database,
    ) {
    }

    public function findSecurityIdsByInvestorId(int $investorId): array
    {
        return $this->database->table('watchlist')
            ->where('investor_id', $investorId)
            ->fetchPairs(null, 'security_id');
    }

    public function add(int $investorId, int $securityId): void
    {
        if ($this->exists($investorId, $securityId)) {
            return;
        }

        $this->database->table('watchlist')->insert([
            'investor_id' => $investorId,
            'security_id' => $securityId,
            'added_at'    => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    public function remove(int $investorId, int $securityId): void
    {
        $this->database->table('watchlist')
            ->where('investor_id', $investorId)
            ->where('security_id', $securityId)
            ->delete();
    }

    public function exists(int $investorId, int $securityId): bool
    {
        return $this->database->table('watchlist')
            ->where('investor_id', $investorId)
            ->where('security_id', $securityId)
            ->count('*') > 0;
    }
}
```

- [ ] **Krok 4: Vytvořit `app/Infrastructure/Database/PriceRepository.php`**

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Domain\Price\PriceRepositoryInterface;
use Nette\Database\Explorer;

final class PriceRepository implements PriceRepositoryInterface
{
    public function __construct(
        private readonly Explorer $database,
    ) {
    }

    public function findBySecurity(int $securityId): ?array
    {
        $row = $this->database->table('security_prices')
            ->where('security_id', $securityId)
            ->fetch();

        if ($row === null) {
            return null;
        }

        return [
            'price'      => (float) $row->price,
            'currency'   => $row->currency,
            'fetched_at' => \DateTimeImmutable::createFromInterface($row->fetched_at),
        ];
    }

    public function upsert(int $securityId, float $price, string $currency, \DateTimeImmutable $fetchedAt): void
    {
        $this->database->query(
            'INSERT INTO security_prices (security_id, price, currency, fetched_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE price = VALUES(price), currency = VALUES(currency), fetched_at = VALUES(fetched_at)',
            $securityId,
            $price,
            $currency,
            $fetchedAt->format('Y-m-d H:i:s'),
        );
    }
}
```

- [ ] **Krok 5: Vytvořit `app/Infrastructure/Database/ExchangeRateRepository.php`**

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Domain\Price\ExchangeRateRepositoryInterface;
use Nette\Database\Explorer;

final class ExchangeRateRepository implements ExchangeRateRepositoryInterface
{
    public function __construct(
        private readonly Explorer $database,
    ) {
    }

    public function findRate(string $from, string $to): ?float
    {
        if ($from === $to) {
            return 1.0;
        }

        $row = $this->database->table('exchange_rates')
            ->where('from_currency', $from)
            ->where('to_currency', $to)
            ->fetch();

        return $row !== null ? (float) $row->rate : null;
    }

    public function upsert(string $from, string $to, float $rate, \DateTimeImmutable $fetchedAt): void
    {
        $this->database->query(
            'INSERT INTO exchange_rates (from_currency, to_currency, rate, fetched_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE rate = VALUES(rate), fetched_at = VALUES(fetched_at)',
            $from,
            $to,
            $rate,
            $fetchedAt->format('Y-m-d H:i:s'),
        );
    }
}
```

- [ ] **Krok 6: Commit**

```bash
git add app/Infrastructure/Database/SecurityRepository.php \
        app/Infrastructure/Database/PortfolioRepository.php \
        app/Infrastructure/Database/WatchlistRepository.php \
        app/Infrastructure/Database/PriceRepository.php \
        app/Infrastructure/Database/ExchangeRateRepository.php
git commit -m "feat: Infrastructure — DB repositories pro securities, portfolio, watchlist, prices"
```

---

## Task 4: Infrastructure — Price providers

**Files:**
- Create: `app/Infrastructure/Providers/PriceProviderInterface.php`
- Create: `app/Infrastructure/Providers/AlphaVantageProvider.php`
- Create: `app/Infrastructure/Providers/CoinGeckoProvider.php`
- Create: `app/Infrastructure/Providers/YahooFinanceProvider.php`

- [ ] **Krok 1: Vytvořit `app/Infrastructure/Providers/PriceProviderInterface.php`**

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

interface PriceProviderInterface
{
    /**
     * @return array{price: float, currency: string}|null
     */
    public function fetchPrice(string $providerSymbol): ?array;

    /**
     * @param  string[] $symbols
     * @return array<string, array{price: float, currency: string}>
     */
    public function fetchBatch(array $symbols): array;
}
```

- [ ] **Krok 2: Vytvořit `app/Infrastructure/Providers/AlphaVantageProvider.php`**

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

final class AlphaVantageProvider implements PriceProviderInterface
{
    private const BASE_URL = 'https://www.alphavantage.co/query';

    public function __construct(
        private readonly string $apiKey,
    ) {
    }

    public function fetchPrice(string $providerSymbol): ?array
    {
        $url = self::BASE_URL . '?' . http_build_query([
            'function' => 'GLOBAL_QUOTE',
            'symbol'   => $providerSymbol,
            'apikey'   => $this->apiKey,
        ]);

        $data = $this->httpGet($url);
        if ($data === null) {
            return null;
        }

        $quote = $data['Global Quote'] ?? [];
        $price = isset($quote['05. price']) ? (float) $quote['05. price'] : null;

        if ($price === null || $price <= 0.0) {
            return null;
        }

        return ['price' => $price, 'currency' => 'USD'];
    }

    public function fetchBatch(array $symbols): array
    {
        $results = [];
        foreach ($symbols as $symbol) {
            $result = $this->fetchPrice($symbol);
            if ($result !== null) {
                $results[$symbol] = $result;
            }
            // Alpha Vantage free: max 5 req/min — krátká pauza
            usleep(200_000);
        }
        return $results;
    }

    /**
     * Fetch kurzů USD→CZK a EUR→CZK. Vrací ['USD' => float, 'EUR' => float].
     * @return array<string, float>
     */
    public function fetchExchangeRates(): array
    {
        $rates = [];
        foreach (['USD', 'EUR'] as $from) {
            $url = self::BASE_URL . '?' . http_build_query([
                'function'    => 'CURRENCY_EXCHANGE_RATE',
                'from_currency' => $from,
                'to_currency'   => 'CZK',
                'apikey'      => $this->apiKey,
            ]);
            $data = $this->httpGet($url);
            $rate = $data['Realtime Currency Exchange Rate']['5. Exchange Rate'] ?? null;
            if ($rate !== null) {
                $rates[$from] = (float) $rate;
            }
            usleep(200_000);
        }
        return $rates;
    }

    private function httpGet(string $url): ?array
    {
        $ctx = stream_context_create(['http' => ['timeout' => 10]]);
        $response = @file_get_contents($url, false, $ctx);
        if ($response === false) {
            return null;
        }
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }
}
```

- [ ] **Krok 3: Vytvořit `app/Infrastructure/Providers/CoinGeckoProvider.php`**

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

final class CoinGeckoProvider implements PriceProviderInterface
{
    private const BASE_URL = 'https://api.coingecko.com/api/v3';

    public function fetchPrice(string $providerSymbol): ?array
    {
        $result = $this->fetchBatch([$providerSymbol]);
        return $result[$providerSymbol] ?? null;
    }

    public function fetchBatch(array $symbols): array
    {
        if (empty($symbols)) {
            return [];
        }

        $ids = implode(',', $symbols);
        $url = self::BASE_URL . '/simple/price?' . http_build_query([
            'ids'           => $ids,
            'vs_currencies' => 'usd',
        ]);

        $ctx = stream_context_create(['http' => ['timeout' => 10]]);
        $response = @file_get_contents($url, false, $ctx);
        if ($response === false) {
            return [];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return [];
        }

        $results = [];
        foreach ($symbols as $symbol) {
            $price = $data[$symbol]['usd'] ?? null;
            if ($price !== null) {
                $results[$symbol] = ['price' => (float) $price, 'currency' => 'USD'];
            }
        }
        return $results;
    }
}
```

- [ ] **Krok 4: Vytvořit `app/Infrastructure/Providers/YahooFinanceProvider.php`**

PSE formát: `CEZ.PR`, `KB.PR`, `O2CR.PR`, `MONET.PR`.

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

final class YahooFinanceProvider implements PriceProviderInterface
{
    public function fetchPrice(string $providerSymbol): ?array
    {
        $url = 'https://query1.finance.yahoo.com/v8/finance/chart/' . urlencode($providerSymbol)
            . '?interval=1d&range=1d';

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 10,
                'header'  => "User-Agent: Mozilla/5.0\r\n",
            ],
        ]);
        $response = @file_get_contents($url, false, $ctx);
        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);
        $result = $data['chart']['result'][0] ?? null;
        if ($result === null) {
            return null;
        }

        $price = $result['meta']['regularMarketPrice'] ?? null;
        $currency = $result['meta']['currency'] ?? 'USD';

        if ($price === null || $price <= 0.0) {
            return null;
        }

        return ['price' => (float) $price, 'currency' => strtoupper($currency)];
    }

    public function fetchBatch(array $symbols): array
    {
        $results = [];
        foreach ($symbols as $symbol) {
            $result = $this->fetchPrice($symbol);
            if ($result !== null) {
                $results[$symbol] = $result;
            }
            usleep(100_000);
        }
        return $results;
    }
}
```

- [ ] **Krok 5: Commit**

```bash
git add app/Infrastructure/Providers/
git commit -m "feat: Price providers — AlphaVantage, CoinGecko, YahooFinance"
```

---

## Task 5: Application Services

**Files:**
- Create: `app/Application/Security/SecurityService.php`
- Create: `app/Application/Portfolio/PortfolioService.php`
- Create: `app/Application/Watchlist/WatchlistService.php`
- Create: `app/Application/Prices/PriceService.php`
- Create: `app/Application/Prices/PriceFetcherService.php`

- [ ] **Krok 1: Vytvořit `app/Application/Security/SecurityService.php`**

```php
<?php

declare(strict_types=1);

namespace App\Application\Security;

use App\Domain\Security\Security;
use App\Domain\Security\SecurityRepositoryInterface;

final class SecurityService
{
    public function __construct(
        private readonly SecurityRepositoryInterface $securityRepository,
    ) {
    }

    /** @return mixed[] ActiveRow[] pro Latte templates */
    public function getAll(): array
    {
        return $this->securityRepository->findAll();
    }

    /** @return Security[] */
    public function getAllActive(): array
    {
        return $this->securityRepository->findAllActive();
    }

    public function getById(int $id): ?Security
    {
        return $this->securityRepository->findById($id);
    }

    public function create(
        string $ticker,
        string $name,
        string $type,
        string $exchange,
        string $currency,
        string $provider,
        string $providerSymbol,
    ): int {
        $security = new Security(
            id:             null,
            ticker:         strtoupper(trim($ticker)),
            name:           $name,
            type:           $type,
            exchange:       strtoupper($exchange),
            currency:       strtoupper($currency),
            provider:       $provider,
            providerSymbol: $providerSymbol,
            active:         true,
            createdAt:      new \DateTimeImmutable(),
        );
        return $this->securityRepository->save($security);
    }

    public function update(int $id, string $name, string $exchange, string $providerSymbol, bool $active): void
    {
        $existing = $this->securityRepository->findById($id);
        if ($existing === null) {
            throw new \InvalidArgumentException("Security $id nenalezeno.");
        }

        $updated = new Security(
            id:             $id,
            ticker:         $existing->ticker,
            name:           $name,
            type:           $existing->type,
            exchange:       strtoupper($exchange),
            currency:       $existing->currency,
            provider:       $existing->provider,
            providerSymbol: $providerSymbol,
            active:         $active,
            createdAt:      $existing->createdAt,
        );
        $this->securityRepository->save($updated);
    }

    public function delete(int $id): void
    {
        $this->securityRepository->delete($id);
    }
}
```

- [ ] **Krok 2: Vytvořit `app/Application/Prices/PriceService.php`**

```php
<?php

declare(strict_types=1);

namespace App\Application\Prices;

use App\Domain\Price\ExchangeRateRepositoryInterface;
use App\Domain\Price\PriceRepositoryInterface;

final class PriceService
{
    private const STALE_HOURS = 48;

    public function __construct(
        private readonly PriceRepositoryInterface $priceRepository,
        private readonly ExchangeRateRepositoryInterface $exchangeRateRepository,
    ) {
    }

    /**
     * @return array{price: float, currency: string, fetched_at: \DateTimeImmutable}|null
     */
    public function getLatestPrice(int $securityId): ?array
    {
        return $this->priceRepository->findBySecurity($securityId);
    }

    public function getExchangeRate(string $from, string $to): ?float
    {
        return $this->exchangeRateRepository->findRate($from, $to);
    }

    /**
     * Přepočítá částku na CZK. Vrátí null pokud kurz není k dispozici.
     */
    public function convertToCzk(float $amount, string $currency): ?float
    {
        if ($currency === 'CZK') {
            return $amount;
        }
        $rate = $this->exchangeRateRepository->findRate($currency, 'CZK');
        return $rate !== null ? $amount * $rate : null;
    }

    public function isDataStale(?\DateTimeImmutable $fetchedAt): bool
    {
        if ($fetchedAt === null) {
            return true;
        }
        $diff = (new \DateTimeImmutable())->getTimestamp() - $fetchedAt->getTimestamp();
        return $diff > self::STALE_HOURS * 3600;
    }
}
```

- [ ] **Krok 3: Vytvořit `app/Application/Portfolio/PortfolioService.php`**

```php
<?php

declare(strict_types=1);

namespace App\Application\Portfolio;

use App\Application\Prices\PriceService;
use App\Domain\Portfolio\PortfolioPosition;
use App\Domain\Portfolio\PortfolioRepositoryInterface;
use App\Domain\Security\SecurityRepositoryInterface;

final class PortfolioService
{
    public function __construct(
        private readonly PortfolioRepositoryInterface $portfolioRepository,
        private readonly SecurityRepositoryInterface $securityRepository,
        private readonly PriceService $priceService,
    ) {
    }

    /**
     * @return array{
     *   position: PortfolioPosition,
     *   security: \App\Domain\Security\Security,
     *   currentPrice: float|null,
     *   valueCzk: float|null,
     *   pnlCzk: float|null,
     *   pnlPercent: float|null,
     * }[]
     */
    public function getPositionsWithValues(int $investorId): array
    {
        $positions = $this->portfolioRepository->findByInvestorId($investorId);
        $result = [];

        foreach ($positions as $position) {
            $security = $this->securityRepository->findById($position->securityId);
            if ($security === null) {
                continue;
            }

            $priceData = $this->priceService->getLatestPrice($position->securityId);
            $currentPrice = $priceData !== null ? $priceData['price'] : null;
            $priceCurrency = $priceData !== null ? $priceData['currency'] : $security->currency;

            $valueCzk = null;
            $pnlCzk = null;
            $pnlPercent = null;

            if ($currentPrice !== null) {
                $valueCzk = $this->priceService->convertToCzk($currentPrice * $position->quantity, $priceCurrency);
                $costCzk  = $this->priceService->convertToCzk($position->purchasePrice * $position->quantity, $position->purchaseCurrency);
                if ($valueCzk !== null && $costCzk !== null && $costCzk > 0) {
                    $pnlCzk     = $valueCzk - $costCzk;
                    $pnlPercent = (($valueCzk / $costCzk) - 1) * 100;
                }
            }

            $result[] = [
                'position'     => $position,
                'security'     => $security,
                'currentPrice' => $currentPrice,
                'valueCzk'     => $valueCzk,
                'pnlCzk'       => $pnlCzk,
                'pnlPercent'   => $pnlPercent,
            ];
        }

        return $result;
    }

    public function addPosition(
        int $investorId,
        int $securityId,
        float $quantity,
        float $purchasePrice,
        string $purchaseCurrency,
        string $purchasedAt,
        ?string $note,
    ): void {
        $position = new PortfolioPosition(
            id:               null,
            investorId:       $investorId,
            securityId:       $securityId,
            quantity:         $quantity,
            purchasePrice:    $purchasePrice,
            purchaseCurrency: strtoupper($purchaseCurrency),
            purchasedAt:      new \DateTimeImmutable($purchasedAt),
            note:             $note !== '' ? $note : null,
            createdAt:        new \DateTimeImmutable(),
        );
        $this->portfolioRepository->save($position);
    }

    public function deletePosition(int $id): void
    {
        $this->portfolioRepository->delete($id);
    }
}
```

- [ ] **Krok 4: Vytvořit `app/Application/Watchlist/WatchlistService.php`**

```php
<?php

declare(strict_types=1);

namespace App\Application\Watchlist;

use App\Application\Prices\PriceService;
use App\Domain\Security\SecurityRepositoryInterface;
use App\Domain\Watchlist\WatchlistRepositoryInterface;

final class WatchlistService
{
    public function __construct(
        private readonly WatchlistRepositoryInterface $watchlistRepository,
        private readonly SecurityRepositoryInterface $securityRepository,
        private readonly PriceService $priceService,
    ) {
    }

    /**
     * @return array{
     *   security: \App\Domain\Security\Security,
     *   currentPrice: float|null,
     *   priceCurrency: string|null,
     * }[]
     */
    public function getWatchlistWithPrices(int $investorId): array
    {
        $securityIds = $this->watchlistRepository->findSecurityIdsByInvestorId($investorId);
        $result = [];

        foreach ($securityIds as $securityId) {
            $security = $this->securityRepository->findById($securityId);
            if ($security === null) {
                continue;
            }

            $priceData = $this->priceService->getLatestPrice($securityId);
            $result[] = [
                'security'      => $security,
                'currentPrice'  => $priceData !== null ? $priceData['price'] : null,
                'priceCurrency' => $priceData !== null ? $priceData['currency'] : null,
            ];
        }

        return $result;
    }

    public function add(int $investorId, int $securityId): void
    {
        $this->watchlistRepository->add($investorId, $securityId);
    }

    public function remove(int $investorId, int $securityId): void
    {
        $this->watchlistRepository->remove($investorId, $securityId);
    }

    public function isInWatchlist(int $investorId, int $securityId): bool
    {
        return $this->watchlistRepository->exists($investorId, $securityId);
    }
}
```

- [ ] **Krok 5: Vytvořit `app/Application/Prices/PriceFetcherService.php`**

```php
<?php

declare(strict_types=1);

namespace App\Application\Prices;

use App\Domain\Price\ExchangeRateRepositoryInterface;
use App\Domain\Price\PriceRepositoryInterface;
use App\Domain\Security\SecurityRepositoryInterface;
use App\Infrastructure\Providers\AlphaVantageProvider;
use App\Infrastructure\Providers\CoinGeckoProvider;
use App\Infrastructure\Providers\YahooFinanceProvider;

final class PriceFetcherService
{
    private const COOLDOWN_HOURS = 23;

    public function __construct(
        private readonly SecurityRepositoryInterface $securityRepository,
        private readonly PriceRepositoryInterface $priceRepository,
        private readonly ExchangeRateRepositoryInterface $exchangeRateRepository,
        private readonly AlphaVantageProvider $alphaVantage,
        private readonly CoinGeckoProvider $coinGecko,
        private readonly YahooFinanceProvider $yahoo,
    ) {
    }

    /**
     * @return array{ok: int, errors: int, skipped: int}
     */
    public function fetchAll(bool $force = false): array
    {
        $stats = ['ok' => 0, 'errors' => 0, 'skipped' => 0];

        $securities = $this->securityRepository->findAllActive();

        // Seskupit dle providera
        $byProvider = ['alpha_vantage' => [], 'coingecko' => [], 'yahoo' => []];
        foreach ($securities as $security) {
            // Kontrola cooldownu
            if (!$force) {
                $existing = $this->priceRepository->findBySecurity($security->id);
                if ($existing !== null) {
                    $age = (new \DateTimeImmutable())->getTimestamp() - $existing['fetched_at']->getTimestamp();
                    if ($age < self::COOLDOWN_HOURS * 3600) {
                        $stats['skipped']++;
                        continue;
                    }
                }
            }
            $byProvider[$security->provider][$security->providerSymbol] = $security;
        }

        // Alpha Vantage
        if (!empty($byProvider['alpha_vantage'])) {
            $symbols = array_keys($byProvider['alpha_vantage']);
            $results = $this->alphaVantage->fetchBatch($symbols);
            foreach ($byProvider['alpha_vantage'] as $symbol => $security) {
                if (isset($results[$symbol])) {
                    $this->priceRepository->upsert(
                        $security->id,
                        $results[$symbol]['price'],
                        $results[$symbol]['currency'],
                        new \DateTimeImmutable(),
                    );
                    $stats['ok']++;
                } else {
                    $stats['errors']++;
                    fwrite(STDERR, "AlphaVantage: chyba pro {$symbol}\n");
                }
            }
        }

        // CoinGecko
        if (!empty($byProvider['coingecko'])) {
            $symbols = array_keys($byProvider['coingecko']);
            $results = $this->coinGecko->fetchBatch($symbols);
            foreach ($byProvider['coingecko'] as $symbol => $security) {
                if (isset($results[$symbol])) {
                    $this->priceRepository->upsert(
                        $security->id,
                        $results[$symbol]['price'],
                        $results[$symbol]['currency'],
                        new \DateTimeImmutable(),
                    );
                    $stats['ok']++;
                } else {
                    $stats['errors']++;
                    fwrite(STDERR, "CoinGecko: chyba pro {$symbol}\n");
                }
            }
        }

        // Yahoo Finance
        if (!empty($byProvider['yahoo'])) {
            $symbols = array_keys($byProvider['yahoo']);
            $results = $this->yahoo->fetchBatch($symbols);
            foreach ($byProvider['yahoo'] as $symbol => $security) {
                if (isset($results[$symbol])) {
                    $this->priceRepository->upsert(
                        $security->id,
                        $results[$symbol]['price'],
                        $results[$symbol]['currency'],
                        new \DateTimeImmutable(),
                    );
                    $stats['ok']++;
                } else {
                    $stats['errors']++;
                    fwrite(STDERR, "Yahoo: chyba pro {$symbol}\n");
                }
            }
        }

        // FX kurzy
        $this->fetchExchangeRates();

        return $stats;
    }

    public function fetchExchangeRates(): void
    {
        $rates = $this->alphaVantage->fetchExchangeRates();
        $now = new \DateTimeImmutable();
        foreach ($rates as $from => $rate) {
            $this->exchangeRateRepository->upsert($from, 'CZK', $rate, $now);
        }
    }
}
```

- [ ] **Krok 6: Commit**

```bash
git add app/Application/Security/ app/Application/Portfolio/ \
        app/Application/Watchlist/ app/Application/Prices/
git commit -m "feat: Application services — Security, Portfolio, Watchlist, PriceService, PriceFetcherService"
```

---

## Task 6: DI registrace a konfigurace

**Files:**
- Modify: `config/services.neon`
- Modify: `config/common.neon`
- Modify: `config/local.neon.example`

- [ ] **Krok 1: Přidat services do `config/services.neon`**

Za stávající sekci services přidat:

```neon
    # Securities
    - App\Infrastructure\Database\SecurityRepository
    - App\Infrastructure\Database\PortfolioRepository
    - App\Infrastructure\Database\WatchlistRepository
    - App\Infrastructure\Database\PriceRepository
    - App\Infrastructure\Database\ExchangeRateRepository

    # Price Providers
    - App\Infrastructure\Providers\CoinGeckoProvider
    - App\Infrastructure\Providers\YahooFinanceProvider
    - App\Infrastructure\Providers\AlphaVantageProvider(%alphavantage.apiKey%)

    # Application Services — Securities & Portfolio
    - App\Application\Security\SecurityService
    - App\Application\Portfolio\PortfolioService
    - App\Application\Watchlist\WatchlistService
    - App\Application\Prices\PriceService
    - App\Application\Prices\PriceFetcherService
```

- [ ] **Krok 2: Přidat parameters sekci do `config/common.neon`**

Na začátek souboru před `includes:`:

```neon
parameters:
    alphavantage:
        apiKey: ''
```

- [ ] **Krok 3: Přidat příklad do `config/local.neon.example`**

Najít nebo vytvořit `config/local.neon.example` a přidat:

```neon
parameters:
    alphavantage:
        apiKey: 'TVUJ_ALPHA_VANTAGE_API_KLIC'
```

Získání klíče zdarma: https://www.alphavantage.co/support/#api-key

- [ ] **Krok 4: Přidat apiKey do vlastního `config/local.neon`** (gitignored, upravit ručně)

- [ ] **Krok 5: Ověřit, že DI kontejner se sestaví**

```bash
docker compose exec app php -r "require 'vendor/autoload.php'; \$c = App\Bootstrap::boot()->createContainer(); echo 'OK\n';"
```

Výstup musí být `OK` bez chyby.

- [ ] **Krok 6: Commit**

```bash
git add config/services.neon config/common.neon config/local.neon.example
git commit -m "feat: DI registrace — securities, portfolio, providers, price services"
```

---

## Task 7: Cron skript `bin/fetch-prices.php`

**Files:**
- Create: `bin/fetch-prices.php`

- [ ] **Krok 1: Vytvořit `bin/fetch-prices.php`**

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

// Použití: php bin/fetch-prices.php [--force]
// --force přeskočí cooldown 23h a vynutí fetch všech securities

$force = in_array('--force', $argv ?? [], true);

require __DIR__ . '/../vendor/autoload.php';

$container = App\Bootstrap::boot()->createContainer();

/** @var App\Application\Prices\PriceFetcherService $fetcher */
$fetcher = $container->getByType(App\Application\Prices\PriceFetcherService::class);

$start = microtime(true);
$result = $fetcher->fetchAll($force);
$elapsed = round(microtime(true) - $start, 2);

echo sprintf(
    "[%s] Fetch dokončen za %.2fs — OK: %d, Chyby: %d, Přeskočeno: %d\n",
    date('Y-m-d H:i:s'),
    $elapsed,
    $result['ok'],
    $result['errors'],
    $result['skipped'],
);
```

- [ ] **Krok 2: Otestovat skript v Dockeru**

Nejprve přidat testovací security přes adminer nebo SQL:
```bash
docker compose exec db mariadb -u USER -pPASS DBNAME -e \
  "INSERT INTO securities (ticker, name, type, exchange, currency, provider, provider_symbol, active, created_at) VALUES ('BTC', 'Bitcoin', 'crypto', 'CRYPTO', 'USD', 'coingecko', 'bitcoin', 1, NOW());"
```

Spustit cron skript:
```bash
docker compose exec app php bin/fetch-prices.php --force
```

Ověřit výsledek v DB:
```bash
docker compose exec db mariadb -u USER -pPASS DBNAME -e "SELECT * FROM security_prices;"
```

Výstup musí obsahovat řádek s price > 0 pro `security_id = 1`.

- [ ] **Krok 3: Ověřit cooldown** (bez `--force` se přeskočí)

```bash
docker compose exec app php bin/fetch-prices.php
```

Výstup musí mít `Přeskočeno: 1`, `OK: 0`.

- [ ] **Krok 4: Commit**

```bash
git add bin/fetch-prices.php
git commit -m "feat: bin/fetch-prices.php — cron skript pro aktualizaci cen"
```

---

## Task 8: Admin — SecurityPresenter a šablona

**Files:**
- Create: `app/Presentation/Admin/Presenters/SecurityPresenter.php`
- Create: `app/Presentation/Admin/templates/Security/default.latte`
- Modify: `app/Presentation/Admin/templates/@layout.latte`

- [ ] **Krok 1: Vytvořit `app/Presentation/Admin/Presenters/SecurityPresenter.php`**

```php
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
```

- [ ] **Krok 2: Vytvořit `app/Presentation/Admin/templates/Security/default.latte`**

Nejprve zkontrolovat strukturu existující admin šablony (např. Investor/default.latte) a použít stejný vzor. Šablona bude:

```latte
{layout '../@layout.latte'}
{block title}Cenné papíry — Smart Funds Admin{/block}
{block content}

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Cenné papíry</h2>
    <div class="d-flex gap-2">
        <a href="{link fetchNow!}" class="btn btn-outline-secondary" n:href="fetchNow!">
            Aktualizovat ceny
        </a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#securityModal"
                onclick="openSecurityModal(null)">
            + Přidat
        </button>
    </div>
</div>

{snippet list}
<table class="table table-striped table-bordered">
    <thead class="table-dark">
        <tr>
            <th>Ticker</th>
            <th>Název</th>
            <th>Typ</th>
            <th>Burza</th>
            <th>Měna</th>
            <th>Provider</th>
            <th>API Symbol</th>
            <th>Aktivní</th>
            <th>Akce</th>
        </tr>
    </thead>
    <tbody>
        {foreach $securities as $s}
        <tr>
            <td><strong>{$s->ticker}</strong></td>
            <td>{$s->name}</td>
            <td>{$s->type}</td>
            <td>{$s->exchange}</td>
            <td>{$s->currency}</td>
            <td>{$s->provider}</td>
            <td><code>{$s->provider_symbol}</code></td>
            <td>
                {if $s->active}
                    <span class="badge bg-success">Ano</span>
                {else}
                    <span class="badge bg-secondary">Ne</span>
                {/if}
            </td>
            <td>
                <button class="btn btn-sm btn-outline-secondary"
                        data-bs-toggle="modal" data-bs-target="#securityModal"
                        onclick="openSecurityModal({$s->id}, {$s->name|json}, {$s->exchange|json}, {$s->provider_symbol|json}, {$s->active|json})">
                    Upravit
                </button>
                <button class="btn btn-sm btn-outline-danger"
                        data-bs-toggle="modal" data-bs-target="#deleteModal"
                        data-id="{$s->id}">
                    Smazat
                </button>
            </td>
        </tr>
        {else}
        <tr><td colspan="9" class="text-center text-muted">Žádné cenné papíry. Přidejte první.</td></tr>
        {/foreach}
    </tbody>
</table>
{/snippet}

{* --- Security Modal --- *}
{snippet modal}
<div class="modal fade" id="securityModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="securityModalLabel">Cenný papír</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                {control securityForm}
            </div>
        </div>
    </div>
</div>
{/snippet}

{* --- Delete Modal --- *}
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Smazat?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Opravdu smazat tento cenný papír? Akce je nevratná.</p>
                {control deleteForm}
            </div>
        </div>
    </div>
</div>

<script>
function openSecurityModal(id, name, exchange, providerSymbol, active) {
    const form = document.querySelector('#securityModal form');
    if (!id) {
        form.reset();
        form.querySelector('[name="id"]').value = '';
        document.getElementById('securityModalLabel').textContent = 'Přidat cenný papír';
        form.querySelector('[name="ticker"]').disabled = false;
        form.querySelector('[name="type"]').disabled = false;
        form.querySelector('[name="currency"]').disabled = false;
        form.querySelector('[name="provider"]').disabled = false;
    } else {
        form.querySelector('[name="id"]').value = id;
        form.querySelector('[name="name"]').value = name;
        form.querySelector('[name="exchange"]').value = exchange;
        form.querySelector('[name="provider_symbol"]').value = providerSymbol;
        form.querySelector('[name="active"]').checked = !!active;
        document.getElementById('securityModalLabel').textContent = 'Upravit cenný papír';
        form.querySelector('[name="ticker"]').disabled = true;
        form.querySelector('[name="type"]').disabled = true;
        form.querySelector('[name="currency"]').disabled = true;
        form.querySelector('[name="provider"]').disabled = true;
    }
    bootstrap.Modal.getOrCreateInstance(document.getElementById('securityModal')).show();
}

document.querySelectorAll('[data-bs-target="#deleteModal"]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelector('#deleteModal [name="id"]').value = btn.dataset.id;
    });
});

document.addEventListener('naja:success', e => {
    if (e.detail?.payload?.closeModal) {
        document.querySelectorAll('.modal.show').forEach(m =>
            bootstrap.Modal.getOrCreateInstance(m).hide()
        );
    }
});
</script>

{/block}
```

- [ ] **Krok 3: Přidat link do admin navigace `@layout.latte`**

Najít v `app/Presentation/Admin/templates/@layout.latte` sekci navigačních odkazů a přidat:

```latte
<li class="nav-item">
    <a class="nav-link{if $presenter->name === 'Admin:Security'} active{/if}" n:href=":Admin:Security:default">
        Cenné papíry
    </a>
</li>
```

- [ ] **Krok 4: Ověřit v prohlížeči**

```bash
docker compose exec app composer install
```

Otevřít http://localhost:8080/admin/security — ověřit:
- Tabulka se zobrazuje (prázdná)
- "Přidat" modal se otevře a formulář je vyplnitelný
- Uložení přidá řádek do tabulky

- [ ] **Krok 5: Commit**

```bash
git add app/Presentation/Admin/Presenters/SecurityPresenter.php \
        app/Presentation/Admin/templates/Security/ \
        app/Presentation/Admin/templates/@layout.latte
git commit -m "feat: Admin SecurityPresenter — CRUD katalogu cenných papírů"
```

---

## Task 9: Investor Dashboard — Portfolio a Watchlist tabs

**Files:**
- Modify: `app/Presentation/Investor/Presenters/DashboardPresenter.php`
- Modify: `app/Presentation/Investor/templates/Dashboard/default.latte`
- Create: `app/Presentation/Investor/templates/Dashboard/_portfolio.latte`
- Create: `app/Presentation/Investor/templates/Dashboard/_watchlist.latte`

- [ ] **Krok 1: Upravit `app/Presentation/Investor/Presenters/DashboardPresenter.php`**

```php
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
        $this->template->transactions    = $transactions;
        $this->template->totalInvested   = $totalInvested;
        $this->template->fundCount       = count($fundIds);
        $this->template->transactionCount = count($transactions);
        $this->template->fundNames       = $fundNames;

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
```

- [ ] **Krok 2: Upravit `app/Presentation/Investor/templates/Dashboard/default.latte`**

Nahradit celý obsah:

```latte
{layout '../@layout.latte'}
{block title}Moje portfolio — Smart Funds{/block}
{block content}

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Moje portfolio</h2>
    {if $canRefresh}
        <a n:href="refreshPrices!" class="btn btn-outline-secondary btn-sm">
            Aktualizovat ceny
        </a>
    {else}
        <button class="btn btn-outline-secondary btn-sm" disabled title="Dostupné za hodinu">
            Aktualizovat ceny
        </button>
    {/if}
</div>

<ul class="nav nav-tabs mb-4" id="dashboardTabs">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="tab" href="#tab-transactions">Transakce do fondů</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#tab-portfolio">Portfolio akcií</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#tab-watchlist">Watchlist</a>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="tab-transactions">
        {* --- Stávající transakce sekce --- *}
        <div class="row g-3 mb-4">
            <div class="col-sm-4">
                <div class="card text-center border-primary">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-1 text-muted">Celkem investováno do fondů</h6>
                        <p class="card-text fs-4 fw-bold text-primary">{number_format($totalInvested, 2, ',', ' ')} Kč</p>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-1 text-muted">Počet transakcí</h6>
                        <p class="card-text fs-4 fw-bold">{$transactionCount}</p>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-1 text-muted">Počet fondů</h6>
                        <p class="card-text fs-4 fw-bold">{$fundCount}</p>
                    </div>
                </div>
            </div>
        </div>

        <table class="table table-striped table-bordered">
            <thead class="table-dark">
                <tr><th>#</th><th>Fond</th><th>Částka</th><th>Datum</th></tr>
            </thead>
            <tbody>
                {foreach $transactions as $t}
                <tr>
                    <td>{$t->id}</td>
                    <td>{isset($fundNames[$t->fundId]) ? $fundNames[$t->fundId] : $t->fundId}</td>
                    <td>{number_format($t->amount, 2, ',', ' ')} Kč</td>
                    <td>{$t->createdAt->format('j. n. Y')}</td>
                </tr>
                {else}
                <tr><td colspan="4" class="text-center text-muted">Zatím žádné transakce</td></tr>
                {/foreach}
            </tbody>
        </table>
    </div>

    <div class="tab-pane fade" id="tab-portfolio">
        {include '_portfolio.latte'}
    </div>

    <div class="tab-pane fade" id="tab-watchlist">
        {include '_watchlist.latte'}
    </div>
</div>

{/block}
```

- [ ] **Krok 3: Vytvořit `app/Presentation/Investor/templates/Dashboard/_portfolio.latte`**

```latte
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>
        Portfolio
        {if $totalPortfolioCzk > 0}
            <span class="text-muted fs-6">— aktuální hodnota: <strong>{number_format($totalPortfolioCzk, 0, ',', ' ')} Kč</strong></span>
        {/if}
    </h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPositionModal">
        + Přidat nákup
    </button>
</div>

{snippet portfolioContent}
{var $anyStale = false}
{foreach $positions as $p}
    {if $p['currentPrice'] === null}{var $anyStale = true}{/if}
{/foreach}

{if $anyStale}
    <div class="alert alert-warning py-2">
        <small>U některých cenných papírů není cena dostupná. Spusťte "Aktualizovat ceny".</small>
    </div>
{/if}

<table class="table table-striped table-hover table-bordered align-middle">
    <thead class="table-dark">
        <tr>
            <th>Ticker</th>
            <th>Název</th>
            <th class="text-end">Počet ks</th>
            <th class="text-end">Nák. cena</th>
            <th class="text-end">Akt. cena</th>
            <th class="text-end">Hodnota (CZK)</th>
            <th class="text-end">Změna</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        {foreach $positions as $p}
        {var $pos = $p['position']}
        {var $sec = $p['security']}
        <tr>
            <td><strong>{$sec->ticker}</strong></td>
            <td>{$sec->name}</td>
            <td class="text-end">{number_format($pos->quantity, 6, ',', ' ')}</td>
            <td class="text-end">{number_format($pos->purchasePrice, 2, ',', ' ')} {$pos->purchaseCurrency}</td>
            <td class="text-end">
                {if $p['currentPrice'] !== null}
                    {number_format($p['currentPrice'], 2, ',', ' ')} {$sec->currency}
                {else}
                    <span class="text-muted">—</span>
                {/if}
            </td>
            <td class="text-end">
                {if $p['valueCzk'] !== null}
                    {number_format($p['valueCzk'], 0, ',', ' ')} Kč
                {else}
                    <span class="text-muted">—</span>
                {/if}
            </td>
            <td class="text-end">
                {if $p['pnlPercent'] !== null}
                    {var $cls = $p['pnlPercent'] >= 0 ? 'text-success' : 'text-danger'}
                    <span class="{$cls}">
                        {if $p['pnlPercent'] >= 0}+{/if}{number_format($p['pnlPercent'], 2, ',', ' ')} %
                    </span>
                {else}
                    <span class="text-muted">—</span>
                {/if}
            </td>
            <td>
                <a n:href="deletePosition!, id: $pos->id"
                   class="btn btn-sm btn-outline-danger"
                   onclick="return confirm('Smazat tuto pozici?')">
                    Smazat
                </a>
            </td>
        </tr>
        {else}
        <tr><td colspan="8" class="text-center text-muted">Žádné pozice. Přidejte první nákup.</td></tr>
        {/foreach}
    </tbody>
</table>
{/snippet}

{* --- Přidat nákup modal --- *}
{snippet addPositionModal}
<div class="modal fade" id="addPositionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Přidat nákup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                {control addPositionForm}
            </div>
        </div>
    </div>
</div>
{/snippet}

<script>
document.addEventListener('naja:success', e => {
    if (e.detail?.payload?.closeModal) {
        document.querySelectorAll('.modal.show').forEach(m =>
            bootstrap.Modal.getOrCreateInstance(m).hide()
        );
    }
});
</script>
```

- [ ] **Krok 4: Vytvořit `app/Presentation/Investor/templates/Dashboard/_watchlist.latte`**

```latte
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Watchlist</h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addWatchlistModal">
        + Přidat
    </button>
</div>

{snippet watchlistContent}
<table class="table table-striped table-hover table-bordered align-middle">
    <thead class="table-dark">
        <tr>
            <th>Ticker</th>
            <th>Název</th>
            <th>Typ</th>
            <th class="text-end">Akt. cena</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        {foreach $watchlist as $w}
        {var $sec = $w['security']}
        <tr>
            <td><strong>{$sec->ticker}</strong></td>
            <td>{$sec->name}</td>
            <td><span class="badge bg-secondary">{$sec->type}</span></td>
            <td class="text-end">
                {if $w['currentPrice'] !== null}
                    {number_format($w['currentPrice'], 2, ',', ' ')} {$w['priceCurrency']}
                {else}
                    <span class="text-muted">Nedostupná</span>
                {/if}
            </td>
            <td>
                <a n:href="removeWatchlist!, securityId: $sec->id"
                   class="btn btn-sm btn-outline-danger">
                    Odebrat
                </a>
            </td>
        </tr>
        {else}
        <tr><td colspan="5" class="text-center text-muted">Watchlist je prázdný. Přidejte cenné papíry ke sledování.</td></tr>
        {/foreach}
    </tbody>
</table>
{/snippet}

{* --- Přidat do watchlistu modal --- *}
{snippet addWatchlistModal}
<div class="modal fade" id="addWatchlistModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Přidat do watchlistu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                {control addWatchlistForm}
            </div>
        </div>
    </div>
</div>
{/snippet}

<script>
document.addEventListener('naja:success', e => {
    if (e.detail?.payload?.closeModal) {
        document.querySelectorAll('.modal.show').forEach(m =>
            bootstrap.Modal.getOrCreateInstance(m).hide()
        );
    }
});
</script>
```

- [ ] **Krok 5: Ověřit v prohlížeči**

```bash
docker compose exec app composer install 2>/dev/null; echo "OK"
```

Přihlásit se jako investor → otevřít http://localhost:8080/investor/dashboard

Ověřit:
1. Zobrazí se tři taby (Transakce, Portfolio akcií, Watchlist)
2. Tab "Transakce do fondů" zobrazuje stávající data beze změn
3. Tab "Portfolio akcií" je prázdný s tlačítkem "+ Přidat nákup"
4. Tab "Watchlist" je prázdný s tlačítkem "+ Přidat"

- [ ] **Krok 6: End-to-end test portfolia**

  1. Admin → /admin/security → přidat `AAPL` (Alpha Vantage, stock, NYSE, USD, `AAPL`)
  2. Admin → přidat `BTC` (CoinGecko, crypto, CRYPTO, USD, `bitcoin`)
  3. `docker compose exec app php bin/fetch-prices.php --force` → ověřit výstup OK: 2
  4. Investor → Portfolio tab → "+ Přidat nákup" → AAPL, 10 ks, 150 USD, datum dnešek → Přidat
  5. Ověřit: řádek v tabulce s hodnotou v CZK (potřebuje USD→CZK kurz)
  6. Investor → Watchlist tab → "+ Přidat" → BTC → Přidat → ověřit zobrazení ceny

- [ ] **Krok 7: Commit**

```bash
git add app/Presentation/Investor/Presenters/DashboardPresenter.php \
        app/Presentation/Investor/templates/Dashboard/
git commit -m "feat: Investor Dashboard — Portfolio a Watchlist tabs s AJAX formuláři"
```

---

## Task 10: Wedos cron konfigurace (dokumentace)

Toto je informační krok — žádný kód, pouze konfigurace na hostingu.

- [ ] **Krok 1: Ověřit deploy na Wedos**

Zkontrolovat, že `config/local.neon` na Wedos serveru obsahuje:
```neon
parameters:
    alphavantage:
        apiKey: 'TVUJ_KLIC'
```

- [ ] **Krok 2: Nastavit cron na Wedos cPanelu**

V cPanelu → Cron Jobs → přidat:

```
0 6 * * * php /home/USERNAME/www/bin/fetch-prices.php >> /home/USERNAME/logs/prices.log 2>&1
```

Spouštět 1× denně v 6:00. Přizpůsobit cestu dle skutečného umístění projektu.

- [ ] **Krok 3: Commit (update CLAUDE.md)**

Přidat do `CLAUDE.md` sekce o cron:

```markdown
## Cron — Fetch cen

- Skript: `bin/fetch-prices.php [--force]`
- Docker: `docker compose exec app php bin/fetch-prices.php`
- Wedos: cron přes cPanel, 1× denně v 6:00
- Alpha Vantage free klíč: 25 req/den — nespouštět vícekrát denně bez `--force`
```

```bash
git add CLAUDE.md
git commit -m "docs: CLAUDE.md — cron fetch-prices konfigurace"
```

---

## Self-Review checklist

- [x] DB migrace pokrývá všech 5 tabulek ze spec
- [x] Security entity i interface konzistentní (findAllActive vrací Security[], findAll vrací ActiveRow[])
- [x] `\DateTimeImmutable::createFromInterface()` použito ve všech repositories (ne `new \DateTimeImmutable($row->date)`)
- [x] AlphaVantageProvider injectován s API klíčem z DI parametru `%alphavantage.apiKey%`
- [x] PriceFetcherService: cooldown 23h při `$force=false`; `fwrite(STDERR, ...)` pro chyby
- [x] Latte signály: `{n:href="deletePosition!, id: $pos->id"}` — BEZ prefixu `handle`
- [x] AJAX formuláře: `$form->getElementPrototype()->addClass('ajax')` + redrawControl
- [x] `portfolio_positions.purchased_at` je DATE, ne DATETIME — `format('Y-m-d')` v repository
- [x] Router: existující `admin/<presenter>[/<action>[/<id>]]` pokrývá `Admin:Security:*` — žádná změna RouterFactory
- [x] `getInvestorId()` refaktorováno jako private metoda (DRY)

# Smart Funds — CLAUDE.md

## O projektu

Portfoliová webová aplikace pro správu investičních fondů. Spravuje **fondy, investory a transakce**.
Hybridní přístup: admin spravuje vše, investoři vidí svá data.

## Technologie

- PHP 8.5 (Wedos hosting + Docker)
- Nette Framework 3.x (Application, Bootstrap, Database, DI, Forms, Security)
- Latte 3.x
- MariaDB 11 (DSN: `mysql:` — stejný driver jako MySQL)
- Nette Database Explorer (ActiveRow, Selection API)

## Produkční hosting

**Wedos webhosting** — Apache + mod_php, PHP 8.5, MariaDB. Docker lokálně simuluje totéž prostředí.

## Architektura

**DDD-inspirovaná čtyřvrstvá architektura:**

```
app/
├── Domain/           ← entity + repository interfaces (čistá doména)
├── Application/      ← services / use cases (business logika)
├── Infrastructure/   ← DB implementace repozitářů + RouterFactory
└── Presentation/
    ├── Front/        ← veřejný modul (homepage)
    ├── Admin/        ← admin panel (CRUD nad vším)
    └── Investor/     ← investor dashboard (vlastní data)
```

**Klíčové pravidlo:** Business logika **pouze v Application services**, nikdy v Presenterech.
Presentery volají pouze Application services, ne přímo repositáře.

## Moduly a routování

| URL | Modul | Presenter |
|-----|-------|-----------|
| `/` | Front | Home |
| `/admin` | Admin | Dashboard |
| `/admin/funds` | Admin | Fund |
| `/admin/investors` | Admin | Investor |
| `/admin/transactions` | Admin | Transaction |
| `/investor` | Investor | Dashboard |

## Spuštění (Docker)

```bash
docker compose up -d        # spustit prostředí
docker compose down         # zastavit
docker compose logs -f app  # logy PHP/Apache
```

- Aplikace: http://localhost:8080
- Adminer (DB GUI): http://localhost:8081

DB credentials pro Docker: `.env.docker` (gitignored).

## Spuštění (bez Dockeru)

```bash
composer install
php -S localhost:8000 -t www
```

DB credentials: `config/local.neon` (gitignored).

## Docker struktura

| Soubor | Účel |
|--------|------|
| `Dockerfile` | php:8.5-apache + extensions (pdo_mysql, intl, unzip, git) + Composer |
| `docker-compose.yml` | Services: app (8080), db MariaDB (3306), adminer (8081) |
| `.env.docker` | DB credentials pro Docker (gitignored) |
| `docker/apache/vhost.conf` | Apache VirtualHost → DocumentRoot www/ |
| `docker/php/php.ini` | Dev overrides (display_errors, memory_limit 256M) |

## Docker — gotcha

- `vendor/` je gitignored — po prvním `docker compose up -d` spustit: `docker compose exec app composer install`
- Dockerfile potřebuje `unzip` a `git` pro Composer (již přidáno) — bez nich `composer install` selže

## Klíčové soubory

| Soubor | Účel |
|--------|------|
| `app/Bootstrap.php` | Inicializace DI kontejneru |
| `config/common.neon` | App mapping, session; includuje services.neon |
| `config/services.neon` | DI binding: router, repositories, services |
| `config/local.neon` | DB credentials + options (gitignored) |
| `config/local.neon.example` | Template pro local.neon (zkopírovat a doplnit credentials) |
| `db/schema.sql` | DDL schéma: tabulky funds, investors, transactions |
| `app/Infrastructure/RouterFactory.php` | Definice URL rout |

## Nette konvence (důležité)

- **Links v Latte:** relativní `Presenter:action`, absolutní cross-module `:Module:Presenter:action`
- **Blocks:** layout má `{block content}{/block}`, child šablony ho vyplní `{block content}...{/block}`; **child šablona musí začínat `{layout '../@layout.latte'}`** — bez toho se layout vůbec nenačte
- **Router:** registrován jako pojmenovaná služba `router:` v `services.neon`
- **DB config:** `common.neon` includuje `services.neon`; `local.neon` má `convertBoolean: true`, `newDateTime: true`
- **Presentery:** konstruktor bez `parent::__construct()`, DI přes constructor injection
- **Docker DB host:** v `config/local.neon` použít `host=db` (název Docker service)

## Nette AJAX (naja 2.x) — vzory

- **Inicializace:** `const naja = window.naja.default; naja.initialize();` (UMD CDN exposes `.default`)
- **AJAX formulář:** `$form->getElementPrototype()->addClass('ajax')` — přidá třídu na `<form>`, naja zachytí submit
- **Snippety:** `{snippet name}...{/snippet}` v Latte + `$this->redrawControl('name')` v presenteru
- **Payload:** `$this->payload->closeModal = true` posílá JSON payload; naja `success` event ho přečte
- **Chyba formuláře:** v `$form->onError[]` zavolat `$this->redrawControl('modal')` pro překreslení modalu s chybami
- **Bootstrap Modal:** použít `bootstrap.Modal.getOrCreateInstance(el).show()` — ne `new bootstrap.Modal(el)`
- **XSS v onclick:** nikdy `{$var|json}` v HTML atributu — vždy `data-*="{$var}"` + čtení přes `btn.dataset.*` (Latte 3 auto-escapuje dle kontextu)
- **`escapeHtmlAttr` neexistuje:** Latte 3 nemá tento filtr — v atributu stačí `{$var}`, auto-escaping se postará o správné escapování
- **Číselné pole:** `$form->addFloat('field', 'Label')` místo `addText()` + `Form::Float` pravidla
- **Repository save():** `created_at` pouze v INSERT větvi, ne v UPDATE — jinak přepisuje originální datum

## DB schéma

- Soubor: `db/schema.sql` — tabulky `funds`, `investors`, `transactions` (MariaDB InnoDB, utf8mb4)
- Docker auto-import: `./db` je namountován jako `/docker-entrypoint-initdb.d` — spustí se při **prvním** startu (prázdný volume)
- Ruční import do existujícího volume: `docker compose exec -T db mariadb -u USER -pPASS DBNAME < db/schema.sql`

## Co zatím není implementováno

- Autentizace (stub `checkRequirements()` v BaseAdminPresenter a BaseInvestorPresenter)
- CSRF ochrana delete signálů (plain HTML formuláře bez Nette Form nemají token — přidat s autentizací)
- Investor dashboard (vlastní data investora)
- Frontend CSS pro Front a Investor modul

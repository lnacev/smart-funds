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
| `/sign/in` | Front | Sign (login) |
| `/sign/out` | Front | Sign (logout) |

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
- Tabulka `users` se při prvním startu vytvoří automaticky z `db/schema.sql`; při existujícím volume spustit: `docker compose exec -T db mariadb -u USER -pPASS DBNAME < db/schema.sql`
- První admin se vytvoří CLI seederem: `docker compose exec app php bin/create-admin.php email heslo`
- **PHP skripty v Dockeru:** pro víceřádkový PHP kód použít heredoc `docker compose exec app php << 'EOF' ... EOF`; `php -r "..."` s `{$obj->prop}` interpolací v bashi nefunguje správně
- **Testování přihlášení:** stará session v prohlížeči může způsobit false "nesprávné heslo" i se správnými credentials — testovat v inkognitu nebo vymazat cookies pro `localhost`

## Klíčové soubory

| Soubor | Účel |
|--------|------|
| `app/Bootstrap.php` | Inicializace DI kontejneru |
| `config/common.neon` | App mapping, session; includuje services.neon |
| `config/services.neon` | DI binding: router, repositories, services |
| `config/local.neon` | DB credentials + options (gitignored) |
| `config/local.neon.example` | Template pro local.neon (zkopírovat a doplnit credentials) |
| `db/schema.sql` | DDL schéma: tabulky funds, investors, transactions, users |
| `app/Infrastructure/RouterFactory.php` | Definice URL rout |
| `app/Domain/User/` | User entita + UserRepositoryInterface |
| `app/Application/User/UserService.php` | Vytváření admin/investor účtů, update, delete |
| `app/Application/User/Authenticator.php` | Nette Security authenticator (email + heslo) |
| `app/Infrastructure/Database/UserRepository.php` | DB implementace UserRepositoryInterface |
| `app/Application/Dashboard/DashboardService.php` | Globální stats + per-fond agregace pro admin dashboard |
| `bin/create-admin.php` | CLI seeder pro prvního admina |

## Nette konvence (důležité)

- **Links v Latte:** relativní `Presenter:action`, absolutní cross-module `:Module:Presenter:action`
- **Blocks:** layout má `{block content}{/block}`, child šablony ho vyplní `{block content}...{/block}`; **child šablona musí začínat `{layout '../@layout.latte'}`** — bez toho se layout vůbec nenačte
- **Router:** registrován jako pojmenovaná služba `router:` v `services.neon`
- **DB config:** `common.neon` includuje `services.neon`; `local.neon` má `convertBoolean: true`, `newDateTime: true`
- **Presentery:** konstruktor bez `parent::__construct()`, DI přes constructor injection
- **Docker DB host:** v `config/local.neon` použít `host=db` (název Docker service)

## Nette AJAX (naja 2.x) — vzory

- **Inicializace:** `const naja = window.naja; naja.initialize();` — UMD bundle (`Naja.js`) exponuje instanci přímo jako `window.naja` (bez `.default`); naja se servíruje lokálně z `www/js/naja.min.js` (verze 2.6.1, staženo z jsdelivr)
- **AJAX formulář:** `$form->getElementPrototype()->addClass('ajax')` — přidá třídu na `<form>`, naja zachytí submit
- **Snippety:** `{snippet name}...{/snippet}` v Latte + `$this->redrawControl('name')` v presenteru
- **Payload:** `$this->payload->closeModal = true` posílá JSON payload; naja `success` event ho přečte
- **Chyba formuláře:** v `$form->onError[]` zavolat `$this->redrawControl('modal')` pro překreslení modalu s chybami
- **Bootstrap Modal:** použít `bootstrap.Modal.getOrCreateInstance(el).show()` — ne `new bootstrap.Modal(el)`
- **XSS v onclick:** nikdy `{$var|json}` v HTML atributu — vždy `data-*="{$var}"` + čtení přes `btn.dataset.*` (Latte 3 auto-escapuje dle kontextu)
- **`escapeHtmlAttr` neexistuje:** Latte 3 nemá tento filtr — v atributu stačí `{$var}`, auto-escaping se postará o správné escapování
- **Číselné pole:** `$form->addFloat('field', 'Label')` místo `addText()` + `Form::Float` pravidla
- **Repository save():** `created_at` pouze v INSERT větvi, ne v UPDATE — jinak přepisuje originální datum
- **Signály v Latte:** `{link delete!, id: $id}` — název signálu BEZ prefixu `handle`; metoda `handleDelete()` = signál `delete` (jinak Nette hledá `handlehandleDelete` → Tracy error)
- **CSRF ochrana:** `$form->addProtection()` na Nette Form komponentě — přidá `_token_` hidden field; plain HTML form token nemá
- **Delete s CSRF:** místo `handleDelete(int $id)` signálu použít `createComponentDeleteForm()` s `addProtection()` + `addHidden('id')` — JS nastavuje `[name="id"]` hodnotu před odesláním
- **Nette Security role:** `$this->getUser()->isInRole('admin')` — metoda `getRole()` neexistuje, správně je `getRoles()` (vrací array) nebo `isInRole()`
- **newDateTime: true gotcha:** s tímto nastavením vrací Nette Database datetime sloupce jako `Nette\Database\DateTime` (extends `DateTimeImmutable`) — nelze předat do `new \DateTimeImmutable($row->created_at)`, použít `\DateTimeImmutable::createFromInterface($row->created_at)`

## Autentizace

- **Tabulka `users`:** `id`, `email`, `password_hash`, `role` (admin|investor), `investor_id` (FK → investors), `created_at`
- **Flow:** přihlášení na `/sign/in` → `SignPresenter` → `Nette\Security\User::login()` → redirect dle role
- **Admin** → `:Admin:Dashboard:default`, **investor** → `:Investor:Dashboard:default`
- **Ochrana modulů:** `checkRequirements()` v `BaseAdminPresenter` / `BaseInvestorPresenter` volá `isInRole()`
- **Vytvoření investora:** admin vyplní jméno + email + heslo → `UserService::createInvestor()` → atomicky vytvoří `investors` + `users` záznam
- **První admin:** `docker compose exec app php bin/create-admin.php email heslo`

## DB schéma

- Soubor: `db/schema.sql` — tabulky `funds`, `investors`, `transactions`, `users` (MariaDB InnoDB, utf8mb4)
- Docker auto-import: `./db` je namountován jako `/docker-entrypoint-initdb.d` — spustí se při **prvním** startu (prázdný volume)
- Ruční import do existujícího volume: `docker compose exec -T db mariadb -u USER -pPASS DBNAME < db/schema.sql`

## CI/CD — Deploy na Wedos

- **Workflow:** `.github/workflows/deploy.yml` — spustí se při push do `main`
- **Akce:** `SamKirkland/FTP-Deploy-Action@v4.3.4`, protokol `ftps`
- **Secrets v GitHub repo:** `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`, `FTP_SERVER_DIR`
- **`FTP_SERVER_DIR`** musí ukazovat na kořen projektu (kde je `app/`, `www/`), ne dovnitř `www/`
- **`config/local.neon`** na serveru musí existovat ručně — není v repo (gitignored), workflow ho nikdy nepřepíše
- **Před deployem:** workflow spustí `composer install --no-dev --optimize-autoloader`
- **Stav deploye:** akce si drží `.ftp-deploy-sync-state.json` na FTP serveru — nahrává jen změněné soubory

## Co zatím není implementováno

- Změna hesla pro investora přes admin panel
- Přiřazení user účtu k existujícímu investorskému záznamu (historická data bez účtu)
- CSRF ochrana na front-end (delete signály jsou chráněné přes Nette Form `addProtection()`; admin modul vyžaduje přihlášení)
- Investor dashboard — zobrazení jmen fondů čerpá z FundService (ActiveRows, ne entity) — intentionally

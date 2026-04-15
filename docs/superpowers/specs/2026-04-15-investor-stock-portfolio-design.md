# Investor Dashboard — Akcie, Portfolio & Watchlist — Design Spec

## Kontext

Investor dashboard aktuálně zobrazuje pouze transakce do investičních fondů. Tato specifikace přidává:
- **Portfolio**: investor zaznamenává vlastní nákupy akcií/ETF/krypto (ticker, počet ks, nákupní cena, datum). Dashboard zobrazuje aktuální hodnotu pozic v CZK.
- **Watchlist**: investor si přidává cenné papíry ke sledování bez evidence vlastnictví.
- **Price cache**: ceny se načítají z externích API a cachují v DB. Cron skript 1× denně + ruční refresh.

Inspirace: Monery.io, Fio broker portfolio tracker.

---

## Trhy a API

| Typ | Provider | Poznámka |
|-----|----------|----------|
| US akcie, ETF | **Alpha Vantage** (free, 25 req/den) | Endpoint `GLOBAL_QUOTE`, FX přes `CURRENCY_EXCHANGE_RATE` |
| Krypto | **CoinGecko** (free, 30 req/min) | Batch endpoint `simple/price` |
| PSE (Praha) | **Yahoo Finance** neofic. | `query1.finance.yahoo.com/v8/finance/chart/{symbol}` |

Základní měna pro zobrazení: **CZK**. USD/EUR→CZK přes Alpha Vantage FX.

---

## Datový model — nové tabulky

```
securities          — katalog CP (admin spravuje)
security_prices     — cache cen (1 řádek na security, UPSERT)
portfolio_positions — nákupní záznamy investora
watchlist           — oblíbené CP investora
exchange_rates      — cache FX kurzů (USD→CZK, EUR→CZK)
```

Viz `db/migrations/001_securities.sql`.

---

## Architektura

Respektuje stávající DDD 4-vrstvou strukturu:

```
Domain/Security/, Domain/Portfolio/, Domain/Watchlist/, Domain/Price/
Application/Security/, Application/Portfolio/, Application/Watchlist/, Application/Prices/
Infrastructure/Database/ (5 nových repositories)
Infrastructure/Providers/ (AlphaVantage, CoinGecko, Yahoo)
Presentation/Admin/ (SecurityPresenter — CRUD katalogu)
Presentation/Investor/ (DashboardPresenter rozšíření — Portfolio + Watchlist tabs)
bin/fetch-prices.php (cron entry-point)
```

---

## UX

**Admin:** `/admin/security` — tabulka cenných papírů, formulář pro přidání/editaci, tlačítko "Fetch now".

**Investor:** `/investor/dashboard` — Bootstrap tabs:
- **Tab "Transakce"** — stávající zobrazení (beze změny)
- **Tab "Portfolio"** — tabulka pozic: Ticker | Název | Ks | Nákupní cena | Akt. cena | Hodnota CZK | Změna %. Tlačítko "Přidat nákup" (AJAX modal). Badge "Zastaralá data" pokud fetched_at > 48h.
- **Tab "Watchlist"** — tabulka sledovaných: Ticker | Název | Akt. cena | Denní zdroj. Tlačítko "Přidat" (AJAX modal).

Ruční refresh cen: tlačítko s cooldownem 1h (timestamp v session).

---

## Error handling

- Alpha Vantage 429 / prázdná odpověď → logovat, zachovat poslední platnou cenu
- Yahoo Finance selhání → cena `NULL`, zobrazit "Nedostupná"
- fetched_at > 48h → žlutý badge "Zastaralá data"
- AlphaVantage: `security_prices` UPSERT přeskočí securities s `fetched_at < 23h` (pokud `$force = false`)

---

## Schváleno

Datum: 2026-04-15. Schválil: Luděk.

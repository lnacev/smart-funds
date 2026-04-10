# Admin CRUD UI — Design spec

**Datum:** 2026-04-10
**Scope:** Admin modul — fondy, investoři, transakce

---

## Cíl

Implementovat plně funkční CRUD rozhraní pro tři entity (`Fund`, `Investor`, `Transaction`) v admin modulu. Formuláře se otevírají jako Bootstrap modaly, akce probíhají přes Nette AJAX (naja) bez překreslení celé stránky. Smazání se potvrzuje v Bootstrap modalu.

---

## Technologie

- **Bootstrap 5** — CDN (CSS + JS bundle)
- **naja** — CDN, moderní Nette AJAX knihovna
- **Nette Forms** — serverová validace, komponenty v presenterech
- **Nette snippety** — selektivní překreslení tabulky a formulářového modalu

---

## Backend

### 1. Repository interfaces

Každý interface (`FundRepositoryInterface`, `InvestorRepositoryInterface`, `TransactionRepositoryInterface`) rozšíří o:

```php
public function delete(int $id): void;
```

### 2. Infrastructure — implementace delete

```php
// FundRepository (stejný vzor pro Investor, Transaction)
public function delete(int $id): void
{
    $this->database->table('funds')->get($id)?->delete();
}
```

### 3. Application services

Každá service (`FundService`, `InvestorService`, `TransactionService`) rozšíří o:

```php
public function update(int $id, string $name): void;   // Fund
public function delete(int $id): void;
```

`InvestorService::update(int $id, string $name, string $email): void`
`TransactionService::update(int $id, int $fundId, int $investorId, float $amount): void`

### 4. Presentery

Každý presenter (`FundPresenter`, `InvestorPresenter`, `TransactionPresenter`) obsahuje:

| Metoda | Popis |
|--------|-------|
| `actionDefault()` | Načte seznam; Transaction navíc naplní `$selectFunds`, `$selectInvestors` |
| `createComponentFundForm()` | Vrátí `UI\Form`; vytvoří nebo aktualizuje dle přítomnosti `id` |
| `handleSave(?int $id)` | Zpracuje signal z formuláře; při úspěchu invaliduje snippet `list`, pošle payload `closeModal: true` |
| `handleDelete(int $id)` | Smaže entitu; invaliduje snippet `list`, pošle payload `closeModal: true` |

Signály jsou AJAX-only — pokud přijdou bez AJAXu, presenter přesměruje zpět na `default`.

**Pozn. k formuláři:** `createComponentFundForm()` vrátí vždy prázdný formulář; předplnění pro edit obstarává JavaScript (`openFundModal(id, name)`), který naplní hidden `id` input a textové pole před otevřením modalu. Server rozlišuje create vs. update podle přítomnosti `id` v POSTu.

---

## Frontend

### Layout — `@layout.latte`

- Přidat Bootstrap 5 CDN (CSS v `<head>`, JS bundle před `</body>`)
- Přidat naja CDN před `</body>`
- Přidat `{block scripts}{/block}` na konci `<body>` za CDN scripty

### Struktura šablony entity (př. `Fund/default.latte`)

```latte
{block content}

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Fondy</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#fundModal"
            onclick="openFundModal(null)">+ Přidat fond</button>
</div>

{snippet list}
    <table class="table table-striped">
        <thead>...</thead>
        <tbody>
            {foreach $funds as $fund}
            <tr>
                <td>{$fund->id}</td>
                <td>{$fund->name}</td>
                <td>{$fund->created_at|date:'j. n. Y'}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary ajax"
                            data-bs-toggle="modal" data-bs-target="#fundModal"
                            onclick="openFundModal({$fund->id}, '{$fund->name|escapeJs}')">Upravit</button>
                    <button class="btn btn-sm btn-outline-danger"
                            data-id="{$fund->id}" data-name="{$fund->name|escapeJs}"
                            data-delete-url="{link handleDelete! $fund->id}"
                            onclick="openDeleteModal(this)">Smazat</button>
                </td>
            </tr>
            {/foreach}
        </tbody>
    </table>
{/snippet}

{* Formulářový modal *}
{snippet modal}
<div class="modal fade" id="fundModal" tabindex="-1">
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

{* Delete confirm modal — statický, naplňuje JS *}
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">Smazat fond</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Opravdu chcete smazat fond <strong id="deleteName"></strong>?</p>
            </div>
            <div class="modal-footer">
                <form id="deleteForm" method="post">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zrušit</button>
                    <button type="submit" class="btn btn-danger ajax">Smazat</button>
                </form>
            </div>
        </div>
    </div>
</div>

{/block}

{block scripts}
<script>
const naja = window.naja;
naja.initialize();

// Zavřít modal po úspěšné AJAX akci
naja.addEventListener('success', ({ detail }) => {
    if (detail.payload.closeModal) {
        document.querySelectorAll('.modal.show').forEach(el => {
            bootstrap.Modal.getInstance(el)?.hide();
        });
    }
});

function openFundModal(id, name = '') {
    document.getElementById('fundModalLabel').textContent = id ? 'Upravit fond' : 'Nový fond';
    // Naplnit hidden input #id a pole #name
    const form = document.querySelector('#fundModal form');
    form.querySelector('[name="id"]').value = id ?? '';
    form.querySelector('[name="name"]').value = name;
}

function openDeleteModal(btn) {
    const id = btn.dataset.id;
    const name = btn.dataset.name;
    document.getElementById('deleteName').textContent = name;
    document.getElementById('deleteForm').action = /* handleDelete URL */ btn.dataset.deleteUrl;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
{/block}
```

### Transaction specifika

Formulář navíc obsahuje:
- `<select>` pro fond — naplněn `$selectFunds` (pole `[id => name]`)
- `<select>` pro investora — naplněn `$selectInvestors` (pole `[id => name]`)

---

## Soubory ke změně / vytvoření

| Soubor | Akce |
|--------|------|
| `app/Domain/Fund/FundRepositoryInterface.php` | + `delete()` |
| `app/Domain/Investor/InvestorRepositoryInterface.php` | + `delete()` |
| `app/Domain/Transaction/TransactionRepositoryInterface.php` | + `delete()` |
| `app/Infrastructure/Database/FundRepository.php` | implementace `delete()` + `update()` v `save()` |
| `app/Infrastructure/Database/InvestorRepository.php` | dtto |
| `app/Infrastructure/Database/TransactionRepository.php` | dtto |
| `app/Application/Fund/FundService.php` | + `update()`, `delete()` |
| `app/Application/Investor/InvestorService.php` | + `update()`, `delete()` |
| `app/Application/Transaction/TransactionService.php` | + `update()`, `delete()` |
| `app/Presentation/Admin/Presenters/FundPresenter.php` | signály + form komponenta |
| `app/Presentation/Admin/Presenters/InvestorPresenter.php` | dtto |
| `app/Presentation/Admin/Presenters/TransactionPresenter.php` | dtto + select data |
| `app/Presentation/Admin/templates/@layout.latte` | Bootstrap 5 + naja CDN |
| `app/Presentation/Admin/templates/Fund/default.latte` | nová |
| `app/Presentation/Admin/templates/Investor/default.latte` | nová |
| `app/Presentation/Admin/templates/Transaction/default.latte` | nová |

---

## Verifikace

1. `docker compose up -d` → http://localhost:8080/admin/funds
2. Přidat fond → modal se otevře, submit → tabulka se aktualizuje bez překreslení stránky, modal se zavře
3. Upravit fond → modal se předplní stávajícími daty, po submitu se seznam aktualizuje
4. Smazat fond → delete modal se otevře s názvem, po potvrzení záznam zmizí z tabulky
5. Chybná validace → modal zůstane otevřený, chyby se zobrazí pod políčky
6. Stejný postup pro Investory a Transakce
7. Transaction form: oba `<select>` jsou naplněny daty z DB

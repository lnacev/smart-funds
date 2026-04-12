# Investor Password Change Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Přidat možnost změny hesla investora přes admin panel — samostatný Bootstrap modal s CSRF ochranou a AJAX chováním.

**Architecture:** Nová metoda `updatePassword()` v `UserRepository` + `changeInvestorPassword()` v `UserService`. `InvestorPresenter` dostane novou form komponentu `changePasswordForm`. Šablona dostane třetí akční tlačítko "Heslo" a `{snippet passwordModal}` s modalem. Sleduje existující pattern: `Form::addClass('ajax')` + `addProtection()` + snippet překreslení při chybě.

**Tech Stack:** PHP 8.5, Nette Application UI Forms, Nette Database Explorer, Latte 3, Bootstrap 5, naja 2.x

---

## Soubory

| Soubor | Akce |
|--------|------|
| `app/Domain/User/UserRepositoryInterface.php` | upravit — přidat `updatePassword()` do interface |
| `app/Infrastructure/Database/UserRepository.php` | upravit — implementovat `updatePassword()` |
| `app/Application/User/UserService.php` | upravit — přidat `changeInvestorPassword()` |
| `app/Presentation/Admin/Presenters/InvestorPresenter.php` | upravit — přidat `createComponentChangePasswordForm()` |
| `app/Presentation/Admin/templates/Investor/default.latte` | upravit — tlačítko + modal + JS |

---

### Task 1: UserRepository — metoda updatePassword

**Files:**
- Modify: `app/Domain/User/UserRepositoryInterface.php`
- Modify: `app/Infrastructure/Database/UserRepository.php`

- [ ] **Krok 1: Přidat do interface**

Upravit `app/Domain/User/UserRepositoryInterface.php` — přidat metodu za `deleteByInvestorId`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\User;

interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function findById(int $id): ?User;

    public function findByInvestorId(int $investorId): ?User;

    public function save(User $user): int;

    public function deleteByInvestorId(int $investorId): void;

    public function updatePassword(int $userId, string $hash): void;
}
```

- [ ] **Krok 2: Implementovat v UserRepository**

Přidat metodu `updatePassword` do `app/Infrastructure/Database/UserRepository.php` za metodu `deleteByInvestorId` (před `rowToUser`):

```php
    public function updatePassword(int $userId, string $hash): void
    {
        $this->database->table('users')
            ->where('id', $userId)
            ->update(['password_hash' => $hash]);
    }
```

- [ ] **Krok 3: PHP syntax check**

```bash
php -l app/Domain/User/UserRepositoryInterface.php && php -l app/Infrastructure/Database/UserRepository.php
```

Očekáváno: `No syntax errors detected` pro oba soubory.

- [ ] **Krok 4: Commit**

```bash
git add app/Domain/User/UserRepositoryInterface.php app/Infrastructure/Database/UserRepository.php
git commit -m "feat: UserRepository — metoda updatePassword"
```

---

### Task 2: UserService — metoda changeInvestorPassword

**Files:**
- Modify: `app/Application/User/UserService.php`

- [ ] **Krok 1: Přidat metodu do UserService**

Přidat metodu `changeInvestorPassword` do `app/Application/User/UserService.php` za metodu `deleteInvestor`:

```php
    public function changeInvestorPassword(int $investorId, string $newPassword): bool
    {
        $user = $this->userRepository->findByInvestorId($investorId);
        if ($user === null) {
            return false;
        }
        $this->userRepository->updatePassword($user->id, $this->passwords->hash($newPassword));
        return true;
    }
```

Poznámka: používá `$this->passwords->hash()` (Nette\Security\Passwords) — stejně jako ostatní metody v UserService.

- [ ] **Krok 2: PHP syntax check**

```bash
php -l app/Application/User/UserService.php
```

Očekáváno: `No syntax errors detected`

- [ ] **Krok 3: Commit**

```bash
git add app/Application/User/UserService.php
git commit -m "feat: UserService — metoda changeInvestorPassword"
```

---

### Task 3: InvestorPresenter — komponenta changePasswordForm

**Files:**
- Modify: `app/Presentation/Admin/Presenters/InvestorPresenter.php`

- [ ] **Krok 1: Přidat form komponentu a handler**

Přidat dvě metody do `app/Presentation/Admin/Presenters/InvestorPresenter.php` za metodu `createComponentDeleteForm` (před uzavírací `}`):

```php
    protected function createComponentChangePasswordForm(): Form
    {
        $form = new Form;
        $form->addHidden('id');
        $form->addPassword('password', 'Nové heslo:')
            ->setRequired('Zadejte heslo.')
            ->addRule(Form::MinLength, 'Minimálně 7 znaků.', 7)
            ->addRule(Form::Pattern, 'Musí obsahovat alespoň 1 velké písmeno.', '.*[A-Z].*');
        $form->addPassword('password_confirm', 'Potvrzení hesla:')
            ->setRequired('Potvrďte heslo.')
            ->addRule(Form::Equal, 'Hesla se neshodují.', $form['password']);
        $form->addProtection();
        $form->addSubmit('save', 'Uložit')
            ->setHtmlAttribute('class', 'btn btn-primary');
        $form->getElementPrototype()->addClass('ajax');

        $form->onSuccess[] = $this->changePasswordFormSucceeded(...);
        $form->onError[] = function (): void {
            if ($this->isAjax()) {
                $this->redrawControl('passwordModal');
            }
        };

        return $form;
    }

    private function changePasswordFormSucceeded(Form $form, \stdClass $values): void
    {
        $investorId = (int) $values->id;
        $changed = $this->userService->changeInvestorPassword($investorId, $values->password);

        if (!$changed) {
            $form->addError('Tento investor nemá přiřazený uživatelský účet.');
            if ($this->isAjax()) {
                $this->redrawControl('passwordModal');
            }
            return;
        }

        if ($this->isAjax()) {
            $this->payload->closeModal = true;
        } else {
            $this->redirect('default');
        }
    }
```

- [ ] **Krok 2: PHP syntax check**

```bash
php -l app/Presentation/Admin/Presenters/InvestorPresenter.php
```

Očekáváno: `No syntax errors detected`

- [ ] **Krok 3: Commit**

```bash
git add app/Presentation/Admin/Presenters/InvestorPresenter.php
git commit -m "feat: InvestorPresenter — komponenta changePasswordForm"
```

---

### Task 4: Šablona — tlačítko Heslo + passwordModal

**Files:**
- Modify: `app/Presentation/Admin/templates/Investor/default.latte`

- [ ] **Krok 1: Přidat tlačítko "Heslo" v tabulce**

V souboru `app/Presentation/Admin/templates/Investor/default.latte` přidat tlačítko "Heslo" mezi "Upravit" a "Smazat" (na řádku 38, před tlačítko Smazat):

```latte
                <button type="button" class="btn btn-sm btn-outline-secondary"
                        data-id="{$investor->id}"
                        data-name="{$investor->name}"
                        onclick="openPasswordModal(this)">
                    Heslo
                </button>
```

- [ ] **Krok 2: Přidat snippet passwordModal**

Přidat `{snippet passwordModal}` blok za `{/snippet}` uzavírající deleteModal (před `{/block}`):

```latte
{snippet passwordModal}
<div class="modal fade" id="passwordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="passwordModalLabel">Změna hesla</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                {control changePasswordForm}
            </div>
        </div>
    </div>
</div>
{/snippet}
```

- [ ] **Krok 3: Přidat JS funkci openPasswordModal**

Přidat funkci `openPasswordModal` do bloku `{block scripts}` za funkci `openDeleteModal`:

```js
function openPasswordModal(btn) {
    const modal = document.getElementById('passwordModal');
    modal.querySelector('[name="id"]').value = btn.dataset.id;
    document.getElementById('passwordModalLabel').textContent = 'Změna hesla: ' + btn.dataset.name;
    modal.querySelector('[name="password"]').value = '';
    modal.querySelector('[name="password_confirm"]').value = '';
    bootstrap.Modal.getOrCreateInstance(modal).show();
}
```

- [ ] **Krok 4: PHP syntax check šablony**

```bash
php -l app/Presentation/Admin/templates/Investor/default.latte
```

Očekáváno: `No syntax errors detected`

- [ ] **Krok 5: Commit**

```bash
git add app/Presentation/Admin/templates/Investor/default.latte
git commit -m "feat: admin investor — tlačítko Heslo a passwordModal"
```

---

### Task 5: Ověření v prohlížeči

- [ ] **Krok 1: Spustit Docker**

```bash
docker compose up -d
```

Ověřit: `docker compose ps` — všechny kontejnery `Up`.

- [ ] **Krok 2: Přihlásit se jako admin**

Otevřít `http://localhost:8080/sign/in`, přihlásit se jako admin. Přejít na `http://localhost:8080/admin/investor`.

- [ ] **Krok 3: Happy path — úspěšná změna hesla**

Kliknout "Heslo" u investora s přiřazeným účtem. Zadat heslo `Heslo123` a potvrzení `Heslo123`. Kliknout Uložit.

Očekáváno: modal se zavře, žádná Tracy chyba.

- [ ] **Krok 4: Neshodná hesla**

Kliknout "Heslo", zadat `Heslo123` a potvrzení `JineHeslo1`. Kliknout Uložit.

Očekáváno: modal zůstane otevřený, zobrazí se "Hesla se neshodují."

- [ ] **Krok 5: Příliš krátké heslo**

Zadat heslo `Ab1` (3 znaky). Kliknout Uložit.

Očekáváno: modal zůstane, zobrazí se "Minimálně 7 znaků."

- [ ] **Krok 6: Heslo bez velkého písmene**

Zadat heslo `heslo123`. Kliknout Uložit.

Očekáváno: modal zůstane, zobrazí se "Musí obsahovat alespoň 1 velké písmeno."

- [ ] **Krok 7: Přihlášení s novým heslem**

Odhlásit se (`/sign/out`). Přihlásit se jako investor, jehož heslo bylo změněno, s heslem `Heslo123`.

Očekáváno: přihlášení proběhne, redirect na `/investor`.

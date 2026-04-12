# Investor Password Change — Design

**Datum:** 2026-04-12
**Funkce:** Změna hesla investora přes admin panel

## Přehled

Admin může změnit heslo investora bez znalosti starého hesla. UI je samostatný Bootstrap modal otevíraný z tabulky investorů tlačítkem "Heslo". Implementace sleduje existující AJAX pattern (Nette Form + snippet).

---

## Architektura

### Upravované soubory

| Soubor | Změna |
|--------|-------|
| `app/Application/User/UserService.php` | Nová metoda `changeInvestorPassword(int $investorId, string $newPassword): void` |
| `app/Infrastructure/Database/UserRepository.php` | Nová metoda `updatePassword(int $userId, string $hash): void` |
| `app/Presentation/Admin/Presenters/InvestorPresenter.php` | Nová komponenta `createComponentChangePasswordForm()` |
| `app/Presentation/Admin/templates/Investor/default.latte` | Tlačítko "Heslo" v tabulce + modal `#passwordModal` + snippet `passwordModal` |

---

## Datový tok

### Otevření modalu

Tlačítko "Heslo" v tabulce nese `data-id` a `data-name`. JS funkce `openPasswordModal(btn)` nastaví skryté pole `id` a titulek modalu, poté zobrazí modal:

```js
function openPasswordModal(btn) {
    document.querySelector('#passwordModal [name="id"]').value = btn.dataset.id;
    document.querySelector('#passwordModal .modal-title').textContent = 'Změna hesla: ' + btn.dataset.name;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('passwordModal')).show();
}
```

### Nette Form komponenta

```php
public function createComponentChangePasswordForm(): Form
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
    $form->addSubmit('save', 'Uložit');
    $form->onSuccess[] = $this->changePasswordFormSucceeded(...);
    $form->onError[] = function () {
        $this->redrawControl('passwordModal');
    };
    return $form;
}
```

### Handler úspěchu

```php
private function changePasswordFormSucceeded(Form $form, \stdClass $values): void
{
    $investorId = (int) $values->id;
    $investor = $this->investorService->getById($investorId);
    $changed = $this->userService->changeInvestorPassword($investorId, $values->password);
    if (!$changed) {
        $this->flashMessage('Investor ' . $investor->name . ' nemá přiřazený účet.', 'warning');
    } else {
        $this->flashMessage('Heslo investora ' . $investor->name . ' bylo změněno.', 'success');
    }
    $this->payload->closeModal = true;
    $this->redrawControl('flashes');
}
```

### UserService::changeInvestorPassword()

Vrací `bool` — `true` pokud byl user nalezen a heslo změněno, `false` pokud investor nemá účet.

```php
public function changeInvestorPassword(int $investorId, string $newPassword): bool
{
    $user = $this->userRepository->findByInvestorId($investorId);
    if ($user === null) {
        return false;
    }
    $this->userRepository->updatePassword($user->id, password_hash($newPassword, PASSWORD_DEFAULT));
    return true;
}
```

### UserRepository::updatePassword()

```php
public function updatePassword(int $userId, string $hash): void
{
    $this->database->table('users')
        ->where('id', $userId)
        ->update(['password_hash' => $hash]);
}
```

---

## UI

### Tlačítko v tabulce

Třetí akční tlačítko vedle "Upravit" a "Smazat":

```latte
<button class="btn btn-sm btn-outline-secondary"
        onclick="openPasswordModal(this)"
        data-id="{$investor['id']}"
        data-name="{$investor['name']}">Heslo</button>
```

### Modal struktura

- Titulek: `Změna hesla: {jméno investora}` (nastavuje JS)
- Snippet `passwordModal` — obaluje celý `modal-content` pro AJAX překreslení při chybě
- Dvě password pole + skryté `id` + `addProtection()` CSRF token
- Submit tlačítko s třídou `ajax` na formuláři

---

## Error handling

| Situace | Chování |
|---------|---------|
| Validační chyba (krátké heslo, neshodují se) | Modal zůstane otevřený, chyby inline pod poli (snippet překreslení) |
| Investor bez user účtu | Modal se zavře, flash warning "Investor X nemá přiřazený účet." |
| Úspěch | Modal se zavře, flash success "Heslo investora X bylo změněno." |

Modal se zavírá přes `payload.closeModal = true` + naja `success` event handler (stejný pattern jako u ostatních formulářů).

---

## Ověření

1. **Happy path** — kliknout "Heslo" → zadat platné heslo 2x → modal se zavře, flash success
2. **Neshodná hesla** — modal zůstane, chybová hláška u pole potvrzení
3. **Příliš krátké heslo** — chybová hláška "Minimálně 7 znaků"
4. **Bez velkého písmene** — chybová hláška "Musí obsahovat alespoň 1 velké písmeno"
5. **Investor bez účtu** — flash warning, modal se zavře
6. **Funkční přihlášení** — přihlásit se jako investor s novým heslem → úspěch

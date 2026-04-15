# Smart Funds

Portfoliová webová aplikace pro správu investičních fondů. Umožňuje spravovat fondy, investory, transakce a sledovat portfolio cenných papírů (akcie, ETF, krypto).

**Technologie:** PHP 8.5, Nette Framework 3.x, Latte 3.x, MariaDB 11

---

## Instalace a spuštění

### Docker (doporučeno)

```bash
docker compose up -d
docker compose exec app composer install
```

Při prvním spuštění s prázdným DB volume se schéma importuje automaticky. Poté vytvořte prvního admina:

```bash
docker compose exec app php bin/create-admin.php email heslo
```

- Aplikace: http://localhost:8080
- Adminer: http://localhost:8081

Credentials pro DB se konfigurují v `.env.docker` (zkopírovat z `.env.docker.example`).

### Bez Dockeru

```bash
composer install
```

Zkopírujte `config/local.neon.example` → `config/local.neon` a doplňte credentials k databázi.

```bash
php -S localhost:8000 -t www
```

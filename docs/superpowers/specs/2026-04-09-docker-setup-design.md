# Docker Setup Design — Smart Funds

**Datum:** 2026-04-09  
**Stav:** Schváleno

## Kontext

Projekt Smart Funds je vyvíjen lokálně a bude hostován na Wedos webhostingu (Apache + mod_php, PHP 8.5, MariaDB). Docker Compose nahrazuje ruční spouštění `php -S` a zajišťuje prostředí shodné s produkcí.

## Architektura

**3 services v Docker Compose:**

| Service | Image | Port |
|---------|-------|------|
| `app` | php:8.5-apache (vlastní Dockerfile) | `8080` |
| `db` | mariadb:11 | `3306` |
| `adminer` | adminer | `8081` |

## Soubory k vytvoření

```
smart-funds/
├── Dockerfile                          ← php:8.5-apache + extensions + Composer
├── docker-compose.yml                  ← orchestrace 3 services
├── .env.docker                         ← DB credentials (gitignored)
├── docker/
│   ├── apache/
│   │   └── vhost.conf                  ← DocumentRoot → /var/www/html/www
│   └── php/
│       └── php.ini                     ← dev overrides
└── docs/superpowers/specs/
    └── 2026-04-09-docker-setup-design.md
```

## Dockerfile

```dockerfile
FROM php:8.5-apache

RUN docker-php-ext-install pdo pdo_mysql mysqli intl
RUN a2enmod rewrite

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
```

## docker-compose.yml

```yaml
services:
  app:
    build: .
    ports:
      - "8080:80"
    volumes:
      - .:/var/www/html
    depends_on:
      - db
    env_file: .env.docker

  db:
    image: mariadb:11
    ports:
      - "3306:3306"
    env_file: .env.docker
    volumes:
      - db_data:/var/lib/mysql

  adminer:
    image: adminer
    ports:
      - "8081:8080"
    depends_on:
      - db

volumes:
  db_data:
```

## .env.docker (gitignored)

```env
MARIADB_ROOT_PASSWORD=secret
MARIADB_DATABASE=smart_funds
MARIADB_USER=smart_funds
MARIADB_PASSWORD=secret
```

## Apache vhost

```apache
<VirtualHost *:80>
    DocumentRoot /var/www/html/www
    <Directory /var/www/html/www>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

## PHP ini (dev)

```ini
display_errors = On
error_reporting = E_ALL
memory_limit = 256M
upload_max_filesize = 32M
```

## Nette DB konfigurace pro Docker

`config/local.neon` — host je název service `db`:
```neon
database:
    dsn: 'mysql:host=db;dbname=smart_funds;charset=utf8mb4'
    user: smart_funds
    password: secret
    options:
        lazy: true
        convertBoolean: true
        newDateTime: true
```

## Spuštění

```bash
docker compose up -d        # spustit
docker compose down         # zastavit
docker compose logs -f app  # logy PHP/Apache
```

- Aplikace: http://localhost:8080
- Adminer: http://localhost:8081

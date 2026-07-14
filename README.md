# MoodJournal (API + Docker)

> **Status: abandoned example / portfolio snippet**  
> Early prototype of a mood-tracking app. Development stopped; the idea was dropped.  
> Kept as a learning/reference project — **not production-ready** and **not maintained**.

Companion mobile app: [moodjournalmobile](https://github.com/kopolot/moodjournalmobile)

This repository holds the **Symfony JSON API** and the **local Docker stack**. What exists is mainly **auth scaffolding** (register / verify / JWT / profile). There is no mood/journal domain on the API.

## Stack

| Piece | Version / notes |
|-------|-----------------|
| PHP | 8.5 (Docker `php:8.5-fpm`) |
| Symfony | 8.1 |
| DB | PostgreSQL 15 |
| Auth | Lexik JWT |
| Async | Messenger + RabbitMQ, Scheduler |
| Mail (dev) | Mailhog (proxied via Apache) |
| CORS | Nelmio (localhost + private LAN) |

## Layout

| Path | Role |
|------|------|
| `public/` | Symfony project root (HTTP docroot is `public/public/`) |
| `docker/` | PHP, Apache, RabbitMQ config |
| `docker-compose.yaml` | Standalone local stack |

A local `mobile/` checkout may exist beside this repo; it is **gitignored** here and lives in its own GitHub repository.

## Quick start

```bash
UID=$(id -u) docker compose up -d --build

# inside PHP container (or with composer on host pointed at public/)
docker exec -it mood_dic-php-1 bash
# cd /var/www/html
composer install
php bin/console doctrine:migrations:migrate --no-interaction
# ensure JWT keys exist under config/jwt/
```

Copy env examples if needed:

```bash
cp public/.env.example public/.env
# local Docker overrides (DB, mailhog, rabbit) — see public/.env.*.local.example
```

| Service | URL |
|---------|-----|
| API / Apache | http://localhost:8080 |
| Mailhog UI | http://localhost:8080/mailhog/ |
| Adminer | http://localhost:8080/adminer/ |
| RabbitMQ UI | http://localhost:8080/rabbitmq/ |

Only **Apache `:8080`** is published on the host. Mailhog, Adminer and RabbitMQ stay on the Docker network and are reverse-proxied by Apache.

Default Postgres (compose): user `symfony`, password `password`, DB `symfony`, host `db`.

## API surface (what exists)

Base URL (dev): `http://localhost:8080`

| Method | Path | Notes |
|--------|------|--------|
| `POST` | `/user/register` | `firstname`, `email`, `password`, `repeatPassword`, `acceptPrivacyPolicy` |
| `POST` | `/user/login` | JSON body uses **`email`** + `password`; response JWT in `data.jwt_token` |
| `GET` | `/user/verify/{token}` | Email verification |
| `GET` | `/user/get` | Bearer JWT; profile (`user:read` serializer groups) |
| `PATCH` | `/user/edit` | Profile / preferences |
| `POST` | `/user/disableuser` | Soft-disable; scheduler may hard-delete later |
| `POST` | `/user/forgotpassword` | Incomplete |
| `POST` | `/user/resetpassword` | Incomplete |
| `GET` | `/translations/{locale}` | Locale catalog (known bugs possible) |

CORS allows browser origins on localhost and private LAN IPs so Expo web / LAN devices can call the API.

## Tests

```bash
docker exec mood_dic-php-1 php bin/phpunit
```

## Known gaps

- No mood/journal endpoints
- Password reset unfinished
- Translations endpoint may fail on bad YAML flatten
- `CustomJsonLoginAuthenticator` extends a `final` Symfony class (deprecation)

## Agent / Cursor notes

See [`AGENTS.md`](./AGENTS.md) and [`.cursor/rules/`](./.cursor/rules/).

## Why this exists

Personal product idea that never shipped. Shared as an **example** of early fullstack wiring (Symfony JWT API + Expo client), not something to deploy.

No support, no roadmap, no guarantees.

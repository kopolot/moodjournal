# MoodJournal / MoodDic (API + Docker)

> **Status: active prototype / portfolio project**  
> Gamified mood journal API (check-ins, XP, streaks) with Symfony JWT auth.  
> Still an early product snapshot — **not production-hardened**.

Companion mobile app: [moodjournalmobile](https://github.com/kopolot/moodjournalmobile)

This repository holds the **Symfony JSON API** and the **local Docker stack**. Product direction: log subjective mood ratings + light notes across life aspects; later unlock AI analysis via a paid plan (`subscriptionTier`).

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

## API surface

Base URL (dev): `http://localhost:8080`

### Auth / user

| Method | Path | Notes |
|--------|------|--------|
| `POST` | `/user/register` | `firstname`, `email`, `password`, `repeatPassword`, `acceptPrivacyPolicy` |
| `POST` | `/user/login` | JSON body uses **`email`** + `password`; response JWT in `data.jwt_token` |
| `GET` | `/user/verify/{token}` | Email verification (required before login) |
| `GET` | `/user/get` | Bearer JWT; profile + gamification fields |
| `PATCH` | `/user/edit` | Partial profile / preferences (`firstname`, `preferences`) |
| `POST` | `/user/disableuser` | Soft-disable; scheduler may hard-delete later |
| `POST` | `/user/forgotpassword` | Incomplete |
| `POST` | `/user/resetpassword` | Incomplete |
| `GET` | `/translations/{locale}` | Locale catalog (known bugs possible) |

### Mood domain

Aspect keys: `mood`, `relationship`, `activity`, `environment`. Each aspect needs `score` (1–6) and optional `note`.

| Method | Path | Notes |
|--------|------|--------|
| `POST` | `/mood` | Create check-in; returns `entry` + updated `stats` (XP / streak) |
| `GET` | `/mood` | List entries (`limit`, `offset`) → `{ items, total }` |
| `GET` | `/mood/stats` | Level, XP, streaks, `loggedToday`, 7d average, subscription flags |
| `GET` | `/mood/{id}` | Single entry (UUID) |
| `PATCH` | `/mood/{id}` | Update entry fields |
| `DELETE` | `/mood/{id}` | Delete entry |

Gamification (on `User` + first-of-day bonus):

- Base XP per entry + bonus for aspect scores/notes
- Daily streak when the first entry of the calendar day is logged
- `subscriptionTier`: `free` (default) / reserved for future `plus` AI unlocks

CORS allows browser origins on localhost and private LAN IPs so Expo web / LAN devices can call the API.

## Tests

```bash
docker exec mood_dic-php-1 php bin/phpunit
```

## Known gaps

- AI analysis / paid billing not implemented (`aiAnalysisUnlocked` is tier-gated only)
- Password reset unfinished
- Translations endpoint may fail on bad YAML flatten
- `CustomJsonLoginAuthenticator` extends a `final` Symfony class (deprecation)

## Agent / Cursor notes

See [`AGENTS.md`](./AGENTS.md) and [`.cursor/rules/`](./.cursor/rules/).

## Why this exists

Personal product prototype revived as a fullstack example (Symfony JWT API + Expo client) with a real mood domain and gamification hooks — still not something to deploy as-is.

No support guarantees.

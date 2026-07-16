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

### Subscription

| Method | Path | Notes |
|--------|------|--------|
| `GET` | `/subscription/plans` | Public catalog + `billingProvider` (`stripe` \| `mock`) |
| `GET` | `/subscription/current` | Auth; current tier + flags |
| `POST` | `/subscription/checkout` | Auth; Stripe → `{ checkoutUrl }` or mock card charge |
| `POST` | `/subscription/cancel` | Auth; mock downgrades now, Stripe cancels at period end |
| `POST` | `/subscription/webhook` | Public Stripe webhook (`Stripe-Signature`) |

Configure `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_PRICE_PLUS`, `STRIPE_PRICE_PRO` to enable Stripe. Empty keys keep the demo mock checkout.

### Mood domain

- **Overall mood**: `overallMood` (1–6) + optional overall `note`
- **Life aspects** (must match `MoodEntry::ASPECT_KEYS`):  
  `close_relationships`, `romantic_relationships`, `duties`, `physical_health`, `finances`, `relaxation`, `growth_spirituality`, `environment`  
  Each: `{ score: 1–6, note?: string|null }` (note min length **5** when required)

| Method | Path | Notes |
|--------|------|--------|
| `POST` | `/mood` | Create check-in; `Idempotency-Key` header; returns `entry` + `stats` |
| `GET` | `/mood` | List entries (`limit`, `offset`) → `{ items, total }` |
| `GET` | `/mood/stats` | Level, XP, streaks, `loggedToday`, 7d average, subscription flags |
| `GET` | `/mood/checkin-hints` | Averages / `noticeableDrop` / note thresholds for the wizard |
| `GET` | `/mood/analysis` | Plus/Pro mood analysis + coaching (`?refresh=1` bypasses cache) |
| `GET` | `/mood/reports/advanced` | Pro reports (`days=30\|90`): weekly series, aspects, distribution |
| `GET` | `/mood/{id}` | Single entry (UUID) |
| `PATCH` | `/mood/{id}` | Update entry fields |
| `DELETE` | `/mood/{id}` | Delete entry |

Gamification (on `User` + first-of-day bonus):

- Base XP per entry + bonus for aspect scores/notes
- Daily streak when the first entry of the calendar day is logged
- `subscriptionTier`: `free` (default) / reserved for future `plus` AI unlocks

CORS allows browser origins on localhost and private LAN IPs so Expo web / LAN devices can call the API.

## AI mood analysis

`GET /mood/analysis` (Plus/Pro) always runs the built-in **pattern engine** (trends, aspect focus, coaching tip keys).  
**No LLM key is required** — the API and tests work out of the box.

### Optional LLM narrative (OpenAI-compatible)

If you want richer wording, set:

```bash
OPENAI_API_KEY=…                 # required for cloud providers; for Ollama can be `ollama`
OPENAI_BASE_URL=                 # empty → https://api.openai.com/v1
OPENAI_MODEL=gpt-4o-mini
```

Compatible backends: **OpenAI**, **Groq**, **OpenRouter**, **Ollama** (`/v1` chat completions).

When the LLM call succeeds, the response includes:

- `engine: "pattern+llm"`
- `narrative: { headline, detail, tips[] }`

On timeout/error the pattern payload is returned unchanged (`engine: "pattern"`).

### Local model (Ollama) — GPU required for comfort

Local inference is **optional** and meant for developers with a strong GPU.  
Laptops without a discrete GPU can still use MoodDic — just leave `OPENAI_*` empty.

Example (AMD Radeon with ~12 GB VRAM, e.g. RX 6700 XT):

```bash
# install Ollama, then pull a 7B–8B instruct model
ollama pull llama3.1:8b
# or: ollama pull qwen2.5:7b

# in public/.env (API container must reach the host)
OPENAI_BASE_URL=http://172.255.0.1:11434/v1
OPENAI_API_KEY=ollama
OPENAI_MODEL=llama3.1:8b
```

| Requirement | Guidance |
|-------------|----------|
| GPU VRAM | **≥ 8 GB** recommended for 7B Q4/Q5; **12 GB** comfortable (RX 6700 XT class) |
| CPU-only | possible but slow — not recommended for interactive demo |
| Model size | Prefer **7B–8B instruct**; 13B OK on 12 GB; 30B+ usually too heavy |
| Backend | Ollama or llama.cpp with OpenAI-compatible `/v1` |
| Privacy | Mood aggregates are sent to the configured endpoint — keep it local if that matters |

> **Portfolio default:** keep LLM disabled. Pattern analysis is enough to demo Plus/Pro unlocks without hardware or paid keys.

## Tests

```bash
docker exec mood_dic-php-1 php bin/phpunit
```

PHPUnit uses a self-contained SQLite DB (`tests/bootstrap.php` → `var/test.db`) so the suite does not require the Docker Postgres instance.

## Known gaps

- Stripe billing: set `STRIPE_*` env vars to enable Checkout + webhooks; empty keys keep mock checkout
- Password reset routes exist but full product UX is unfinished
- Translations endpoint may fail on bad YAML flatten

## Agent / Cursor notes

See [`AGENTS.md`](./AGENTS.md) and [`.cursor/rules/`](./.cursor/rules/).

## Why this exists

Personal product prototype revived as a fullstack example (Symfony JWT API + Expo client) with a real mood domain and gamification hooks — still not something to deploy as-is.

No support guarantees.

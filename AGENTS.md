# Agent guide — MoodJournal / MoodDic API

Prototype mood-journal API. Prefer focused, shippable changes that match the existing auth + mood domain. Do not add AI billing unless explicitly asked.

## Repositories

| Repo | URL | Contents |
|------|-----|----------|
| API + Docker | https://github.com/kopolot/moodjournal | this tree |
| Mobile | https://github.com/kopolot/moodjournalmobile | Expo app |

Local monorepo may contain `mobile/` as a nested clone; it is **gitignored** here. Do not commit mobile sources in this repo.

## Runtime map

- App code: `public/`
- HTTP docroot: `public/public/`
- PHP container mounts `./public` → `/var/www/html`
- Container name pattern: `mood_dic-php-1` (not `*_php_fpm`)
- Host entrypoint: `http://localhost:8080` → Apache → php-fpm

Start stack:

```bash
UID=$(id -u) docker compose up -d --build
```

Console / tests / migrate:

```bash
docker exec mood_dic-php-1 php bin/console …
docker exec mood_dic-php-1 php bin/console doctrine:migrations:migrate --no-interaction
docker exec mood_dic-php-1 php bin/phpunit
```

Composer may be missing from PATH inside the image; use a host Composer against `public/` or `php composer.phar` if present.

## Stack constraints

- PHP **≥ 8.5**, Symfony **8.1.\***
- Doctrine DBAL 4 / ORM 3 — avoid obsolete Doctrine bundle flags (`use_savepoints`, `enable_lazy_ghost_objects`, etc.)
- Serializer: `Symfony\Component\Serializer\Attribute\Groups` (not Annotations)
- Login JSON field is **`email`** (not `username`)
- JWT returned to clients: `data.jwt_token`
- No `Kopolot\\Utility` bundle — shared repo base is `App\Repository\AbstractRepository`

## Domain map

| Piece | Path |
|-------|------|
| Mood HTTP API | `public/src/Controller/MoodController.php` |
| Mood logic / XP / streak / hints | `public/src/Service/MoodService.php` |
| Mood entity | `public/src/Entity/MoodEntry.php` |
| User + gamification fields | `public/src/Entity/User.php` |
| Auth HTTP API | `public/src/Controller/UserController.php` |
| Repositories | `public/src/Repository/` |
| JSON login | `public/src/Security/Authenticator/CustomJsonLoginAuthenticator.php` |
| Mood migration | `public/migrations/Version20260714160000.php` |

### Mood check-in shape

- **Overall mood**: `overallMood` (1–6) + optional overall `note`
- **Life aspects** (fixed keys in `MoodEntry::ASPECT_KEYS`):  
  `close_relationships`, `romantic_relationships`, `duties`, `physical_health`, `finances`, `relaxation`, `growth_spirituality`, `environment`  
  Each: `{ score: 1–6, note?: string|null }`
- Note min length when required: **5** (`ASPECT_NOTE_MIN_LENGTH`)
- Create accepts `Idempotency-Key` header (safe retries)
- Hints: `GET /mood/checkin-hints` (averages, `noticeableDrop`, thresholds)

Security: `/mood*` requires `IS_AUTHENTICATED_FULLY` (`public/config/packages/security.yaml`).

## Tests

- Bootstrap: `public/tests/bootstrap.php` forces SQLite `var/test.db` + null mailer (self-contained; no Docker Postgres required for PHPUnit)
- DB reset helper: `public/tests/Support/TestDatabase.php`
- Suites live under `public/tests/{Unit,Integration,Controller}/`
- Login throttling is **disabled in `APP_ENV=test`** (avoids flaky 401s)
- Prefer real HTTP/controller coverage for mood + auth over placeholders

## Docker / networking invariants

| Path | Role |
|------|------|
| `docker-compose.yaml` | Local stack (only Apache published) |
| `docker/apache/httpd.conf` | Minimal Apache → php-fpm + Mailhog/Adminer/RabbitMQ proxies |
| `docker/php/Dockerfile` | PHP 8.5-FPM image |
| `docker/php/php.ini` | Copied to `conf.d/99-local.ini` (memory, xdebug) |
| `docker/rabbitmq/rabbitmq.conf` | Management UI path prefix `/rabbitmq` |

- Do **not** re-publish Mailhog / Adminer / RabbitMQ on host ports — only Apache **`8080`**
- Do **not** re-wire this project to a shared external nginx network unless explicitly asked
- Dev CORS regex: `CORS_ALLOW_ORIGIN` (`.env` + Nelmio)
- Apache/PHP timeouts are aligned at **300s**
- Compose network gateway `172.255.0.1` is used by Xdebug `client_host`

## What not to build unless asked

- Large unrelated refactors
- Forcing an LLM dependency (default mood analysis stays pattern-based; optional OpenAI-compatible layer is in `OpenAiCompatibleClient`)
- Completing password-reset UX end-to-end (routes exist; treat as incomplete unless requested)
- Reintroducing a utility/vendor bundle for `AbstractRepository`
- MCP servers for this repo (none required; use user-level Cursor MCP if needed)

## Useful paths

| Path | Why |
|------|-----|
| `public/src/Controller/UserController.php` | Auth HTTP API |
| `public/src/Controller/MoodController.php` | Mood HTTP API |
| `public/src/Entity/User.php` | User + serializer groups + XP/streak |
| `public/src/Repository/AbstractRepository.php` | Shared Doctrine helpers |
| `public/src/Security/Authenticator/` | JSON login + JWT active check |
| `public/config/packages/nelmio_cors.yaml` | CORS |
| `public/public/.htaccess` | Front controller only (no OPTIONS hacks) |
| `docker-compose.yaml` | Local services |
| `docker/php/Dockerfile` | PHP 8.5 image |

## Commits

Conventional, scoped messages (`feat(mood):`, `fix(api):`, `chore(docker):`, `test(mood):`, …). Stage named paths only; never commit secrets from `*.local` env files or JWT private keys.

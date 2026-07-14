# Agent guide — MoodJournal / MoodDic API

Prototype mood-journal API. Prefer focused, shippable changes that match the existing auth + mood domain. Do not add AI billing unless explicitly asked.

## Repositories

| Repo | URL | Contents |
|------|-----|----------|
| API + Docker | https://github.com/kopolot/moodjournal | this tree |
| Mobile | https://github.com/kopolot/moodjournalmobile | Expo app |

Local monorepo may contain `mobile/` as a nested clone; it is gitignored in this repo. Do not commit mobile sources here.

## Runtime map

- App code: `public/`
- Docroot: `public/public/`
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

Composer binary may be missing from PATH inside the image; use a host Composer against `public/` or `php composer.phar` if present.

## Stack constraints

- PHP **≥ 8.5**, Symfony **8.1.\***
- Doctrine DBAL 4 / ORM 3 — avoid obsolete Doctrine bundle flags (`use_savepoints`, `enable_lazy_ghost_objects`, etc.)
- Serializer: use `Symfony\Component\Serializer\Attribute\Groups`, not Annotation
- Login JSON field is **`email`** (not `username`)
- JWT payload key returned to clients: `data.jwt_token`

## Domain map

| Piece | Path |
|-------|------|
| Mood HTTP API | `public/src/Controller/MoodController.php` |
| Mood logic / XP / streak | `public/src/Service/MoodService.php` |
| Mood entity | `public/src/Entity/MoodEntry.php` |
| User + gamification fields | `public/src/Entity/User.php` |
| Auth HTTP API | `public/src/Controller/UserController.php` |
| Migration (mood + XP) | `public/migrations/Version20260714160000.php` |

Mood aspects (fixed keys): `mood`, `relationship`, `activity`, `environment` — each `{ score: 1–6, note?: string|null }`.

Security: `/mood*` requires `IS_AUTHENTICATED_FULLY` (`config/packages/security.yaml`).

## Docker / networking invariants

- Do **not** re-publish Mailhog / Adminer / RabbitMQ on host ports — only Apache `8080`
- Do **not** re-wire this project to a shared external nginx network unless explicitly asked
- Dev CORS regex lives in `CORS_ALLOW_ORIGIN` (`.env` + Nelmio)

## What not to build unless asked

- Stripe / real subscription billing, AI analysis pipelines, large unrelated refactors
- Completing password-reset end-to-end (still incomplete on purpose unless requested)
- MCP servers for this repo (none required; use user-level Cursor MCP if needed)

## Useful paths

| Path | Why |
|------|-----|
| `public/src/Controller/UserController.php` | Auth HTTP API |
| `public/src/Controller/MoodController.php` | Mood HTTP API |
| `public/src/Entity/User.php` | User + serializer groups + XP/streak |
| `public/src/Security/Authenticator/` | JSON login |
| `public/config/packages/nelmio_cors.yaml` | CORS |
| `public/public/.htaccess` | Front controller only (no OPTIONS hacks) |
| `docker-compose.yaml` | Local services |
| `docker/php/Dockerfile` | PHP 8.5 image |

## Commits

Follow conventional, scoped messages (`feat(mood):`, `fix(api):`, `chore(docker):`, …). Stage named paths only; never commit secrets from `*.local` env files or JWT private keys.

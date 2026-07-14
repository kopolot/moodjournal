# MoodJournal

> **Status: abandoned example / portfolio snippet**  
> Early prototype of a mood-tracking app. Development stopped; the idea was dropped.  
> Kept as a learning/reference project — not production-ready and not maintained.

Cross-platform journal app idea: log how you feel, browse related content, keep a simple profile. What landed in the repo is mainly **auth scaffolding** plus an incomplete mood UI on mobile.

## Structure

| Path | Role |
|------|------|
| `public/` | Backend — Symfony 7 JSON API (JWT auth, user account) |
| `mobile/` | Frontend — Expo / React Native (Expo Router) |
| `docker/` + `docker-compose.yaml` | Local stack: PHP-FPM, Apache, PostgreSQL, Mailhog, Adminer, RabbitMQ |

The folder name `public/` is the Symfony project root (docroot is `public/public/`).

## What works

**Backend (`public/`)**
- User register / login (Lexik JWT)
- Email verification flow
- Profile get / edit (preferences intended)
- Disable account + scheduled hard-delete of inactive users
- Locale catalogs (`/translations/{locale}`)

**Mobile (`mobile/`)**
- Register / login / logout, session restore
- Offline gate on auth screens
- Tab shell: home, explore, profile, mood-note form (UI only)
- i18n hooks (EN/PL, incomplete strings)

## What’s missing / incomplete

- No mood/journal domain on the API — mobile mood form does not persist anywhere
- Explore and much of profile are placeholders
- Password reset and some account helpers are unfinished on the backend
- Mobile still references a few auth endpoints that don’t match the API (`/auth/*` vs `/user/*`)
- Stage/prod API hosts in config are placeholders

Treat this as a **partial auth + UI sketch**, not a finished product.

## Stack (summary)

- **API:** PHP 8.5+, Symfony 8.1, Doctrine, PostgreSQL, Lexik JWT, Messenger/Scheduler, Mailer
- **App:** Expo ~57, React Native, Expo Router, Axios, AsyncStorage, i18next
- **Local:** standalone Docker Compose (no shared nginx reverse proxy); PHP image is 8.5-fpm

## Local run (rough outline)

Backend (from repo root):

```bash
UID=$(id -u) docker compose up -d --build
# then inside the PHP project: composer install, migrate, JWT keys, etc.
```

| Service | URL |
|---------|-----|
| API / Apache | http://localhost:8080 |
| Mailhog | http://localhost:8080/mailhog/ |
| Adminer | http://localhost:8080/adminer/ |
| RabbitMQ UI | http://localhost:8080/rabbitmq/ |

Only Apache is published on the host (`8080`). Mailhog, Adminer and RabbitMQ stay on the internal Docker network and are proxied by Apache.

Mobile:

```bash
cd mobile
npm install
npx expo start
```

Dev API base URL is resolved automatically in `mobile/config/appConfig.ts`:
- Metro/Expo host (LAN IP) on a physical device
- `10.0.2.2:8080` on Android emulator
- `localhost:8080` on iOS simulator / web

| Issue | Fix |
|------|-----|
| Web login: blocked OPTIONS / no error | CORS via Nelmio; `Alert.alert` replaced with `showAlert` (works on web) |
| Phone QR not opening | Use same Wi‑Fi, Expo Go app, `npm start` (`expo start --lan`). Avoid tunnel unless logged into Expo (`npm run start:tunnel`). |

## Why this exists

Old personal product idea that never shipped. Shared as an **example** of early fullstack wiring (Symfony JWT API + Expo client), not as something to deploy.

No support, no roadmap, no guarantees.

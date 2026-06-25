# Foliotrak

Self-hosted, LAN-first plant care tracker. Log care and observations per plant, visualize the history, send Pushover reminders, and (once enough data exists) recommend schedules. One install per household; all users share all data.

## Stack

- **Backend**: Laravel 13 API with Sanctum authentication
- **Frontend**: React 19 SPA with TypeScript (Vite)
- **Database**: MySQL 8
- **Server**: nginx (Docker Compose)
- **Notifications**: Pushover
- **Charts**: Recharts + Nivo
- **Stats**: markrogoyski/math-php

## Quick Start

### Prerequisites

- Docker and Docker Compose

### Installation

1. Clone the repository and navigate to the project directory:

```bash
cd foliotrak
```

2. Bring up the stack over HTTPS, passing the hostname you will reach it at:

```bash
./init.sh foliotrak.lan
```

Run it with no argument to default to `localhost`. The script generates a
self-signed certificate for that host, writes `.env`, installs PHP dependencies,
builds the frontend assets, and starts the stack; the `migrate` service runs
migrations and seeds a development account on first boot. It is safe to re-run.

3. Trust the self-signed certificate (`docker/nginx/certs/dev.crt`) or accept the
browser warning, then open `https://foliotrak.lan` and log in with the seeded
account: `admin@foliotrak.test` / `testing123` (override via
`FOLIOTRAK_ADMIN_EMAIL` and `FOLIOTRAK_ADMIN_PASSWORD` in `.env`).

## Development

For local development without Docker:

1. Install PHP 8.4+ and Node.js 22+
2. Run `composer install`
3. Run `npm install`
4. Run `php artisan migrate`
5. Run `composer run dev` to start the dev server, queue listener, and Vite dev server

## Commands

### Backend checks

```bash
vendor/bin/pint --test          # Lint check
vendor/bin/phpstan analyse      # Static analysis
./bin/test                      # Tests (runs in the app container against an isolated sqlite DB)
```

### Frontend checks

```bash
npm run lint                    # Lint check
npm run typecheck               # Type checking
npm run build                   # Production build
```

## Committing

Pre-commit checks (Pint, PHPStan) run inside the app container, so the host
needs no PHP toolchain. Commit with:

```bash
./bin/commit -m "your message"
```

On a fresh clone, generate `vendor/` first:
`docker compose run --rm --no-deps app composer install`.

Push from the host as usual (`git push`); the SSH key never enters the
container. A bare `git commit` on the host bypasses the checks, so prefer
`./bin/commit`.

## Architecture

- **No tenancy**: One household per install; authentication is a gate only.
- **Database spine**: Typed detail tables referenced from a unified care-event table.
- **Weight storage**: Canonical grams; API layer splits to lb/oz/g for UI.
- **UI language**: Insights never assert causation; use "coincided with", "may indicate", etc.

## Production hardening

`.env.example` ships local-development defaults. Before exposing the app beyond a
trusted LAN, set the following in `.env`:

- `APP_ENV=production` and `APP_DEBUG=false`.
- Strong, unique `DB_PASSWORD`, `DB_ROOT_PASSWORD`, and `FOLIOTRAK_ADMIN_PASSWORD`.
  The default development admin is never seeded when `APP_ENV=production`.
- `SESSION_SECURE_COOKIE=true` when serving over HTTPS (generate certs with
  `docker/nginx/generate-certs.sh`).
- `SANCTUM_STATEFUL_DOMAINS` and `CORS_ALLOWED_ORIGINS` set to the host and origin
  the browser actually uses, not `localhost`.

## Documentation

For detailed requirements and design, see `docs/project/foliotrak-spec.md` and `docs/project/` for multi-phase build tracking.

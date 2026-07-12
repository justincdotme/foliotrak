# Foliotrak

Self-hosted, LAN-first plant care tracker. Log care and observations per plant, visualize the history, send Pushover reminders, and (once enough data exists) recommend schedules. One install per household; all users share all data.

## Stack

- **Backend**: Laravel 13 API with Sanctum authentication
- **Frontend**: React 19 SPA with TypeScript (Vite)
- **Database**: MySQL 8
- **Search**: Meilisearch (species lookup, with live GBIF fallback)
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
builds the frontend assets, starts the stack, then runs database migrations and
seeds a development account. It also runs the containers as your own user, so
everything the stack writes stays editable on the host. It is safe to re-run.

If you bring the stack up without `init.sh`, set `FOLIOTRAK_UID` and
`FOLIOTRAK_GID` in `.env` to the output of `id -u` and `id -g` first.

3. Trust the self-signed certificate (`docker/nginx/certs/dev.crt`) or accept the
browser warning, then open `https://foliotrak.lan` and log in with the seeded
account: `admin@foliotrak.test` / `testing123` (override via
`FOLIOTRAK_ADMIN_EMAIL` and `FOLIOTRAK_ADMIN_PASSWORD` in `.env`).

## Sensor Integration

Optional ambient environment tracking via BLE sensors and a LAN gateway. Pulls temperature, humidity, light level (lux), and soil moisture readings on a schedule and charts them per plant. See [docs/SENSORS.md](docs/SENSORS.md) for setup and configuration.

## Production hardening

`.env.example` ships local-development defaults. Before exposing the app beyond a
trusted LAN, set the following in `.env`:

- `APP_ENV=production` and `APP_DEBUG=false`.
- Strong, unique `DB_PASSWORD`, `DB_ROOT_PASSWORD`, and `FOLIOTRAK_ADMIN_PASSWORD`.
  In production the admin account is only seeded once `FOLIOTRAK_ADMIN_PASSWORD`
  is changed from the development default.
- `SESSION_SECURE_COOKIE=true` when serving over HTTPS (generate certs with
  `docker/nginx/generate-certs.sh`).
- `SANCTUM_STATEFUL_DOMAINS` and `CORS_ALLOWED_ORIGINS` set to the host and origin
  the browser actually uses, not `localhost`.

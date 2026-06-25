#!/usr/bin/env bash
# One-command bring-up: self-signed TLS, environment, dependencies, frontend
# assets, and the full stack with the database migrated and seeded. Safe to
# re-run. Pass the domain to serve over HTTPS as the first argument (or the
# DOMAIN env var); defaults to localhost.
#
#   ./init.sh foliotrak.justinc.srv
set -euo pipefail

cd "$(dirname "$0")"

DOMAIN="${1:-${DOMAIN:-}}"
if [ -z "$DOMAIN" ]; then
  if [ -t 0 ]; then
    printf "Domain to serve over HTTPS [localhost]: "
    read -r DOMAIN
  fi
  DOMAIN="${DOMAIN:-localhost}"
fi

# The dev override starts a Vite dev server; the TLS stack serves the built
# assets through nginx instead, so pin the base compose file throughout.
COMPOSE="docker compose -f docker-compose.yml"

set_env() {
  local key="$1" value="$2"
  if grep -qE "^${key}=" .env; then
    sed -i "s|^${key}=.*|${key}=${value}|" .env
  else
    printf '%s=%s\n' "$key" "$value" >>.env
  fi
}

echo "==> TLS certificate"
if [ -f docker/nginx/certs/dev.crt ]; then
  echo "    present, skipping (delete docker/nginx/certs to renew)"
else
  docker/nginx/generate-certs.sh "$DOMAIN"
fi

echo "==> Environment"
if [ ! -f .env ]; then
  cp .env.example .env
  echo "    created .env from .env.example"
fi
set_env APP_URL "https://$DOMAIN"
set_env SESSION_DOMAIN "$DOMAIN"
set_env SESSION_SECURE_COOKIE "true"
set_env SANCTUM_STATEFUL_DOMAINS "$DOMAIN"
set_env CORS_ALLOWED_ORIGINS "https://$DOMAIN"
set_env NGINX_SERVER_NAME "$DOMAIN"
echo "    pinned HTTPS settings for $DOMAIN"

echo "==> Building images"
$COMPOSE build

echo "==> Installing PHP dependencies"
$COMPOSE run --rm --no-deps app composer install

if ! grep -qE '^APP_KEY=base64:' .env; then
  echo "==> Generating APP_KEY"
  $COMPOSE run --rm --no-deps app php artisan key:generate
fi

echo "==> Building frontend assets"
docker run --rm \
  --user "$(id -u):$(id -g)" \
  -e HOME=/tmp \
  -v "$PWD":/var/www/html \
  -w /var/www/html \
  node:22-alpine sh -c "npm install && npm run build"

echo "==> Starting the stack"
$COMPOSE up -d
# nginx picks its config from cert presence at startup; bounce it in case it was
# already running before the certificate existed.
$COMPOSE restart nginx

echo "==> Species seed data"
# `docker compose exec` ignores depends_on, so wait for the app container and a
# reachable Meilisearch before seeding; the import builds the search index.
seed_ready=false
attempts=0
while [ "$attempts" -lt 60 ]; do
  if $COMPOSE exec -T app php -r 'exit(@file_get_contents("http://meilisearch:7700/health") !== false ? 0 : 1);' >/dev/null 2>&1; then
    seed_ready=true
    break
  fi
  attempts=$((attempts + 1))
  sleep 2
done
if [ "$seed_ready" = true ]; then
  # The download is a few GB on first run; refresh-seed reuses a recent file and
  # import-seed upserts, so re-running init.sh is cheap. A failure here is not
  # fatal: search falls back to live GBIF until the seed lands.
  if $COMPOSE exec -T app php artisan species:refresh-seed \
    && $COMPOSE exec -T app php artisan species:import-seed; then
    :
  else
    echo "    species seed did not finish; the app runs and falls back to live GBIF." >&2
    echo "    retry: $COMPOSE exec app php artisan species:refresh-seed && $COMPOSE exec app php artisan species:import-seed" >&2
  fi
else
  echo "    app or Meilisearch not ready in time; skipped seeding." >&2
  echo "    seed later: $COMPOSE exec app php artisan species:refresh-seed && $COMPOSE exec app php artisan species:import-seed" >&2
fi

echo
echo "Foliotrak is starting at https://$DOMAIN"
echo "The certificate is self-signed; accept the browser warning or trust"
echo "docker/nginx/certs/dev.crt on your clients."

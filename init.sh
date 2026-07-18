#!/usr/bin/env bash
# One-command bring-up: self-signed TLS, environment, dependencies, frontend
# assets, and the full stack with the database migrated and seeded. Safe to
# re-run. Pass the domain to serve over HTTPS as the first argument (or the
# DOMAIN env var); defaults to localhost.
#
#   ./init.sh foliotrak.lan
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
TLS_CHOICE=1
if [ -f docker/nginx/certs/dev.crt ]; then
  echo "    present, skipping (delete docker/nginx/certs to renew)"
else
  if [ -t 0 ]; then
    echo ""
    echo "    TLS is strongly recommended. Even a self-signed certificate protects"
    echo "    session cookies and API traffic on your network."
    echo ""
    echo "    How would you like to handle TLS?"
    echo "      1) Generate a self-signed certificate (recommended)"
    echo "      2) Use your own certificate and key files"
    echo "      3) No TLS — serve over HTTP on port 80"
    echo ""
    while :; do
      printf "    Choice [1]: "
      read -r TLS_CHOICE
      TLS_CHOICE="${TLS_CHOICE:-1}"
      case "$TLS_CHOICE" in
        1|2|3) break ;;
        *) echo "    Enter 1, 2 or 3." ;;
      esac
    done
  fi
  case "$TLS_CHOICE" in
    1)
      docker/nginx/generate-certs.sh "$DOMAIN"
      ;;
    2)
      while :; do
        printf "    Path to certificate file (PEM): "
        read -r TLS_CERT_SRC
        printf "    Path to private key file: "
        read -r TLS_KEY_SRC
        if [ -f "$TLS_CERT_SRC" ] && [ -r "$TLS_CERT_SRC" ] \
          && [ -f "$TLS_KEY_SRC" ] && [ -r "$TLS_KEY_SRC" ]; then
          break
        fi
        echo "    Certificate or key not found or unreadable; try again."
      done
      mkdir -p docker/nginx/certs
      # Key lands first: the skip-check above keys on dev.crt, so an
      # interrupted copy must never leave a crt without its key.
      cp "$TLS_KEY_SRC" docker/nginx/certs/dev.key
      cp "$TLS_CERT_SRC" docker/nginx/certs/dev.crt
      echo "    installed certificate and key into docker/nginx/certs/"
      ;;
    3)
      echo "    WARNING: Running without TLS. Session cookies will be sent in"
      echo "    cleartext. This is not recommended for any network you do not"
      echo "    fully control."
      ;;
  esac
fi

if [ "$TLS_CHOICE" = 3 ]; then
  SCHEME=http
  SECURE_COOKIE=false
else
  SCHEME=https
  SECURE_COOKIE=true
fi

echo "==> Environment"
if [ ! -f .env ]; then
  cp .env.example .env
  echo "    created .env from .env.example"
fi
set_env APP_URL "$SCHEME://$DOMAIN"
set_env SESSION_DOMAIN "$DOMAIN"
set_env SESSION_SECURE_COOKIE "$SECURE_COOKIE"
set_env SANCTUM_STATEFUL_DOMAINS "$DOMAIN"
set_env CORS_ALLOWED_ORIGINS "$SCHEME://$DOMAIN"
set_env NGINX_SERVER_NAME "$DOMAIN"
# Containers run as the invoking user so bind-mounted files stay editable on the host.
set_env FOLIOTRAK_UID "$(id -u)"
set_env FOLIOTRAK_GID "$(id -g)"
if [ "$SCHEME" = https ]; then
  echo "    pinned HTTPS settings for $DOMAIN"
else
  echo "    pinned HTTP settings for $DOMAIN"
fi

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

echo "==> Database migrations and seeders"
# Compose gates the app service on a healthy MySQL, so this can run immediately.
$COMPOSE exec -T app php artisan migrate --force --seed

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
    && $COMPOSE exec -T app php artisan scout:sync-index-settings \
    && $COMPOSE exec -T app php artisan species:import-seed; then
    :
  else
    echo "    species seed did not finish; the app runs and falls back to live GBIF." >&2
    echo "    retry: $COMPOSE exec app php artisan species:refresh-seed && $COMPOSE exec app php artisan scout:sync-index-settings && $COMPOSE exec app php artisan species:import-seed" >&2
  fi
else
  echo "    app or Meilisearch not ready in time; skipped seeding." >&2
  echo "    seed later: $COMPOSE exec app php artisan species:refresh-seed && $COMPOSE exec app php artisan scout:sync-index-settings && $COMPOSE exec app php artisan species:import-seed" >&2
fi

echo
echo "Foliotrak is starting at $SCHEME://$DOMAIN"
case "$TLS_CHOICE" in
  2)
    echo "The certificate was provided externally; replace"
    echo "docker/nginx/certs/dev.crt and dev.key to renew it."
    ;;
  3)
    echo "Serving over plain HTTP; re-run init.sh and pick a TLS option"
    echo "to enable HTTPS."
    ;;
  *)
    echo "The certificate is self-signed; accept the browser warning or trust"
    echo "docker/nginx/certs/dev.crt on your clients."
    ;;
esac

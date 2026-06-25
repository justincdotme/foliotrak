#!/bin/sh
set -eu

TARGET_CONFIG="/etc/nginx/conf.d/default.conf"
CERT_FILE="/etc/nginx/certs/dev.crt"
KEY_FILE="/etc/nginx/certs/dev.key"
TEMPLATE_DIR="/opt/foliotrak-nginx/templates"

# server_name is rendered from the environment so the bring-up script can point
# nginx at the configured host. Only SERVER_NAME is substituted; nginx's own
# runtime variables ($host, $uri, ...) are left untouched.
export SERVER_NAME="${SERVER_NAME:-_}"

if [ -f "$CERT_FILE" ] && [ -f "$KEY_FILE" ]; then
  envsubst '${SERVER_NAME}' <"$TEMPLATE_DIR/https.conf" >"$TARGET_CONFIG"
  echo "Using HTTPS Nginx configuration (server_name $SERVER_NAME)."
else
  envsubst '${SERVER_NAME}' <"$TEMPLATE_DIR/http.conf" >"$TARGET_CONFIG"
  echo "TLS certificates not found. Using HTTP-only Nginx configuration (server_name $SERVER_NAME)."
fi

exec nginx -g "daemon off;"

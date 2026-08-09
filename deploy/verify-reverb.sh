#!/bin/bash
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

strip_env_value() {
    local value="$1"
    value="${value%$'\r'}"
    value="${value%\"}"
    value="${value#\"}"
    value="${value%\'}"
    value="${value#\'}"
    printf '%s' "$value"
}

read_env_var() {
    local key="$1"
    local default="${2:-}"
    local value="$default"

    if [[ ! -f "$DIR/.env" ]]; then
        printf '%s' "$value"
        return
    fi

    while IFS= read -r line || [[ -n "$line" ]]; do
        if [[ "$line" =~ ^[[:space:]]*# ]] || [[ -z "$line" ]]; then
            continue
        fi

        if [[ "$line" =~ ^${key}= ]]; then
            value="$(strip_env_value "${line#*=}")"
        fi
    done < "$DIR/.env"

    printf '%s' "$value"
}

PORT="$(read_env_var REVERB_SERVER_PORT "${REVERB_SERVER_PORT:-8080}")"
REVERB_HOST="$(read_env_var REVERB_HOST "")"
REVERB_APP_KEY="$(read_env_var REVERB_APP_KEY "")"

echo "=== Reverb service ==="
systemctl is-active reverb 2>/dev/null || echo "reverb service not found"
systemctl status reverb --no-pager 2>/dev/null | head -5 || true

echo
echo "=== Reverb listening on ${PORT}? ==="
if command -v ss >/dev/null 2>&1; then
    ss -tlnp | grep ":${PORT}" || echo "nothing listening on ${PORT}"
else
    netstat -tlnp 2>/dev/null | grep ":${PORT}" || echo "nothing listening on ${PORT}"
fi

echo
echo "=== Direct WebSocket upgrade to Reverb ==="
if [[ -z "$REVERB_HOST" ]]; then
    echo "REVERB_HOST is not set in .env; skipping WebSocket curl test"
elif [[ -z "$REVERB_APP_KEY" ]]; then
    echo "REVERB_APP_KEY is not set in .env; skipping WebSocket curl test"
else
    # WebSocket upgrades stay open; --max-time avoids hanging on success (HTTP 101).
    curl --max-time 3 -sS -o /dev/null -w "HTTP %{http_code}\n" \
        -H "Host: ${REVERB_HOST}" \
        -H "Connection: Upgrade" \
        -H "Upgrade: websocket" \
        -H "Sec-WebSocket-Version: 13" \
        -H "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==" \
        "http://127.0.0.1:${PORT}/app/${REVERB_APP_KEY}" \
        || echo "curl to Reverb failed (timeout with no response may mean Reverb accepted the upgrade)"
fi

echo
echo "=== Caddy /app route present? ==="
if grep -q 'reverb.caddy' /etc/caddy/Caddyfile 2>/dev/null \
    || grep -q 'app/\*' /etc/caddy/Caddyfile 2>/dev/null; then
    echo "reverb.caddy imported (proxies /app/* → 127.0.0.1:${PORT})"
elif grep -q 'app/\*' "$DIR/deploy/caddy/snippets/reverb.caddy" 2>/dev/null \
    && grep -q 'reverb.caddy' /etc/caddy/Caddyfile 2>/dev/null; then
    echo "reverb.caddy imported"
else
    echo "WARNING: no reverb.caddy import in /etc/caddy/Caddyfile — live charts need /app/* proxied to port ${PORT}"
fi

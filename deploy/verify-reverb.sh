#!/bin/bash
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PORT="${REVERB_SERVER_PORT:-8081}"

if [[ -f "$DIR/.env" ]]; then
    while IFS= read -r line || [[ -n "$line" ]]; do
        if [[ "$line" =~ ^REVERB_SERVER_PORT= ]]; then
            PORT="${line#REVERB_SERVER_PORT=}"
            PORT="${PORT%\"}"
            PORT="${PORT#\"}"
        fi
    done < "$DIR/.env"
fi

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
curl -sS -o /dev/null -w "HTTP %{http_code}\n" \
    -H "Host: bbq.fiskkarta.com" \
    -H "Connection: Upgrade" \
    -H "Upgrade: websocket" \
    -H "Sec-WebSocket-Version: 13" \
    -H "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==" \
    "http://127.0.0.1:${PORT}/app/${REVERB_APP_KEY:-tebja3nfp1qop1qkysrh}" \
    || echo "curl to Reverb failed"

echo
echo "=== Caddy /app route present? ==="
grep -n 'app/\*' /etc/caddy/Caddyfile 2>/dev/null || echo "no /app/* handler in Caddyfile"
grep -n "127.0.0.1:${PORT}" /etc/caddy/Caddyfile 2>/dev/null || echo "Caddyfile may not proxy to port ${PORT}"

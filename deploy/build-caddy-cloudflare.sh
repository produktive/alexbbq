#!/bin/bash
set -euo pipefail

# Build a Caddy binary with the official Cloudflare DNS module for ACME DNS-01.
# Run on the Pi (or cross-build for its architecture).
#
# Usage:
#   ./deploy/build-caddy-cloudflare.sh
#   sudo mv caddy /usr/bin/caddy
#   sudo setcap cap_net_bind_service=+ep /usr/bin/caddy   # if binding :443 directly
#   caddy version

OUTPUT="${OUTPUT:-caddy}"
GO="${GO:-go}"

if ! command -v "$GO" >/dev/null 2>&1; then
    echo "Go is required. Install with: sudo apt install golang-go" >&2
    exit 1
fi

if ! command -v xcaddy >/dev/null 2>&1; then
    echo "Installing xcaddy..."
    "$GO" install github.com/caddyserver/xcaddy/cmd/xcaddy@latest
    export PATH="${PATH}:$(go env GOPATH)/bin"
fi

echo "Building Caddy with Cloudflare DNS module..."
xcaddy build --with github.com/caddy-dns/cloudflare --output "$OUTPUT"

echo "Built ./$OUTPUT"
echo "Install: sudo install -m 755 $OUTPUT /usr/bin/caddy"

#!/bin/bash
# Install Caddy for Raspberry Pi ARMv6 (Pi Zero / Pi 1) with the Cloudflare DNS plugin.
#
# Do NOT use the Cloudsmith apt repo or `caddy add-package` on ARMv6 — those ship
# ARMv7 binaries and fail with "Illegal instruction".
#
# Usage (as root):
#   sudo ./deploy/install-caddy-armv6.sh
#
# Optional env:
#   CADDY_VERSION=2.11.4
#   CADDY_CUSTOM_PATH=/tmp/caddy-test   (use a pre-downloaded binary)
set -euo pipefail

CADDY_VERSION="${CADDY_VERSION:-2.11.4}"
CADDY_DEB="caddy_${CADDY_VERSION}_linux_armv6.deb"
CADDY_DEB_URL="https://github.com/caddyserver/caddy/releases/download/v${CADDY_VERSION}/${CADDY_DEB}"
CADDY_CUSTOM_URL="https://caddyserver.com/api/download?os=linux&arch=arm&arm=6&p=github.com/caddy-dns/cloudflare"

if [[ "$(id -u)" -ne 0 ]]; then
    echo "Run as root: sudo $0" >&2
    exit 1
fi

arch="$(uname -m)"
if [[ "$arch" != "armv6l" ]]; then
    echo "Warning: expected armv6l (Pi Zero / Pi 1), got ${arch}." >&2
    echo "This script is required on ARMv6. On ARMv7+ you may use the standard Caddy apt repo." >&2
fi

download_custom_caddy() {
    local dest="$1"
    local attempt

    for attempt in 1 2 3; do
        echo "==> Downloading ARMv6 Caddy with Cloudflare DNS plugin (attempt ${attempt}/3, may take several minutes)"
        if curl -fSL --retry 3 --retry-delay 5 --connect-timeout 30 --max-time 900 \
            "$CADDY_CUSTOM_URL" -o "$dest"; then
            chmod +x "$dest"

            if head -c 20 "$dest" | grep -qi '<!DOCTYPE\|<html'; then
                echo "Warning: download returned HTML, not a binary (attempt ${attempt})" >&2
                rm -f "$dest"
                continue
            fi

            local size
            size="$(wc -c < "$dest" | tr -d '[:space:]')"
            if (( size < 1000000 )); then
                echo "Warning: download too small (${size} bytes), likely incomplete (attempt ${attempt})" >&2
                rm -f "$dest"
                continue
            fi

            if "$dest" version >/dev/null 2>&1; then
                return 0
            fi

            echo "Warning: binary failed to execute (attempt ${attempt}): $(file "$dest")" >&2
            rm -f "$dest"
        else
            echo "Warning: curl download failed (attempt ${attempt})" >&2
        fi

        sleep 5
    done

    echo "Error: could not download a working ARMv6 Caddy binary with Cloudflare DNS." >&2
    echo "Try manually on the Pi:" >&2
    echo "  curl -fSL '$CADDY_CUSTOM_URL' -o /tmp/caddy-test && chmod +x /tmp/caddy-test && /tmp/caddy-test version" >&2
    exit 1
}

echo "==> Removing Cloudsmith apt repo (ARMv7 binary) if present"
rm -f /etc/apt/sources.list.d/caddy-stable.list
rm -f /usr/share/keyrings/caddy-stable-archive-keyring.gpg

echo "==> Stopping stray Caddy processes (manual 'caddy start' blocks systemd on :2019)"
systemctl stop caddy 2>/dev/null || true
pkill -x caddy 2>/dev/null || true
sleep 1

echo "==> Disabling Apache (conflicts with Caddy on :80)"
systemctl stop apache2 2>/dev/null || true
systemctl disable apache2 2>/dev/null || true

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

echo "==> Downloading ARMv6 Caddy ${CADDY_VERSION} (.deb for systemd unit files)"
curl -fSL --retry 3 -o "${tmpdir}/${CADDY_DEB}" "$CADDY_DEB_URL"
dpkg -i "${tmpdir}/${CADDY_DEB}" || apt-get install -f -y
systemctl daemon-reload 2>/dev/null || true

if [[ -n "${CADDY_CUSTOM_PATH:-}" && -f "$CADDY_CUSTOM_PATH" ]]; then
    echo "==> Using pre-downloaded Caddy binary: ${CADDY_CUSTOM_PATH}"
    cp "$CADDY_CUSTOM_PATH" "${tmpdir}/caddy-custom"
    chmod +x "${tmpdir}/caddy-custom"
else
    download_custom_caddy "${tmpdir}/caddy-custom"
fi

echo "==> Verifying custom binary"
"${tmpdir}/caddy-custom" version
"${tmpdir}/caddy-custom" list-modules | grep -qi cloudflare || {
    echo "Error: Cloudflare DNS module missing from custom binary" >&2
    exit 1
}

echo "==> Installing custom binary to /usr/bin/caddy"
cp "${tmpdir}/caddy-custom" /usr/bin/caddy
chmod 755 /usr/bin/caddy
setcap 'cap_net_bind_service=+ep' /usr/bin/caddy

echo "==> Caddy installed:"
caddy version
caddy list-modules | grep -i cloudflare
echo
echo "Next: configure /etc/caddy/Caddyfile and Cloudflare token in"
echo "  /etc/systemd/system/caddy.service.d/cloudflare.conf"
echo "  (or run deploy/install-pi-production.sh / deploy/install-cloudflare-ddns.sh)"
echo "Then: sudo systemctl restart caddy"

if [[ ! -f /etc/systemd/system/caddy.service.d/cloudflare.conf ]]; then
    echo
    echo "WARNING: /etc/systemd/system/caddy.service.d/cloudflare.conf is missing." >&2
    echo "Caddy will fail to start with Cloudflare DNS TLS until you create it." >&2
fi

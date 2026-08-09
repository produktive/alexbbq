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

echo "==> Removing Cloudsmith apt repo (ARMv7 binary) if present"
rm -f /etc/apt/sources.list.d/caddy-stable.list
rm -f /usr/share/keyrings/caddy-stable-archive-keyring.gpg

echo "==> Stopping stray Caddy processes (manual 'caddy start' blocks systemd on :2019)"
systemctl stop caddy 2>/dev/null || true
pkill caddy 2>/dev/null || true
sleep 1

echo "==> Disabling Apache (conflicts with Caddy on :80)"
systemctl stop apache2 2>/dev/null || true
systemctl disable apache2 2>/dev/null || true

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

echo "==> Downloading ARMv6 Caddy ${CADDY_VERSION} (.deb for systemd unit files)"
wget -q -O "${tmpdir}/${CADDY_DEB}" "$CADDY_DEB_URL"
dpkg -i "${tmpdir}/${CADDY_DEB}" || apt-get install -f -y

echo "==> Downloading ARMv6 Caddy with Cloudflare DNS plugin (this may take several minutes)"
wget -q -O "${tmpdir}/caddy-custom" "$CADDY_CUSTOM_URL"
chmod +x "${tmpdir}/caddy-custom"

echo "==> Verifying custom binary"
file "${tmpdir}/caddy-custom" | grep -q 'ARM, EABI5' || {
    echo "Error: downloaded binary is not 32-bit ARM EABI5" >&2
    exit 1
}
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

#!/bin/bash
# Install Cloudflare DDNS cron for Alex BBQ (cloudflare-ddns.sh).
#
# Reads CLOUDFLARE_* from .env. Installs jq if missing and adds a cron job
# for APP_USER (default: pi) every 5 minutes.
#
# Usage (as root):
#   sudo ./deploy/install-cloudflare-ddns.sh
#
# Or with env vars (non-interactive):
#   sudo DOMAIN=bbq.example.com \
#        CLOUDFLARE_API_TOKEN=xxx \
#        CLOUDFLARE_ZONE_ID=yyy \
#        ./deploy/install-cloudflare-ddns.sh
#
# Optional env:
#   APP_DIR=/var/www/alexbbq
#   APP_USER=pi
#   CLOUDFLARE_DNS_RECORD=bbq.example.com  (defaults to DOMAIN)
#   SKIP_DDNS_TEST=1                       (skip --dry-run after install)
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_DIR="${APP_DIR:-$DIR}"
APP_USER="${APP_USER:-pi}"
DOMAIN="${DOMAIN:-}"
CLOUDFLARE_API_TOKEN="${CLOUDFLARE_API_TOKEN:-}"
CLOUDFLARE_ZONE_ID="${CLOUDFLARE_ZONE_ID:-}"
CLOUDFLARE_DNS_RECORD="${CLOUDFLARE_DNS_RECORD:-}"
SKIP_DDNS_TEST="${SKIP_DDNS_TEST:-0}"
CRON_SCHEDULE="${CRON_SCHEDULE:-*/5 * * * *}"

log() { echo "==> $*"; }
die() { echo "Error: $*" >&2; exit 1; }

require_root() {
    if [[ "$(id -u)" -ne 0 ]]; then
        die "Run as root: sudo $0"
    fi
}

prompt_missing_vars() {
    if [[ -z "$DOMAIN" && -f "$APP_DIR/.env" ]]; then
        DOMAIN="$(grep -E '^APP_URL=' "$APP_DIR/.env" | head -1 | sed 's|^APP_URL=https://||; s|^APP_URL=http://||' || true)"
    fi

    if [[ -z "$CLOUDFLARE_API_TOKEN" && -f "$APP_DIR/.env" ]]; then
        CLOUDFLARE_API_TOKEN="$(grep -E '^CLOUDFLARE_API_TOKEN=' "$APP_DIR/.env" | head -1 | cut -d= -f2- || true)"
    fi

    if [[ -z "$CLOUDFLARE_ZONE_ID" && -f "$APP_DIR/.env" ]]; then
        CLOUDFLARE_ZONE_ID="$(grep -E '^CLOUDFLARE_ZONE_ID=' "$APP_DIR/.env" | head -1 | cut -d= -f2- || true)"
    fi

    if [[ -z "$DOMAIN" ]]; then
        read -r -p "Public domain / DNS record (e.g. bbq.example.com): " DOMAIN
    fi

    CLOUDFLARE_DNS_RECORD="${CLOUDFLARE_DNS_RECORD:-$DOMAIN}"

    if [[ -z "$CLOUDFLARE_API_TOKEN" ]]; then
        read -r -p "Cloudflare API token (Zone DNS Edit): " CLOUDFLARE_API_TOKEN
    fi

    if [[ -z "$CLOUDFLARE_ZONE_ID" ]]; then
        read -r -p "Cloudflare Zone ID: " CLOUDFLARE_ZONE_ID
    fi

    [[ -n "$DOMAIN" ]] || die "DOMAIN is required"
    [[ -n "$CLOUDFLARE_API_TOKEN" ]] || die "CLOUDFLARE_API_TOKEN is required"
    [[ -n "$CLOUDFLARE_ZONE_ID" ]] || die "CLOUDFLARE_ZONE_ID is required"
    [[ -n "$CLOUDFLARE_DNS_RECORD" ]] || die "CLOUDFLARE_DNS_RECORD is required"
}

install_jq() {
    if command -v jq >/dev/null 2>&1; then
        return
    fi

    log "Installing jq"
    apt-get update
    apt-get install -y jq
}

patch_env() {
    local env_file="$APP_DIR/.env"
    local key="$1"
    local value="$2"

    [[ -f "$env_file" ]] || die ".env not found at ${env_file}"

    if grep -q "^${key}=" "$env_file"; then
        sed -i "s|^${key}=.*|${key}=${value}|" "$env_file"
    else
        echo "${key}=${value}" >> "$env_file"
    fi
}

write_cloudflare_env() {
    log "Writing CLOUDFLARE_* values to .env"
    patch_env CLOUDFLARE_API_TOKEN "$CLOUDFLARE_API_TOKEN"
    patch_env CLOUDFLARE_ZONE_ID "$CLOUDFLARE_ZONE_ID"
    patch_env CLOUDFLARE_DNS_RECORD "$CLOUDFLARE_DNS_RECORD"
}

install_cron_job() {
    local script_path="$APP_DIR/cloudflare-ddns.sh"
    local cron_line="${CRON_SCHEDULE} ${script_path}"

    [[ -x "$script_path" ]] || chmod +x "$script_path"
    id "$APP_USER" >/dev/null 2>&1 || die "User ${APP_USER} does not exist"

    log "Installing cron job for ${APP_USER}"
    (
        sudo -u "$APP_USER" crontab -l 2>/dev/null | grep -v 'cloudflare-ddns.sh' || true
        echo "$cron_line"
    ) | sudo -u "$APP_USER" crontab -

    log "Cron installed:"
    sudo -u "$APP_USER" crontab -l | grep cloudflare-ddns.sh
}

test_ddns_script() {
    if [[ "$SKIP_DDNS_TEST" == "1" ]]; then
        return
    fi

    log "Testing cloudflare-ddns.sh (--dry-run)"
    sudo -u "$APP_USER" bash -lc "cd '$APP_DIR' && ./cloudflare-ddns.sh --dry-run"
}

main() {
    require_root
    [[ -f "$APP_DIR/cloudflare-ddns.sh" ]] || die "Missing ${APP_DIR}/cloudflare-ddns.sh"

    prompt_missing_vars
    install_jq
    write_cloudflare_env
    install_cron_job
    test_ddns_script

    echo
    echo "Cloudflare DDNS installed. Logs: ${APP_DIR}/storage/logs/cloudflare-ddns.log"
}

main "$@"

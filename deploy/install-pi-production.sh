#!/bin/bash
# Fresh Raspberry Pi production install for Alex BBQ.
#
# Assumes Raspberry Pi OS with nothing preinstalled. Clones or updates the repo
# first, then run this script from the project root.
#
# Usage:
#   git clone https://github.com/produktive/alexbbq.git /var/www/alexbbq
#   cd /var/www/alexbbq && chmod +x deploy/*.sh
#   sudo DOMAIN=bbq.example.com \
#        CLOUDFLARE_API_TOKEN=xxx \
#        CLOUDFLARE_ZONE_ID=yyy \
#        ./deploy/install-pi-production.sh
#
# Optional env:
#   APP_DIR=/var/www/alexbbq     (default: directory containing this repo)
#   APP_USER=pi                  (owner of app files; runs composer/artisan)
#   DOMAIN=bbq.example.com       (required — public hostname)
#   CLOUDFLARE_API_TOKEN=...     (required — Zone DNS Edit for ACME)
#   CLOUDFLARE_ZONE_ID=...        (required for DDNS — or prompted)
#   CLOUDFLARE_DNS_RECORD=...     (optional — defaults to DOMAIN)
#   SKIP_DDNS=1                   (skip Cloudflare DDNS cron setup)
#   SKIP_MAVERICK=1               (skip pigpio daemon build)
#   SKIP_CADDY=1                 (skip Caddy install — already configured)
#   CADDY_VERSION=2.11.4
#
# Pi Zero W notes:
#   - Frontend assets are committed in public/build/ — no Node.js on the Pi.
#   - Caddy must be ARMv6 (install-caddy-armv6.sh); never use Cloudsmith apt.
#   - Do not run `caddy start`; use systemctl only.
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_DIR="${APP_DIR:-$DIR}"
APP_USER="${APP_USER:-pi}"
DOMAIN="${DOMAIN:-}"
CLOUDFLARE_API_TOKEN="${CLOUDFLARE_API_TOKEN:-}"
CLOUDFLARE_ZONE_ID="${CLOUDFLARE_ZONE_ID:-}"
CLOUDFLARE_DNS_RECORD="${CLOUDFLARE_DNS_RECORD:-}"
PHP_VERSION="${PHP_VERSION:-8.4}"
SKIP_MAVERICK="${SKIP_MAVERICK:-0}"
SKIP_CADDY="${SKIP_CADDY:-0}"
SKIP_DDNS="${SKIP_DDNS:-0}"

log() { echo "==> $*"; }
die() { echo "Error: $*" >&2; exit 1; }

run_as_app_user() {
    local cmd="$1"
    if [[ "$(id -un)" == "$APP_USER" ]]; then
        bash -lc "cd '$APP_DIR' && $cmd"
    else
        sudo -u "$APP_USER" -H bash -lc "cd '$APP_DIR' && $cmd"
    fi
}

require_root() {
    if [[ "$(id -u)" -ne 0 ]]; then
        die "Run as root: sudo $0"
    fi
}

prompt_missing_vars() {
    if [[ -z "$DOMAIN" ]]; then
        read -r -p "Public domain (e.g. bbq.example.com): " DOMAIN
    fi
    if [[ -z "$CLOUDFLARE_API_TOKEN" ]]; then
        read -r -p "Cloudflare API token (Zone DNS Edit): " CLOUDFLARE_API_TOKEN
    fi
    [[ -n "$DOMAIN" ]] || die "DOMAIN is required"
    [[ -n "$CLOUDFLARE_API_TOKEN" ]] || die "CLOUDFLARE_API_TOKEN is required"

    if [[ "$SKIP_DDNS" != "1" ]]; then
        CLOUDFLARE_DNS_RECORD="${CLOUDFLARE_DNS_RECORD:-$DOMAIN}"
        if [[ -z "$CLOUDFLARE_ZONE_ID" ]]; then
            read -r -p "Cloudflare Zone ID (for DDNS): " CLOUDFLARE_ZONE_ID
        fi
    fi
}

install_system_packages() {
    log "Installing system packages (PHP ${PHP_VERSION}, pigpio, jq, build tools, …)"
    apt-get update
    apt-get install -y \
        git curl wget ca-certificates \
        "php${PHP_VERSION}-cli" "php${PHP_VERSION}-fpm" \
        "php${PHP_VERSION}-curl" "php${PHP_VERSION}-intl" "php${PHP_VERSION}-xml" \
        "php${PHP_VERSION}-mbstring" "php${PHP_VERSION}-sqlite3" \
        "php${PHP_VERSION}-bcmath" "php${PHP_VERSION}-zip" "php${PHP_VERSION}-gd" \
        sqlite3 jq

    if [[ "$SKIP_MAVERICK" != "1" ]]; then
        apt-get install -y pigpio libpigpio-dev libsqlite3-dev gcc make
    fi
}

install_composer() {
    if command -v composer >/dev/null 2>&1; then
        log "Composer already installed"
        return
    fi

    log "Installing Composer"
    local tmp
    tmp="$(mktemp)"
    curl -fsSL https://getcomposer.org/installer -o "$tmp"
    php "$tmp" -- --install-dir=/usr/local/bin --filename=composer
    rm -f "$tmp"
}

fix_app_ownership() {
    log "Setting ownership to ${APP_USER}:www-data"
    id "$APP_USER" >/dev/null 2>&1 || die "User ${APP_USER} does not exist"
    chown -R "${APP_USER}:www-data" "$APP_DIR"
    chmod -R ug+rX "$APP_DIR"
}

configure_git_safe_directory() {
    log "Configuring git safe.directory for ${APP_USER}"
    run_as_app_user "git config --global --add safe.directory '$APP_DIR'"
}

ensure_sqlite_database() {
    log "Preparing SQLite database"
    rm -f "$APP_DIR/database/database.sqlite" "$APP_DIR/database/database.sqlite-"*
    run_as_app_user "touch database/database.sqlite"
    chown "${APP_USER}:www-data" "$APP_DIR/database" "$APP_DIR/database/database.sqlite"
    chmod 775 "$APP_DIR/database"
    chmod 664 "$APP_DIR/database/database.sqlite"
}

deploy_laravel_app() {
    log "Installing PHP dependencies and bootstrapping Laravel"
    run_as_app_user "composer install --no-dev --optimize-autoloader"

    if [[ ! -f "$APP_DIR/.env" ]]; then
        run_as_app_user "cp .env.example .env"
    fi

    ensure_sqlite_database
    run_as_app_user "php artisan key:generate --force"
    run_as_app_user "php artisan reverb:configure"
    run_as_app_user "php artisan webpush:configure"
    run_as_app_user "php artisan migrate --force"
    log "Seeding database (skipped if admin already exists)"
    run_as_app_user "php artisan db:seed --force" 2>/dev/null || log "Seed skipped — admin user already exists"

    if [[ ! -f "$APP_DIR/public/build/manifest.json" ]]; then
        die "Missing public/build/manifest.json — pull latest from git or run npm run build on another machine"
    fi
}

configure_production_env() {
    log "Writing production .env values for ${DOMAIN}"
    local env_file="$APP_DIR/.env"
    [[ -f "$env_file" ]] || die ".env not found"

    patch_env() {
        local key="$1"
        local value="$2"
        if grep -q "^${key}=" "$env_file"; then
            sed -i "s|^${key}=.*|${key}=${value}|" "$env_file"
        else
            echo "${key}=${value}" >> "$env_file"
        fi
    }

    patch_env APP_ENV production
    patch_env APP_DEBUG false
    patch_env "APP_URL" "https://${DOMAIN}"
    patch_env SESSION_SECURE_COOKIE true
    patch_env REVERB_HOST "$DOMAIN"
    patch_env REVERB_PORT 443
    patch_env REVERB_SCHEME https
    patch_env REVERB_SERVER_HOST 127.0.0.1
    patch_env REVERB_SERVER_PORT 8081
    patch_env REVERB_SERVER_SCHEME http
    patch_env MAVERICK_SCRIPT "${APP_DIR}/maverick.sh"
    patch_env MAVERICK_USE_SUDO true
    patch_env CLOUDFLARE_API_TOKEN "$CLOUDFLARE_API_TOKEN"

    if [[ "$SKIP_DDNS" != "1" ]]; then
        patch_env CLOUDFLARE_ZONE_ID "$CLOUDFLARE_ZONE_ID"
        patch_env CLOUDFLARE_DNS_RECORD "${CLOUDFLARE_DNS_RECORD:-$DOMAIN}"
    fi

    run_as_app_user "php artisan config:cache"
}

fix_laravel_permissions() {
    log "Fixing Laravel writable paths for www-data"
    chown -R "${APP_USER}:www-data" "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" "$APP_DIR/database"
    chmod -R ug+rwx "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
    chmod 775 "$APP_DIR/database"
    chmod 664 "$APP_DIR/database/database.sqlite" 2>/dev/null || true
    chown "${APP_USER}:www-data" "$APP_DIR/database/database.sqlite" 2>/dev/null || true
}

build_maverick() {
    if [[ "$SKIP_MAVERICK" == "1" ]]; then
        log "Skipping Maverick daemon build (SKIP_MAVERICK=1)"
        return
    fi

    log "Building Maverick daemon"
    run_as_app_user "gcc -o maverick maverick.c -lpigpio -lsqlite3 -lrt -pthread"
    chmod +x "$APP_DIR/maverick" "$APP_DIR/maverick.sh" "$APP_DIR/maverick-artisan.sh"

    local sudoers_dest="/etc/sudoers.d/alexbbq-maverick"
    sed "s|/var/www/alexbbq|${APP_DIR}|g" "$DIR/deploy/sudoers/maverick.example" > "$sudoers_dest"
    chmod 440 "$sudoers_dest"
    visudo -c
}

install_reverb_service() {
    log "Installing Reverb systemd service"
    "$DIR/deploy/install-reverb-service.sh"
}

install_and_configure_caddy() {
    if [[ "$SKIP_CADDY" == "1" ]]; then
        log "Skipping Caddy (SKIP_CADDY=1)"
        return
    fi

    CADDY_VERSION="${CADDY_VERSION:-2.11.4}" "$DIR/deploy/install-caddy-armv6.sh"

    log "Installing Caddyfile for ${DOMAIN}"
    sed \
        -e "s|/var/www/alexbbq|${APP_DIR}|g" \
        -e "s|bbq.fiskkarta.com|${DOMAIN}|g" \
        "$DIR/deploy/caddy/Caddyfile.example" > /etc/caddy/Caddyfile
    chown caddy:caddy /etc/caddy/Caddyfile
    chmod 644 /etc/caddy/Caddyfile

    log "Installing Cloudflare token for Caddy systemd unit"
    mkdir -p /etc/systemd/system/caddy.service.d
    cat > /etc/systemd/system/caddy.service.d/cloudflare.conf <<EOF
[Service]
Environment=CLOUDFLARE_API_TOKEN=${CLOUDFLARE_API_TOKEN}
EOF
    chmod 644 /etc/systemd/system/caddy.service.d/cloudflare.conf

    CLOUDFLARE_API_TOKEN="${CLOUDFLARE_API_TOKEN}" caddy validate --config /etc/caddy/Caddyfile
    systemctl daemon-reload
    systemctl enable caddy
    systemctl restart caddy
}

enable_php_fpm() {
    log "Enabling PHP-FPM"
    systemctl enable "php${PHP_VERSION}-fpm"
    systemctl restart "php${PHP_VERSION}-fpm"
}

install_cloudflare_ddns() {
    if [[ "$SKIP_DDNS" == "1" ]]; then
        log "Skipping Cloudflare DDNS (SKIP_DDNS=1)"
        return
    fi

    log "Installing Cloudflare DDNS cron"
    APP_DIR="$APP_DIR" APP_USER="$APP_USER" DOMAIN="$DOMAIN" \
        CLOUDFLARE_API_TOKEN="$CLOUDFLARE_API_TOKEN" \
        CLOUDFLARE_ZONE_ID="$CLOUDFLARE_ZONE_ID" \
        CLOUDFLARE_DNS_RECORD="${CLOUDFLARE_DNS_RECORD:-$DOMAIN}" \
        "$DIR/deploy/install-cloudflare-ddns.sh"
}

print_summary() {
    cat <<EOF

================================================================
Alex BBQ production install complete.

App:     ${APP_DIR}
Domain:  https://${DOMAIN}
Admin:   run 'php artisan db:seed' if you skipped the default user

Services:
  sudo systemctl status caddy
  sudo systemctl status php${PHP_VERSION}-fpm
  sudo systemctl status reverb

Verify:
  ./deploy/verify-reverb.sh
  curl -I https://${DOMAIN}

DDNS:
  cron runs ${APP_DIR}/cloudflare-ddns.sh every 5 minutes (${APP_USER})
  log: ${APP_DIR}/storage/logs/cloudflare-ddns.log

Pi Zero W — Caddy upgrades:
  Never use 'apt install caddy' or 'caddy add-package'.
  Re-run: sudo ./deploy/install-caddy-armv6.sh

If Pi-hole uses port 80, move its web UI to another port.
Never use 'caddy start' — use systemctl only.
================================================================
EOF
}

main() {
    require_root
    log "Fresh Pi install — no PHP, Composer, Caddy, or pigpio required beforehand"
    prompt_missing_vars
    [[ -d "$APP_DIR" ]] || die "App directory not found: ${APP_DIR}"

    install_system_packages
    install_composer
    fix_app_ownership
    configure_git_safe_directory
    deploy_laravel_app
    configure_production_env
    fix_laravel_permissions
    build_maverick
    enable_php_fpm
    install_reverb_service
    install_and_configure_caddy
    install_cloudflare_ddns
    print_summary
}

main "$@"

#!/bin/bash
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
STATE_FILE="$DIR/storage/app/cloudflare-ddns.ip"
API_BASE="https://api.cloudflare.com/client/v4"
IP_CHECK_URL="${CLOUDFLARE_IP_CHECK_URL:-https://api.ipify.org}"

DRY_RUN=false
FORCE=false

usage() {
    echo "Usage: $0 [--dry-run] [--force]" >&2
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --force)
            FORCE=true
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            usage
            exit 1
            ;;
    esac
done

log() {
    echo "[$(date -Is)] $*"
}

fail() {
    log "ERROR: $*" >&2
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || fail "Required command not found: $1"
}

load_cloudflare_env() {
    if [[ ! -f "$DIR/.env" ]]; then
        fail ".env file not found at $DIR/.env"
    fi

    while IFS= read -r line || [[ -n "$line" ]]; do
        line="${line%$'\r'}"

        if [[ "$line" =~ ^[[:space:]]*# ]] || [[ -z "$line" ]]; then
            continue
        fi

        if [[ ! "$line" =~ ^CLOUDFLARE_ ]]; then
            continue
        fi

        key="${line%%=*}"
        value="${line#*=}"

        if [[ "$value" =~ ^\".*\"$ ]]; then
            value="${value:1:${#value}-2}"
        elif [[ "$value" =~ ^\'.*\'$ ]]; then
            value="${value:1:${#value}-2}"
        fi

        export "$key=$value"
    done < "$DIR/.env"
}

valid_ipv4() {
    local ip=$1
    [[ "$ip" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ ]] || return 1

    local octet
    IFS='.' read -r -a octets <<< "$ip"
    for octet in "${octets[@]}"; do
        [[ "$octet" -le 255 ]] || return 1
    done
}

fetch_public_ipv4() {
    local response ip

    response="$(curl -4fsS --max-time 10 "$IP_CHECK_URL")" || fail "Unable to fetch public IP from [$IP_CHECK_URL]"

    ip="$(printf '%s' "$response" | tr -d '[:space:]')"

    if [[ -z "$ip" ]] && printf '%s' "$response" | grep -q '^ip='; then
        ip="$(printf '%s' "$response" | awk -F= '/^ip=/{print $2; exit}' | tr -d '[:space:]')"
    fi

    valid_ipv4 "$ip" || fail "Public IP lookup returned an invalid IPv4 address from [$IP_CHECK_URL]"

    printf '%s' "$ip"
}

read_stored_ip() {
    if [[ -f "$STATE_FILE" ]]; then
        tr -d '[:space:]' < "$STATE_FILE"
    fi
}

write_stored_ip() {
    local ip=$1
    mkdir -p "$(dirname "$STATE_FILE")"
    printf '%s\n' "$ip" > "$STATE_FILE"
}

cloudflare_api() {
    local method=$1
    local path=$2
    local data=${3:-}

    if [[ -n "$data" ]]; then
        curl -fsS --max-time 15 \
            -X "$method" \
            -H "Authorization: Bearer $CLOUDFLARE_API_TOKEN" \
            -H "Content-Type: application/json" \
            --data "$data" \
            "$API_BASE$path"
    else
        curl -fsS --max-time 15 \
            -X "$method" \
            -H "Authorization: Bearer $CLOUDFLARE_API_TOKEN" \
            -H "Content-Type: application/json" \
            "$API_BASE$path"
    fi
}

main() {
    require_command curl
    require_command jq

    load_cloudflare_env

    : "${CLOUDFLARE_API_TOKEN:?CLOUDFLARE_API_TOKEN is not configured in .env}"
    : "${CLOUDFLARE_ZONE_ID:?CLOUDFLARE_ZONE_ID is not configured in .env}"
    : "${CLOUDFLARE_DNS_RECORD:?CLOUDFLARE_DNS_RECORD is not configured in .env}"

    IP_CHECK_URL="${CLOUDFLARE_IP_CHECK_URL:-https://api.ipify.org}"

    local current_ip stored_ip record_json record_id record_name dns_ip

    current_ip="$(fetch_public_ipv4)"
    stored_ip="$(read_stored_ip)"

    if [[ "$FORCE" != true && "$stored_ip" == "$current_ip" ]]; then
        log "Public IP unchanged at $current_ip."
        exit 0
    fi

    record_json="$(cloudflare_api GET "/zones/${CLOUDFLARE_ZONE_ID}/dns_records?type=A&name=${CLOUDFLARE_DNS_RECORD}")" \
        || fail "Unable to fetch Cloudflare DNS records."

    if [[ "$(jq -r '.success' <<< "$record_json")" != "true" ]]; then
        fail "$(jq -r '.errors[]?.message // "Cloudflare API request failed."' <<< "$record_json")"
    fi

    record_id="$(jq -r '.result[0].id // empty' <<< "$record_json")"
    record_name="$(jq -r '.result[0].name // empty' <<< "$record_json")"
    dns_ip="$(jq -r '.result[0].content // empty' <<< "$record_json")"

    if [[ -z "$record_id" ]]; then
        fail "No A record found for [$CLOUDFLARE_DNS_RECORD]."
    fi

    if [[ "$FORCE" != true && "$dns_ip" == "$current_ip" ]]; then
        if [[ "$DRY_RUN" != true ]]; then
            write_stored_ip "$current_ip"
        fi

        log "DNS already points to $current_ip; local state synced for ${record_name:-$CLOUDFLARE_DNS_RECORD}."
        exit 0
    fi

    if [[ "$DRY_RUN" == true ]]; then
        log "Would update ${record_name:-$CLOUDFLARE_DNS_RECORD} from ${dns_ip:-unknown} to $current_ip."
        exit 0
    fi

    record_json="$(cloudflare_api PATCH "/zones/${CLOUDFLARE_ZONE_ID}/dns_records/${record_id}" "{\"content\":\"${current_ip}\"}")" \
        || fail "Unable to update Cloudflare DNS record."

    if [[ "$(jq -r '.success' <<< "$record_json")" != "true" ]]; then
        fail "$(jq -r '.errors[]?.message // "Cloudflare API request failed."' <<< "$record_json")"
    fi

    write_stored_ip "$current_ip"
    log "Updated ${record_name:-$CLOUDFLARE_DNS_RECORD} from ${dns_ip:-unknown} to $current_ip."
}

main "$@"

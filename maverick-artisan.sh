#!/bin/bash
set -uo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
COMMAND="${1:?artisan command required}"
ARG="${2:?artisan argument required}"
PHP="${MAVERICK_PHP:-/usr/bin/php8.4}"
USER="${MAVERICK_ARTISAN_USER:-www-data}"
LOG="$DIR/storage/logs/maverick-artisan.log"

mkdir -p "$(dirname "$LOG")"

if ! command -v "$PHP" >/dev/null 2>&1; then
    PHP="$(command -v php8.4 || command -v php)"
fi

{
    echo "---- $(date -Is) $COMMAND $ARG (php=$PHP user=$USER) ----"

    if id -u "$USER" >/dev/null 2>&1; then
        if command -v runuser >/dev/null 2>&1; then
            runuser -u "$USER" -- "$PHP" "$DIR/artisan" "$COMMAND" "$ARG"
        else
            sudo -u "$USER" "$PHP" "$DIR/artisan" "$COMMAND" "$ARG"
        fi
    else
        "$PHP" "$DIR/artisan" "$COMMAND" "$ARG"
    fi
} >> "$LOG" 2>&1

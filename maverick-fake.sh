#!/bin/bash
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PIDFILE="$DIR/storage/maverick-fake.pid"
LOG="$DIR/storage/logs/maverick-fake.log"

cd "$DIR"

mkdir -p "$(dirname "$LOG")" "$(dirname "$PIDFILE")"

resolve_php() {
    if [[ -n "${MAVERICK_PHP:-}" && -x "$MAVERICK_PHP" && "$MAVERICK_PHP" != *fpm* ]]; then
        echo "$MAVERICK_PHP"
        return
    fi

    if [[ -n "${MAVERICK_PHP:-}" && "$MAVERICK_PHP" == *fpm* ]]; then
        sibling="${MAVERICK_PHP/-fpm/}"
        if [[ -x "$sibling" ]]; then
            echo "$sibling"
            return
        fi
    fi

    if command -v php >/dev/null 2>&1; then
        command -v php
        return
    fi

    for candidate in \
        "$HOME/Library/Application Support/Herd/bin/php" \
        "$HOME/.config/herd/bin/php"; do
        if [[ -x "$candidate" ]]; then
            echo "$candidate"
            return
        fi
    done

    echo "Could not find a PHP binary. Set MAVERICK_PHP in .env." >&2
    exit 1
}

PHP="$(resolve_php)"

is_running() {
    [[ -f "$PIDFILE" ]] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null
}

case "${1:-}" in
    start)
        if is_running; then
            exit 0
        fi

        nohup env -u APP_ENV -u DB_CONNECTION -u DB_DATABASE -u DB_URL \
            "$PHP" "$DIR/artisan" maverick:simulate >> "$LOG" 2>&1 &
        echo $! > "$PIDFILE"

        sleep 0.5

        if is_running; then
            exit 0
        fi

        echo "fake maverick failed to start; see $LOG" >&2
        rm -f "$PIDFILE"
        exit 1
        ;;
    stop)
        if is_running; then
            kill "$(cat "$PIDFILE")" 2>/dev/null || true
        fi

        rm -f "$PIDFILE"
        exit 0
        ;;
    status)
        if is_running; then
            exit 0
        fi

        rm -f "$PIDFILE"
        exit 1
        ;;
    *)
        echo "Usage: $0 {start|stop|status}" >&2
        exit 1
        ;;
esac

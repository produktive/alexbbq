#!/bin/bash
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MAVERICK="$DIR/maverick"
LOG="$DIR/storage/logs/maverick.log"

cd "$DIR"

case "${1:-}" in
    start)
        if pgrep -x maverick > /dev/null 2>&1; then
            exit 0
        fi

        if [[ ! -x "$MAVERICK" ]]; then
            echo "maverick binary missing or not executable at $MAVERICK" >&2
            exit 1
        fi

        mkdir -p "$(dirname "$LOG")"
        nohup "$MAVERICK" >> "$LOG" 2>&1 &
        disown

        sleep 0.5

        if pgrep -x maverick > /dev/null 2>&1; then
            exit 0
        fi

        echo "maverick failed to start; see $LOG" >&2
        exit 1
        ;;
    stop)
        if pgrep -x maverick > /dev/null 2>&1; then
            pkill -x maverick
        fi

        exit 0
        ;;
    status)
        if pgrep -x maverick > /dev/null 2>&1; then
            exit 0
        fi

        exit 1
        ;;
    *)
        echo "Usage: $0 {start|stop|status}" >&2
        exit 1
        ;;
esac

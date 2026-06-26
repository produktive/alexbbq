#!/bin/bash
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SERVICE_NAME="${SERVICE_NAME:-reverb.service}"
UNIT_SRC="$DIR/deploy/systemd/reverb.service"
UNIT_DEST="/etc/systemd/system/$SERVICE_NAME"

if [[ "$(id -u)" -ne 0 ]]; then
    echo "Run as root: sudo $0" >&2
    exit 1
fi

if [[ ! -f "$UNIT_SRC" ]]; then
    echo "Missing unit file: $UNIT_SRC" >&2
    exit 1
fi

sed "s|@APP_DIR@|$DIR|g" "$UNIT_SRC" > "$UNIT_DEST"
chmod 644 "$UNIT_DEST"

systemctl daemon-reload
systemctl enable "$SERVICE_NAME"
systemctl restart "$SERVICE_NAME"

echo "Installed and started $SERVICE_NAME (app dir: $DIR)"
systemctl --no-pager status "$SERVICE_NAME"

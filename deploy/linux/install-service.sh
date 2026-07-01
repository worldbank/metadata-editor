#!/usr/bin/env bash
# install-service.sh — Install and configure the metadata-editor-worker systemd service.
#
# Usage:
#   sudo ./install-service.sh [--app-root PATH] [--user USER] [--group GROUP] [--max-jobs N]
#
# Defaults:
#   --app-root   /var/www/metadata-editor
#   --user       www-data
#   --group      www-data
#   --max-jobs   50

set -euo pipefail

# ── Defaults ─────────────────────────────────────────────────────────────────
APP_ROOT="/var/www/metadata-editor"
SERVICE_USER="www-data"
SERVICE_GROUP="www-data"
MAX_JOBS=50

SERVICE_NAME="metadata-editor-worker"
SERVICE_FILE="/etc/systemd/system/${SERVICE_NAME}.service"
OVERRIDE_DIR="/etc/systemd/system/${SERVICE_NAME}.service.d"
OVERRIDE_FILE="${OVERRIDE_DIR}/override.conf"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# ── Helpers ───────────────────────────────────────────────────────────────────
usage() {
    grep '^#' "$0" | sed 's/^# \{0,1\}//' | head -n 12
    exit 0
}

die() { echo "ERROR: $*" >&2; exit 1; }

require_root() {
    [[ $EUID -eq 0 ]] || die "This script must be run as root (use sudo)."
}

# ── Argument parsing ──────────────────────────────────────────────────────────
while [[ $# -gt 0 ]]; do
    case "$1" in
        --app-root)   APP_ROOT="$2";      shift 2 ;;
        --user)       SERVICE_USER="$2";  shift 2 ;;
        --group)      SERVICE_GROUP="$2"; shift 2 ;;
        --max-jobs)   MAX_JOBS="$2";      shift 2 ;;
        -h|--help)    usage ;;
        *) die "Unknown option: $1" ;;
    esac
done

require_root

# ── Validate ──────────────────────────────────────────────────────────────────
[[ -f "${SCRIPT_DIR}/${SERVICE_NAME}.service" ]] \
    || die "Service template not found: ${SCRIPT_DIR}/${SERVICE_NAME}.service"

id -u "$SERVICE_USER"  &>/dev/null || die "User '${SERVICE_USER}' does not exist."
id -g "$SERVICE_GROUP" &>/dev/null || die "Group '${SERVICE_GROUP}' does not exist."

[[ "$MAX_JOBS" =~ ^[0-9]+$ && "$MAX_JOBS" -gt 0 ]] \
    || die "--max-jobs must be a positive integer."

# ── Install service file ──────────────────────────────────────────────────────
echo "→ Copying service file to ${SERVICE_FILE} …"
cp "${SCRIPT_DIR}/${SERVICE_NAME}.service" "$SERVICE_FILE"
chmod 644 "$SERVICE_FILE"

# ── Write override ────────────────────────────────────────────────────────────
echo "→ Writing override to ${OVERRIDE_FILE} …"
mkdir -p "$OVERRIDE_DIR"
cat > "$OVERRIDE_FILE" <<EOF
[Service]
Environment="APP_ROOT=${APP_ROOT}"
Environment="WORKER_MAX_JOBS=${MAX_JOBS}"
User=${SERVICE_USER}
Group=${SERVICE_GROUP}
EOF
chmod 644 "$OVERRIDE_FILE"

# ── Reload and enable ─────────────────────────────────────────────────────────
echo "→ Reloading systemd daemon …"
systemctl daemon-reload

echo "→ Enabling and starting ${SERVICE_NAME} …"
systemctl enable --now "$SERVICE_NAME"

# ── Status ────────────────────────────────────────────────────────────────────
echo ""
echo "✔ Service installed successfully."
echo ""
systemctl status "$SERVICE_NAME" --no-pager || true
echo ""
echo "Useful commands:"
echo "  journalctl -u ${SERVICE_NAME} -f          # follow logs"
echo "  systemctl restart ${SERVICE_NAME}          # restart"
echo "  systemctl stop    ${SERVICE_NAME}          # stop"

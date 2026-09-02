#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_USER="${SUDO_USER:-$USER}"
SERVICE_FILE="/etc/systemd/system/starnms.service"

command -v systemctl >/dev/null 2>&1 || { echo "ERROR: systemd tidak tersedia."; exit 1; }

chmod +x "${APP_DIR}/start-nms-linux.sh"

# Dependency dipasang secara interaktif sebelum service dibuat. Service tidak
# pernah mencoba meminta password sudo ketika berjalan saat boot.
"${APP_DIR}/start-nms-linux.sh" --prepare-only

sudo tee "${SERVICE_FILE}" >/dev/null <<EOF
[Unit]
Description=STARNMS Network Monitoring System
After=network-online.target postgresql.service
Wants=network-online.target

[Service]
Type=simple
User=${APP_USER}
WorkingDirectory=${APP_DIR}
Environment=STARNMS_HOST=0.0.0.0
Environment=STARNMS_PORT=8000
ExecStart=${APP_DIR}/start-nms-linux.sh
Restart=always
RestartSec=5
TimeoutStopSec=20
KillMode=control-group

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable --now starnms.service
echo "STARNMS service aktif dan otomatis dijalankan saat boot."
sudo systemctl --no-pager --full status starnms.service || true

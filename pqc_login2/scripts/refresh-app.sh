#!/bin/bash
# Reload pqc_login routes after code changes (run on the Pi with sudo).
set -euo pipefail

echo "Disabling pqc_login..."
snap run nextcloud.occ app:disable pqc_login || true

echo "Enabling pqc_login..."
snap run nextcloud.occ app:enable pqc_login

echo "Repairing..."
snap run nextcloud.occ maintenance:repair --include-expensive || true

echo "Done. Open: /index.php/apps/pqc_login/register"
echo "Recovery: /index.php/apps/pqc_login/register?mode=recovery"

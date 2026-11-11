#!/bin/bash
# Update the PQC hardware key bound to a Nextcloud user (oc_preferences).
#
# This snap uses: occ user:setting  (NOT config:user:set)
#
# Usage (key must be ONE line, 64 hex chars, no spaces):
#   sudo ./set-pqc-key.sh USERNAME 64_CHAR_HEX_KEY
#
# Example:
#   sudo ./set-pqc-key.sh sohren f6db41e0f070b6d0e7ecf34454d27fa0348699a60f1bec6db0f79a81457e32a1

set -euo pipefail

USER="${1:-}"
KEY="${2:-}"

if [[ -z "$USER" || -z "$KEY" ]]; then
    echo "Usage: sudo $0 USERNAME 64_CHAR_HEX_KEY" >&2
    exit 1
fi

KEY="$(echo "$KEY" | tr '[:upper:]' '[:lower:]' | tr -cd 'a-f0-9')"

if [[ ${#KEY} -ne 64 ]]; then
    echo "Error: key must be exactly 64 hex characters (got ${#KEY})" >&2
    exit 1
fi

echo "Setting pqc_key for user: $USER"
snap run nextcloud.occ user:setting "$USER" pqc_login pqc_key "$KEY"
echo "Done. Current value:"
snap run nextcloud.occ user:setting "$USER" pqc_login pqc_key

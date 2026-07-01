#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REMOTE_HOST="example.org"
REMOTE_USER="ssh-user"
REMOTE_PATH="/pfad/zum/webroot"
RSYNC_IGNORE_FILE="$ROOT_DIR/.rsyncignore"

echo "Deploye Projekt nach ${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PATH}"

rsync \
  -avz \
  --delete \
  --exclude-from="$RSYNC_IGNORE_FILE" \
  "$ROOT_DIR/" \
  "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PATH}/"

echo "Deploy abgeschlossen."


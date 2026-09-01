#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RSYNC_IGNORE_FILE="$ROOT_DIR/.rsyncignore"

# Zielserver aus scripts/deploy.env (nicht im Repo). Vorlage: deploy.env.example
ENV_FILE="$ROOT_DIR/scripts/deploy.env"
if [ ! -f "$ENV_FILE" ]; then
  echo "Fehler: $ENV_FILE fehlt. scripts/deploy.env.example kopieren und ausfüllen." >&2
  exit 1
fi
# shellcheck disable=SC1090
source "$ENV_FILE"
: "${REMOTE_HOST:?REMOTE_HOST in scripts/deploy.env setzen}"
: "${REMOTE_USER:?REMOTE_USER in scripts/deploy.env setzen}"
: "${REMOTE_PATH:?REMOTE_PATH in scripts/deploy.env setzen}"

echo "Deploye Projekt nach ${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PATH}"

rsync \
  -avz \
  --delete \
  --exclude-from="$RSYNC_IGNORE_FILE" \
  "$ROOT_DIR/" \
  "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PATH}/"

echo "Deploy abgeschlossen."

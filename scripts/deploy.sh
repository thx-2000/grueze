#!/usr/bin/env bash
# Deployment per rsync. Aufruf:
#   bash scripts/deploy.sh            Probelauf, Prüfung, dann echter Abgleich
#   bash scripts/deploy.sh --dry-run  nur Probelauf + Prüfung, nichts übertragen
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RSYNC_IGNORE_FILE="$ROOT_DIR/.rsyncignore"

DRY_RUN_ONLY=0
if [ "${1:-}" = "--dry-run" ] || [ "${1:-}" = "-n" ]; then
  DRY_RUN_ONLY=1
fi

# Zielserver aus scripts/deploy.env (nicht im Repo). Vorlage: deploy.env.example
ENV_FILE="$ROOT_DIR/scripts/deploy.env"
if [ ! -f "$ENV_FILE" ]; then
  echo "Fehler: $ENV_FILE fehlt. scripts/deploy.env.example kopieren und ausfüllen." >&2
  exit 1
fi
if [ ! -f "$RSYNC_IGNORE_FILE" ]; then
  echo "Fehler: $RSYNC_IGNORE_FILE fehlt – ohne Ausschlussliste würde --delete Nutzerdaten löschen." >&2
  exit 1
fi
# shellcheck disable=SC1090
source "$ENV_FILE"
: "${REMOTE_HOST:?REMOTE_HOST in scripts/deploy.env setzen}"
: "${REMOTE_USER:?REMOTE_USER in scripts/deploy.env setzen}"
: "${REMOTE_PATH:?REMOTE_PATH in scripts/deploy.env setzen}"

# Optionaler eigener Schlüssel – nötig, wenn kein ssh-agent läuft (z. B. aus
# Skripten oder Werkzeugen heraus). Ohne SSH_KEY gilt die normale SSH-Konfiguration.
SSH_CMD="ssh -o BatchMode=yes"
if [ -n "${SSH_KEY:-}" ]; then
  SSH_CMD="ssh -i ${SSH_KEY/#\~/$HOME} -o BatchMode=yes"
fi

TARGET="${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PATH}/"

# Nutzerdaten und Konfiguration, die ein Deployment nie löschen darf. Sie sind
# über .rsyncignore ausgenommen; diese Liste fängt eine beschädigte oder
# unvollständige Ausschlussliste ab, bevor --delete etwas anrichtet.
PROTECTED=(
  "public/assets/uploads/"
  "storage/media/"
  "storage/documents/"
  "storage/backups/"
  "storage/app.key"
  "config/config.php"
)

echo "Probelauf gegen ${TARGET}"
PREVIEW="$(rsync -az --delete --dry-run --itemize-changes \
  --exclude-from="$RSYNC_IGNORE_FILE" -e "$SSH_CMD" \
  "$ROOT_DIR/" "$TARGET")"

DELETIONS="$(printf '%s\n' "$PREVIEW" | sed -n 's/^\*deleting *//p')"

BLOCKED=""
while IFS= read -r path; do
  [ -z "$path" ] && continue
  for protected in "${PROTECTED[@]}"; do
    case "$path" in
      "$protected"*) BLOCKED+="  $path"$'\n' ;;
    esac
  done
done <<< "$DELETIONS"

if [ -n "$BLOCKED" ]; then
  echo "ABBRUCH: Der Deploy würde geschützte Dateien auf dem Server löschen:" >&2
  printf '%s' "$BLOCKED" | head -30 >&2
  echo "Nichts übertragen. Erst .rsyncignore prüfen (geschützt: ${PROTECTED[*]})." >&2
  exit 1
fi

if [ -n "$DELETIONS" ]; then
  COUNT="$(printf '%s\n' "$DELETIONS" | grep -c .)"
  if [ "$COUNT" -eq 1 ]; then LABEL="1 Eintrag"; else LABEL="$COUNT Einträge"; fi
  echo "Würde auf dem Server löschen (${LABEL}):"
  printf '%s\n' "$DELETIONS" | sed 's/^/  /'
else
  echo "Keine Löschungen auf dem Server."
fi

if [ "$DRY_RUN_ONLY" -eq 1 ]; then
  echo "Nur Probelauf – nichts übertragen."
  exit 0
fi

echo "Deploye nach ${TARGET}"
rsync -avz --delete \
  --exclude-from="$RSYNC_IGNORE_FILE" -e "$SSH_CMD" \
  "$ROOT_DIR/" "$TARGET"

echo "Deploy abgeschlossen."

#!/usr/bin/env bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"
docker compose up -d --build
echo ""
echo "App:      http://localhost:8095"
echo "Adminer:  http://localhost:8096  (System: MySQL, Server: db, Benutzer: abi_user, Passwort: abi_local_pw, Datenbank: abi_adress_zentrale)"
echo "Erster Admin: http://localhost:8095/setup/admin"

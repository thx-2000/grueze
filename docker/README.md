# Lokale Docker-Testumgebung

Bildet einen typischen Shared-Hosting-Produktivstand möglichst nah nach,
damit sich Änderungen vor dem Deploy per `scripts/deploy.sh` lokal prüfen
lassen. Die Werte unten sind eine begründete Annäherung an gängige
all-inkl-/KAS-Umgebungen und lassen sich in `docker/Dockerfile` /
`docker-compose.yml` an den eigenen Hoster anpassen.

- **PHP 8.2** mit Apache/mod_php (klassisches Shared-Hosting-Setup, kein
  PHP-FPM), `public/` als Document Root, `.htaccess`/`mod_rewrite` aktiv.
- **MariaDB 10.11** (all-inkl ersetzt MySQL durch MariaDB seit der
  PHP-8-Umstellung).
- PHP-Erweiterungen: `pdo_mysql`, `zip` (für den XLSX-Kontaktimport). Die App
  läuft auch ohne `ext-imap` – das "Kopiere Mail in Gesendet-Ordner"-Feature
  prüft `function_exists('imap_open')` und wird lokal einfach übersprungen,
  da es ohnehin ein echtes externes IMAP-Postfach braucht, das lokal nicht
  sinnvoll simulierbar ist.
- Kein Composer/`vendor/` nötig – ohne PHPMailer fällt der Mailversand auf
  `mail()` zurück (lokal landet das schlicht im Leeren, es sei denn du
  richtest einen Mail-Catcher ein).

## Starten / Stoppen

Die Container starten **nie von selbst** (kein `restart: always`) – du oder
ich starten sie jeweils gezielt, und sie bleiben aus, bis das wieder passiert.

```bash
bash scripts/docker-up.sh     # baut bei Bedarf neu und startet alles
bash scripts/docker-down.sh   # stoppt und entfernt die Container (DB-Daten bleiben im Volume erhalten)
bash scripts/docker-logs.sh   # Logs verfolgen (optional: Servicename dranhängen, z. B. app)
```

Nach dem Start:

- App: http://localhost:8095
- Ersten Admin anlegen: http://localhost:8095/setup/admin
- Adminer (DB-Ansicht): http://localhost:8096
  (System: MySQL, Server: `db`, Benutzer: `grueze_user`, Passwort: `grueze_dev_pw`,
  Datenbank: `grueze`)

Die lokale `config/config.php` (nicht versioniert, siehe `.gitignore`) zeigt
auf diese Container und ist bereits fertig eingerichtet
(`base_url` = `http://localhost:8095`, `force_https` = `false`,
`debug` = `true`). Das Datenbankschema (`database/schema.sql`) wird beim
allerersten Start automatisch importiert.

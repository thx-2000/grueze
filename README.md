# Abi Adress Zentrale

Eine mobile-first PHP-Web-App für die Verwaltung von Kontaktdaten einer Abi-Stufe mit Rollen, CSV-Export, Copy-to-Clipboard-Funktion, Audit-Log und personalisiertem Mailversand.

## Setup

1. Webspace so konfigurieren, dass `public/` der Webroot ist.
2. `config/config.example.php` oder `config/config.production-template.php` nach `config/config.php` kopieren und Datenbank, Basis-URL sowie SMTP-Zugangsdaten eintragen.
3. `database/schema.sql` in die MySQL- oder MariaDB-Datenbank importieren.
4. Per Composer `phpmailer/phpmailer` installieren, wenn SMTP-Versand genutzt werden soll. Ohne PHPMailer fällt die App auf `mail()` zurück.
5. Sicherstellen, dass `public/assets/uploads/` und `storage/tmp/` beschreibbar sind.
6. Einen ersten Admin-Datensatz in `users` anlegen. Die zugehörige `role_id` ist die `admin`-Rolle aus `roles`.
7. Über `security.contact_detail_visibility` kann pro Datenfeld gesteuert werden, welche Rollen Adresse, E-Mail, Telefon, Geburtstag, Notizen und Login sehen dürfen. Fehlt diese Matrix, greift `security.private_contact_detail_roles` als Rückfall.
8. Unter `/contacts/import` können Admins eine XLSX-Liste mit den Spalten `Vorname`, `Geburtsname`, `Nachname akt.`, `Mail`, `Ort` und `Handy` importieren.

Alternativ kannst du nach dem ersten Deploy direkt die Seite `/setup/admin` aufrufen. Dort lässt sich genau ein erstes Admin-Konto über die Anwendung anlegen, solange noch kein Admin existiert.

Die Anwendung ist markenneutral. Name, Texte, Rechtstexte, Theme und Mail-Vorlagen werden nach der Installation über die Oberfläche bzw. `config/config.php` eingestellt – siehe **[docs/NEUE-INSTANZ.md](docs/NEUE-INSTANZ.md)**.

## Hinweise für all-inkl

- Die Anwendung ist für Shared Hosting mit klassischem PHP ohne Build-Prozess aufgebaut.
- Für SMTP über PHPMailer muss auf dem Server `vendor/autoload.php` vorhanden sein. Die App bindet diese Datei automatisch ein, sobald Composer-Dependencies installiert wurden.
- Ohne Composer fällt der Mailversand auf `mail()` zurück. Anhänge sind dann bewusst nicht verfügbar.
- `.htaccess` in `public/` leitet HTTP auf HTTPS um und schickt alle nicht vorhandenen Pfade auf `public/index.php`.

## Deploy per rsync

Für den Upload auf den all-inkl-Webspace liegt ein Skript unter `scripts/deploy.sh` bereit.

Voraussetzungen:

- SSH-Zugang ist lokal eingerichtet
- Zielserver: `example.org`
- SSH-User: `ssh-user`
- Zielpfad: `/pfad/zum/webroot`

Aufruf:

```bash
bash scripts/deploy.sh
```

Das Skript synchronisiert das Projekt per `rsync` auf den Server und schließt sensible oder serverlokale Dateien über `.rsyncignore` aus, insbesondere:

- `config/config.php`
- Upload-Inhalte
- temporäre Dateien
- Git-Metadaten

## Wichtige Ordner

- `public/`: Einstiegspunkt und Rewrite-Regeln
- `src/`: PHP-Logik, Controller, Repositories und Services
- `templates/`: Server-seitig gerenderte Ansichten
- `public/assets/css/`: Theming und Layout
- `public/assets/js/`: Vanilla-JS für Copy, Auswahl, dynamische Felder und Mail-Batches
- `database/`: Datenbankschema
- `storage/tmp/`: temporäre Mail-Anhänge

## Manuell noch zu erledigen

- `config/config.php` mit echten Zugangsdaten anlegen
- Datenbank importieren
- Ersten Admin erzeugen
- Optional Composer samt PHPMailer installieren
- DSGVO-Text im Layout ersetzen

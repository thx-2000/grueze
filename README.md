# Abi Adress Zentrale

Eine mobile-first PHP-Web-App für die Verwaltung von Kontaktdaten einer Abi-Stufe mit Rollen, CSV-Export, Copy-to-Clipboard-Funktion, Audit-Log und personalisiertem Mailversand.

## Setup

1. Webspace so konfigurieren, dass `public/` der Webroot ist.
2. `config/config.example.php` nach `config/config.php` kopieren und Datenbank, Basis-URL sowie SMTP-Zugangsdaten eintragen.
3. `database/schema.sql` in die MySQL- oder MariaDB-Datenbank importieren.
4. Optional per Composer `phpmailer/phpmailer` installieren, wenn SMTP mit Anhängen und robusterem Versand genutzt werden soll.
5. Sicherstellen, dass `assets/uploads/` und `storage/tmp/` beschreibbar sind.
6. Einen ersten Admin-Datensatz in `users` anlegen. Die zugehörige `role_id` ist die `admin`-Rolle aus `roles`.

## Hinweise für all-inkl

- Die Anwendung ist für Shared Hosting mit klassischem PHP ohne Build-Prozess aufgebaut.
- Ohne Composer fällt der Mailversand auf `mail()` zurück. Anhänge sind dann bewusst nicht verfügbar.
- `.htaccess` in `public/` leitet HTTP auf HTTPS um und schickt alle nicht vorhandenen Pfade auf `public/index.php`.

## Wichtige Ordner

- `public/`: Einstiegspunkt und Rewrite-Regeln
- `src/`: PHP-Logik, Controller, Repositories und Services
- `templates/`: Server-seitig gerenderte Ansichten
- `assets/css/`: Theming und Layout
- `assets/js/`: Vanilla-JS für Copy, Auswahl, dynamische Felder und Mail-Batches
- `database/`: Datenbankschema
- `storage/tmp/`: temporäre Mail-Anhänge

## Manuell noch zu erledigen

- `config/config.php` mit echten Zugangsdaten anlegen
- Datenbank importieren
- Ersten Admin erzeugen
- Optional Composer samt PHPMailer installieren
- DSGVO-Text im Layout ersetzen


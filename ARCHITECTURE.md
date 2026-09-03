# Architekturüberblick

## Weiterarbeit

- Bestehende Mechanismen erweitern, nicht parallel duplizieren.
- Vor Änderungen die betroffenen Stellen lesen; Seiteneffekte prüfen.
- Versionierung nachvollziehbar weiterführen (`system_version()` in
  `src/Support/helpers.php`), pro Arbeitseinheit ein GitHub-Release.
- Dokumentieren, was geändert wurde und was offen bleibt (`TODO.md`).
- Zentrale Dateien: `public/index.php`, `src/Support/helpers.php`,
  `src/Repositories/SettingRepository.php`, `src/Controllers/*`,
  `src/Services/MailService.php`, `templates/layout/app.php`,
  `public/assets/css/app.css`, `themes/`.

## Grundaufbau

Die Anwendung ist als kleine SSR-PHP-App ohne Framework aufgebaut. `public/index.php` übernimmt Bootstrap, Dependency-Container und Routing. Controller bleiben schlank und delegieren Datenzugriffe an Repositories sowie Fachlogik an Services.

## Datenmodell

- `users`, `roles`: Accounts, Rollen und Aktivstatus
- `contacts`: Stammdaten je Person
- `contact_emails`, `contact_phones`: 1:n-Erweiterungen für mehrere Kontaktwege
- `categories`: einfache Gruppierung für Klasse, Orga-Team oder ähnliche Cluster
- `contact_groups`, `contact_group_members`: frei definierbare Personengruppen
  quer zu Kategorie/Tag (Mitgliedschaft am Kontakt, `is_open` = Selbst-Beitritt,
  `mail_locked` = Notbremse für die Gruppen-Mail)
- `group_mail_log`: ein Eintrag je Gruppen-Mail-Versand (weiche Tagesgrenze,
  Nachvollziehbarkeit)
- `password_resets`, `login_attempts`: Sicherheit für Reset und Brute-Force-Schutz
- `audit_log`, `mail_log`: Nachvollziehbarkeit von Änderungen und Versand

Die Kontakt-Kategorie ist in Version 1 absichtlich als einzelne Fremdschlüsselbeziehung modelliert, damit die Oberfläche einfach bleibt. Eine spätere Umstellung auf n:m ist möglich, ohne die übrige Struktur zu blockieren.

## Rollen und Rechte

Die zentrale Rechteprüfung sitzt in `src/Core/Auth.php` über eine kleine Berechtigungsmatrix. So lassen sich spätere Module wie Galerie oder Upload-Portal mit denselben Rollen ergänzen, ohne die Controller-Struktur neu zu denken.

## Mailing-Ansatz

Das Mailing nutzt einen zweistufigen Ablauf:

1. Entwurf und Anhänge werden entgegengenommen.
2. Der eigentliche Versand läuft in kleinen Batches über wiederholte Requests.

Das reduziert Timeout-Risiken auf Shared Hosting und macht eine Fortschrittsanzeige im Browser möglich. Wenn PHPMailer vorhanden ist, wird SMTP genutzt; andernfalls gibt es einen einfachen Fallback über `mail()`.

## Zeitgesteuerte Aufgaben

`EventScheduler` erledigt die Abstimmungs-Automatik (Fristen schließen,
48-Stunden-Erinnerung an Nicht-Abstimmende, Ergebnis-Mail nach dem Schließen).
Einstiegspunkt ist die Route `/intern/cron?key=…` (`CronController`), abgesichert
über `app.cron_key` mit `hash_equals`. Für selten besuchte Instanzen läuft in
`public/index.php` eine gedrosselte Rückfallebene (max. 1×/Stunde, über das
`app_settings`-Flag `scheduler_last_run`). Jeder Job ist idempotent und über
Zeitstempel-Spalten am Event gegen Doppelversand gesichert.

## White-Label und Upgrades

Zwei feste Regeln für alle künftigen Änderungen:

1. **White-Label zuerst.** Das Produkt (GRUEZE) wird vertrieben. Neue
   Defaults, Texte und Beispiele werden markenneutral gedacht; alles
   Instanzspezifische lebt in `config/config.php` (`branding.*`, `defaults.*`)
   oder in `app_settings` (Branding-Seite, Rechtstexte, Mail-Vorlagen).
   Keine Instanz-Namen, Personendaten oder festen URLs im Code.
   - Ausnahme: `system_label` / `product_url` – der **Produktname** GRUEZE ist
     kein Instanz-Branding und bleibt der Standard.

2. **Bestandsdaten dürfen bei einem Upload nie kaputtgehen.** Wenn ein
   Code-Default neutralisiert wird, sichert vorher eine Migration die alten
   Werte per `INSERT IGNORE` in `app_settings`. Neue Tabellen werden zusätzlich
   lazy per `CREATE TABLE IF NOT EXISTS` im Repository angelegt, damit ein
   Feature auch vor der Migration trägt. Umbenennungen (Slugs, Setting-Keys)
   bekommen für das Fenster zwischen Deploy und Migration einen Alias im Code
   und eine `UPDATE`-Migration, die den alten Wert mitzieht. Migrationen sind
   additiv und idempotent; `app_settings`-Werte, die schon existieren, bleiben
   unberührt.

## Sicherheitsentscheidungen

- PDO mit Prepared Statements für alle Datenbankzugriffe (`EMULATE_PREPARES` aus)
- CSRF-Schutz auf jedem zustandsändernden Endpunkt (`Csrf::validate`)
- Session-Härtung: `httponly`, `secure`, `SameSite=Strict`, Inaktivitäts-Timeout,
  `session_regenerate_id` bei Login/Rollenwechsel/Timeout, CSRF-Token frisch nach Login
- Content-Security-Policy (nonce-basiert, `csp_nonce()`) + `X-Frame-Options`,
  `nosniff`, `Referrer-Policy`, HSTS – gesetzt in `send_security_headers()`
  (`public/index.php`). Keine Inline-Event-Handler; Rückfragen über `data-confirm`.
- Login-Ratelimit je (E-Mail, IP) **und** je IP; konstante Antwortzeit
  (Dummy-`password_verify`) gegen Konten-Enumeration
- Passwort-Reset: Token als bcrypt-Hash, 1 Mail je Konto / 5 min,
  Audit-Eintrag, alte Tokens werden beim Zurücksetzen verbraucht
- Datei-Uploads: Inhalts-MIME-Prüfung, Zufallsnamen, `php_flag engine off` +
  `RemoveHandler` im Upload-Ordner; kein SVG-Logo; Backup-Restore nur mit
  Bild-Dateinamen-Allowlist
- Vom Admin eingegebenes HTML (Impressum/Datenschutz) läuft durch
  `sanitize_rich_html()` (DOM-Allowlist); Theme-Token-Werte durch
  `ThemeService::tokenValueIsValid()`, bevor sie ins Seiten-CSS wandern
- E-Mail-Adressen werden von Steuerzeichen befreit; `MailService::safeAddress()`
  weist Adressen mit Zeilenumbruch ab; IMAP-Verbindung mit Zertifikatsprüfung
- Aufbewahrung: `login_attempts`, abgelaufene `password_resets` und alte
  `event_token_hits` werden probabilistisch (`index.php`, GC) gelöscht;
  pseudonyme IP-Hashes über `source_hash()` mit optionalem
  `security.hash_pepper`
- sensible Konfiguration (`config.php`, `deploy.env`) außerhalb von Git und
  rsync-Deploy
- Vollständiger Audit-Bericht: `docs/SECURITY-AUDIT.md`

# Architekturüberblick

## Grundaufbau

Die Anwendung ist als kleine SSR-PHP-App ohne Framework aufgebaut. `public/index.php` übernimmt Bootstrap, Dependency-Container und Routing. Controller bleiben schlank und delegieren Datenzugriffe an Repositories sowie Fachlogik an Services.

## Datenmodell

- `users`, `roles`: Accounts, Rollen und Aktivstatus
- `contacts`: Stammdaten je Person
- `contact_emails`, `contact_phones`: 1:n-Erweiterungen für mehrere Kontaktwege
- `categories`: einfache Gruppierung für Klasse, Orga-Team oder ähnliche Cluster
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
   Werte per `INSERT IGNORE` in `app_settings` (Beispiel:
   `2026-09-01-white-label-seed`). Neue Tabellen werden zusätzlich lazy per
   `CREATE TABLE IF NOT EXISTS` im Repository angelegt, damit ein Feature auch
   vor der Migration trägt. Umbenennungen bekommen einen Alias im Code für das
   Fenster zwischen Deploy und Migration (Beispiel: alter Theme-Slug `signalfarbe`
   → `signalfarbe` in `ThemeService::activeSlug()`). Migrationen sind additiv
   und idempotent; `app_settings`-Werte, die schon existieren, bleiben unberührt.

## Sicherheitsentscheidungen

- PDO mit Prepared Statements für alle Datenbankzugriffe
- CSRF-Schutz für zustandsändernde Formulare
- Session-Härtung mit `httponly`, `secure`, `SameSite=Strict` und Inaktivitäts-Timeout
- Rate-Limit über `login_attempts`
- serverseitige Datei- und MIME-Prüfung für Fotos und Anhänge
- sensible Konfiguration bewusst außerhalb von Git durch `config.php`

# Sicherheit

## Sicherheitslücke melden

Wenn du in GRUEZE eine Sicherheitslücke findest, melde sie bitte **nicht** über
einen öffentlichen Issue, sondern über
[GitHub Security Advisories](https://github.com/thx-2000/grueze/security/advisories/new)
(privat).

Bitte gib an:

- betroffene Version (siehe Footer / `system_version()` in `src/Support/helpers.php`)
- eine Beschreibung, wie sich das Problem auslösen lässt
- die vermutete Auswirkung

Ich melde mich in der Regel innerhalb einer Woche.

## Betrieb absichern

- `config/config.php` und `scripts/deploy.env` gehören **nicht** ins Git und
  nicht in ein Backup-ZIP. Beide sind per `.gitignore` / `.rsyncignore`
  ausgeschlossen.
- `security.hash_pepper` in der `config.php` auf eine zufällige Zeichenkette
  setzen.
- Nur über HTTPS betreiben (`app.force_https` = `true`, `.htaccess` erzwingt den
  Redirect). Die App setzt bei HTTPS zusätzlich `Strict-Transport-Security`.
- Rechtstexte (Impressum, Datenschutz) vor dem Produktivbetrieb ausfüllen. Die
  Datenschutzerklärung sollte IP-Protokollierung (`login_attempts`),
  Änderungsprotokoll (`audit_log`) und Versandprotokoll (`mail_log`) benennen.
- Backups liegen unverschlüsselt vor und enthalten Passwort-Hashes und alle
  personenbezogenen Daten – sicher aufbewahren.
- Regelmäßig **Verwaltung → Aktualisieren** ausführen, damit Sicherheits-
  Migrationen eingespielt werden.

Eine ausführliche Prüfung des Stands 0.43.0 liegt in
[`docs/SECURITY-AUDIT.md`](docs/SECURITY-AUDIT.md) – alle dort genannten Punkte
sind in Version 1.0.0 behoben oder als bewusste Abwägung dokumentiert.

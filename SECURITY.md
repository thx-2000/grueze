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
- **Verschlüsselung „at rest":** Die Mailserver-Passwörter werden mit einem
  Schlüssel verschlüsselt gespeichert. Ohne `security.secret_key` erzeugt die
  App beim ersten Start automatisch `storage/app.key` (Rechte `0600`, per
  `.gitignore`/`.rsyncignore` vom Deploy und von Backups ausgenommen). Diese
  Datei mitsichern (getrennt von den DB-Backups) – ohne sie sind die
  verschlüsselten Werte nach einem Serverwechsel verloren und müssen neu
  eingegeben werden. Bei mehreren App-Servern an einer DB stattdessen einen
  festen `security.secret_key` in allen `config.php` setzen.
- Nur über HTTPS betreiben (`app.force_https` = `true`, `.htaccess` erzwingt den
  Redirect). Die App setzt bei HTTPS zusätzlich `Strict-Transport-Security`.
- **IP-Adressen:** Login-Versuche (`login_attempts`) werden immer nur
  pseudonym (gehasht) gespeichert – nur fürs Rate-Limit, nirgends angezeigt.
  Die IP angemeldeter Sitzungen (Verwaltung → Anmeldungen) wird nur bei
  `security.store_ip = true` gespeichert und angezeigt; Standard ist `false`.
  Aufbewahrung über `security.session_retention_days` (Standard 90) bzw.
  `security.login_attempts_retention_days` (Standard 30).
- Rechtstexte (Impressum, Datenschutz) vor dem Produktivbetrieb ausfüllen. Die
  Datenschutzerklärung sollte das Änderungsprotokoll (`audit_log`), das
  Versandprotokoll (`mail_log`) und – falls `security.store_ip` aktiv ist – die
  IP-Speicherung angemeldeter Sitzungen benennen.
- Backups enthalten Passwort-Hashes und alle personenbezogenen Daten. Beim
  Export lässt sich ein Passwort vergeben (ZIP dann AES-256-verschlüsselt) –
  empfohlen, wenn das Backup den Server verlässt. Sicher aufbewahren.
- Regelmäßig **Verwaltung → Aktualisieren** ausführen, damit Sicherheits-
  Migrationen eingespielt werden.

Eine ausführliche Prüfung des Stands 0.43.0 liegt in
[`docs/SECURITY-AUDIT.md`](docs/SECURITY-AUDIT.md) – alle dort genannten Punkte
sind in Version 1.0.0 behoben oder als bewusste Abwägung dokumentiert.

# Security-Audit GRUEZE

> **Umsetzung:** Alle unten aufgeführten Befunde sind in **Version 1.0.0**
> behoben. Zusammenfassung der Änderungen am Ende des Dokuments
> („## Umsetzung in 1.0.0"). Der Befundtext darunter beschreibt den geprüften
> Stand 0.43.0.

- **Stand:** 2026-09-02, Code-Version 0.43.0 (Commit `5b82be4`)
- **Umfang:** vollständiger Code-Durchgang – Auth/Session/Passkeys, CSRF,
  Rechte-/Rollenprüfung an jedem Endpunkt, SQL-/Template-Injection,
  Datei-Uploads (XLSX-Import, Logo, Anhänge), Passwort-Reset,
  Selbst-Registrierung, Mailversand & Mailserver-Zugangsdaten, Backup/Restore,
  Deploy-Hygiene.
- **Methodik:** manuelle Quelltext-Analyse. Kein Pentest gegen eine laufende
  Instanz, kein Abhängigkeits-Scan (die App hat keine PHP-Abhängigkeiten außer
  optional PHPMailer).

Bewertung: **hoch** = vor dem Public-Release / vor Produktivbetrieb beheben ·
**mittel** = zeitnah beheben · **niedrig** = Härtung / bei Gelegenheit.
Fast alle Befunde setzen ein Konto voraus; „nur Admin" heißt: nur mit der
Berechtigung `users.manage` bzw. `settings.manage` ausnutzbar.

---

## Zusammenfassung

| # | Titel | Schwere | Voraussetzung |
|---|---|---|---|
| H1 | Backup-Restore: bösartige ZIP → Datei-Schreibzugriff in `public/`, mögliche Code-Ausführung | **hoch** | Admin + manipulierte Backup-Datei |
| H2 | Backup-Restore: SQL-Injection über Spaltennamen aus der Backup-JSON | **hoch** | Admin + manipulierte Backup-Datei |
| M1 | Kein CSRF-Schutz bei „Impressum/Datenschutz speichern" | mittel | – (SameSite=Strict mildert stark) |
| M2 | Stored XSS über den Theme-Editor (Token-Werte ungefiltert ins `<style>`) | mittel | Admin |
| M3 | Stored XSS in der Termin-Detailseite (Teilnehmername in `<textarea>`) | mittel | Mitglied (eigener Name) |
| M4 | E-Mail-Header-Injection im `mail()`-Fallback (Kontakt-Mailadressen ungeprüft) | mittel | Mitglied / Team, nur ohne PHPMailer |
| M5 | SMTP-/IMAP-Passwörter im Klartext in der Datenbank und im Backup-ZIP | mittel | DB- oder Backup-Zugriff |
| M6 | IMAP-„Kopie in Gesendet" ohne Zertifikatsprüfung (`novalidate-cert`) | mittel | Netzwerk-MITM |
| M7 | Fehlende Sicherheits-HTTP-Header (CSP, X-Frame-Options, nosniff, HSTS …) | mittel | – |
| M8 | CSV-Export: Formel-Injection (Excel) | mittel | Mitglied (eigener Name) |
| M9 | Logo-Upload erlaubt SVG → Stored XSS beim direkten Öffnen | mittel | Admin |
| M10 | XLSX-Import: keine Größenbegrenzung → DoS; XML-Parser nicht gehärtet | mittel | Team |
| L1 | `registration_default_role` serverseitig nicht auf „nicht Admin" geprüft | niedrig | Admin |
| L2 | Login-Ratelimit schwach (nur je E-Mail + IP) | niedrig | – |
| L3 | Passwort-Reset: Enumeration über Antwortzeit, kein Ratelimit, kein Audit | niedrig | – |
| L4 | Massenversand über `POST /mail/start` bei ungewöhnlicher Rechte-Kombi | niedrig | Rolle mit `contacts.manage` + `mail.contact_single`, ohne `mail.send` |
| L5 | Aktionen während „Als Benutzer anmelden" werden dem Ziel zugeschrieben | niedrig | Admin |
| L6 | `applyOne()` akzeptiert jede vorhandene Migration, nicht nur offene | niedrig | Admin |
| L7 | IP-Adressen/-Hashes ohne Aufbewahrungsgrenze; Datenschutz-Doku nötig | niedrig | – |
| L8 | Reset-Token + Mailadresse im URL-Query (landet in Logs/History) | niedrig | erledigt 1.20.0 (Pfad-Segment) |
| L9 | `Auth::user()` nicht gecacht → viele DB-Abfragen pro Seite | niedrig | – |
| L10 | Login-Timing verrät, ob eine Mailadresse existiert | niedrig | – |
| L11 | Deploy spielt weiterhin `docker-compose.yml` u. a. Dev-Dateien auf den Webspace | niedrig | – |
| L12 | Kontaktliste: vollständige Datensätze gehen an die View, Filter nur im Template | niedrig | – |
| B1 | Bug: „Impressum/Datenschutz speichern" endet in einem 500 (Speichern klappt trotzdem) | – (Fehler) | Admin |

**Positiv** (siehe unten): durchgehend Prepared Statements, CSRF überall außer M1,
gehärtete Sessions, bcrypt + 12-Zeichen-Minimum, saubere Standard-Rechte
(Admin-only per Default), Uploads mit Inhalts-MIME-Prüfung + `engine off`,
keine Secrets im Repo, brauchbare WebAuthn-Prüfungen, Ersteinrichtung
serverseitig abgesichert.

---

## Hoch

### H1 – Backup-Restore: Datei-Schreibzugriff in `public/`, mögliche Code-Ausführung

`BackupService::restoreUploads()` (und `restoreMergePhoto()`) schreiben Dateien
aus dem hochgeladenen Backup-ZIP nach `public/assets/uploads/`. Geprüft wird nur
`basename()` und ein Verbot von `..` – **nicht** die Dateiendung.

- Ein ZIP mit `uploads/.htaccess` überschreibt die dortige Schutzdatei
  (`php_flag engine off` / `RemoveHandler`), ein weiteres `uploads/shell.php`
  im selben ZIP landet daneben. Auf einem Host, der `.htaccess` auswertet, ist
  die neue `.htaccess` scharf → **Remote Code Execution**. Auf `AllowOverride
  None` (kein `.htaccess`) ist schon der reine `.php`-Upload das Problem.
- `restoreUploads()` löscht vorher alle Nicht-Dotfiles im Ordner – die
  Original-`.htaccess` überlebt zwar, wird aber durch das ZIP-Pendant
  überschrieben.
- Auslöser: Berechtigung `users.manage` **und** eine vom Angreifer gelieferte
  Backup-Datei (z. B. „stell das mal wieder her"). „Zusammenführen" ist der
  Standard-Modus und hat kein Bestätigungswort.

**Empfehlung:** beim Restore nur Dateinamen akzeptieren, die dem eigenen
Schema entsprechen (`^(brand_)?[0-9a-f]{24,32}\.(jpg|png|webp|svg)$`),
mindestens aber `.htaccess`, `.php`/`.phtml` und alles ohne Bild-Endung
ablehnen und nie eine bestehende `.htaccess` überschreiben. SVG siehe M9.

### H2 – Backup-Restore: SQL-Injection über Spaltennamen

`BackupService::importTable()` baut das INSERT so:

```php
$columns = array_keys($row);                       // aus der Backup-JSON
$columnList = '`' . implode('`, `', $columns) . '`';
```

Die Spaltennamen stammen unkontrolliert aus `database.json`. Ein Backtick im
Spaltennamen bricht aus dem Bezeichner aus → SQL-Injection (der Tabellenname
ist über `tableExists()` abgesichert, die Spaltennamen nicht).

**Empfehlung:** Spaltennamen gegen `SHOW COLUMNS FROM <table>` prüfen (nur
bekannte Spalten zulassen) **oder** Backticks verdoppeln
(`str_replace('`', '``', $column)`). Gleiche Bedrohungslage wie H1
(Admin + manipuliertes Backup), aber eine echte Injection.

---

## Mittel

### M1 – Kein CSRF-Schutz bei „Impressum/Datenschutz speichern"

`LegalController::updateLegal()` (Routen `POST /admin/legal/impressum` und
`/admin/legal/datenschutz`) prüft `requirePermission('users.manage')`, **ruft
aber `Csrf::validate()` nicht auf** – als einziger zustandsändernde Endpunkt.
Der gespeicherte Text wird auf `/impressum` bzw. `/datenschutz` **roh** als
HTML ausgegeben (`<?= $content ?>`), also ein Stored-XSS-Ziel, das für alle
Besucher:innen (auch nicht angemeldete) sichtbar ist.

`SameSite=Strict` auf dem Session-Cookie verhindert, dass ein fremder
Tab/Formular den Login mitschickt – die praktische Ausnutzbarkeit ist dadurch
gering, aber der Schutz gehört trotzdem hin.

**Empfehlung:** `Csrf::validate($request->input('_csrf'))` ergänzen. Zusätzlich
den Rechtstext beim Rendern durch einen kleinen HTML-Allowlist-Filter schicken
(erlaubte Tags: `p, br, strong, em, ul, ol, li, a[href], h2, h3`), damit auch
ein Admin-Konto nicht zum XSS-Vektor wird.

### M2 – Stored XSS über den Theme-Editor

Theme-Token-Werte (`ThemeController::save` → `ThemeService::normalizeTokens`)
werden **nur getrimmt**, nicht validiert, und in `helpers.php` zu CSS
zusammengesetzt und im Layout roh ausgegeben:

```php
// helpers.php branding_theme_style()
$declarations[] = sprintf('%s: %s;', $property, $value);
// templates/layout/app.php
<style><?= $themeStyle ?></style>
```

Ein Admin, der einen Token-Wert auf `red}</style><script>…</script>` setzt,
bekommt persistentes JavaScript in jeder Seite für jede:n Nutzer:in (das aktive
Theme wird überall eingebunden).

**Empfehlung:** Token-Werte beim Speichern gegen enge Muster prüfen –
Farben `#hex` / `rgb[a]()` / `hsl[a]()` / CSS-Farbnamen, Schriften
`[A-Za-z0-9 ,"'-]+`, Radien `[0-9.a-z%]+`. Werte mit `;{}<>` verwerfen oder
auf den Standard zurücksetzen.

### M3 – Stored XSS in der Termin-Detailseite

`templates/events/detail.php:292` gibt Teilnehmernamen **ohne `e()`** in einem
`<textarea>` aus:

```php
<textarea id="allVoteLinks" …><?php foreach ($participants as $p): ?><?= trim($p['vorname'] . ' ' . $p['nachname']) ?>: …
```

Ein Mitglied kann über „Mein Eintrag" den eigenen Namen auf
`</textarea><script>…</script>` setzen; öffnet das Orga-Team danach die
Termin-Detailseite, läuft das Skript in dessen Sitzung.

**Empfehlung:** `<?= e(trim($p['vorname'] . ' ' . $p['nachname'])) ?>`.

### M4 – E-Mail-Header-Injection im `mail()`-Fallback

Ohne PHPMailer (kein Composer) versendet `MailService::sendRaw()` über
`mail($to, …, implode("\r\n", $headers))`. `$to` ist die erste Kontakt-Mailadresse
und wird **nirgends** auf Format geprüft – `Validator` prüft nur Login-Mailadressen,
`ContactController::cleanEmail()` entfernt nur `mailto:` und trimmt. Eine
Kontaktadresse mit CRLF (`opfer@x.de\r\nBcc: …`) schleust Header ein.
Ein Mitglied kann das über die eigene Adresse in „Mein Eintrag" auslösen; die
Injection greift, sobald ein Admin eine Rundmail über den `mail()`-Weg schickt.

Der PHPMailer-Weg ist sauber (validiert Adressen, wirft bei CRLF). Betroffen
sind also v. a. Shared-Hosting-Installationen ohne Composer.

**Empfehlung:** Kontakt- und Login-Mailadressen beim Speichern strikt
validieren (`filter_var(..., FILTER_VALIDATE_EMAIL)` **und** CR/LF ablehnen),
zusätzlich in `sendRaw()` Adress- und `Reply-To`-Werte gegen `\r\n` absichern.

### M5 – Mailserver-Zugangsdaten im Klartext

`mail_smtp_password` / `mail_imap_password` liegen unverschlüsselt in
`app_settings`. Damit:

- Jeder DB-Lesezugriff (SQL-Injection anderswo, DB-Dump, Shared-DB-Kompromiss)
  liefert die Postfach-Zugangsdaten.
- Das Backup-ZIP (`app_settings` als Klartext-JSON, ZIP unverschlüsselt, vom
  Admin heruntergeladen) enthält sie ebenfalls.
- Das Formular „Mail-Einstellungen" setzt den aktuellen Wert vermutlich ins
  `value`-Attribut des Passwortfeldes (im HTML-Quelltext lesbar) – prüfen.

Verschlüsselung „at rest" bringt auf Shared Hosting nur begrenzt etwas (der
Schlüssel läge neben den Daten in `config.php`), hebt die Latte gegen reine
DB-Kompromisse aber deutlich.

**Empfehlung, gestaffelt:** (a) im Backup die Mailpasswörter weglassen oder das
Backup optional passwortgeschützt/verschlüsselt anbieten; (b) im Formular ein
Platzhalter-Feld („•••• unverändert") statt des Klartextwerts; (c) optional
`sodium_crypto_secretbox` mit einem Schlüssel aus `config.php`.

### M6 – IMAP-Archivierung ohne Zertifikatsprüfung

`MailService::imapMailboxString()` hängt `novalidate-cert` an, und
`appendWithImapSocket()` öffnet `ssl://…` per `stream_socket_client()` ohne
`verify_peer`-Kontext. Ein Angreifer zwischen App-Server und IMAP-Server kann
die IMAP-Zugangsdaten (`a2 LOGIN …`) und jede archivierte Mail mitlesen.

**Empfehlung:** `novalidate-cert` entfernen (bzw. `validate-cert`), beim Socket
einen SSL-Kontext mit `verify_peer => true`, `verify_peer_name => true`,
`SNI_enabled => true` setzen. Als Übergang konfigurierbar machen, Standard =
prüfen.

### M7 – Fehlende Sicherheits-HTTP-Header

`public/.htaccess` setzt nur `X-Robots-Tag`. Es fehlen:

- `Content-Security-Policy` (mindestens `default-src 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'` – die App lädt keine externen Skripte). Eine CSP entschärft M1–M3 und M9 erheblich.
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY` (Clickjacking; ergänzt `frame-ancestors`)
- `Referrer-Policy: same-origin` (schützt u. a. den Reset-Link, L8)
- `Strict-Transport-Security: max-age=31536000` (nur wenn dauerhaft HTTPS)

**Empfehlung:** per `<IfModule mod_headers.c>` in `public/.htaccess` ergänzen.
Bei striktem CSP das eine Inline-`<style>` (Theme) und die zwei kleinen
Inline-`<script>` (Blickschutz-Preload, Register-Formular) über `'nonce-…'`
oder Auslagerung abdecken.

### M8 – CSV-Export: Formel-Injection

`CsvExportService::stream()` schreibt Kontaktfelder (Name, Notizen, Adresse,
Tags …) unverändert. Beginnt eine Zelle mit `= + - @` oder Tab/CR, führt Excel
sie als Formel aus. Ein Mitglied kann den eigenen Namen entsprechend setzen,
ein Team-Mitglied exportiert und öffnet.

**Empfehlung:** Zellen, die mit `= + - @ \t \r` beginnen, beim Schreiben mit
einem `'` (oder führenden Leerzeichen) neutralisieren.

### M9 – Logo-Upload erlaubt SVG

`UploadService::storeBrandAsset()` akzeptiert `image/svg+xml`. Als `<img src>`
im Layout ist eine SVG harmlos, beim direkten Aufruf von
`/assets/uploads/brand_<hex>.svg` rendert der Browser sie als Dokument und
führt enthaltenes `<script>` aus (XSS auf der App-Origin, gleicher Kontext wie
die App). Nur mit `users.manage` auslösbar, aber persistent.

**Empfehlung:** SVG entweder ganz verbieten (PNG/JPG/WEBP reichen fürs Logo)
oder beim Upload serverseitig bereinigen (Skripte/`<foreignObject>`/`on*`
entfernen) und Uploads generell mit `Content-Disposition: attachment` bzw.
`Content-Security-Policy: default-src 'none'; sandbox` ausliefern.

### M10 – XLSX-Import: DoS + XML-Parser

- `ContactController::importXlsx()` prüft nur die Endung `.xlsx`, **keine
  Dateigröße** (anders als Foto-/Anhang-Upload). Ein kleines ZIP mit einer
  stark komprimierten `sheet1.xml` (Dekompressions-Bombe) lässt
  `DOMDocument::loadXML()` den Worker-Speicher sprengen.
- `XlsxReader::xpath()` ruft `loadXML()` ohne Optionen. Auf PHP 8.2 / libxml
  ≥ 2.9 ist klassisches XXE per Default aus – trotzdem sollte der Parser
  explizit gehärtet werden (`LIBXML_NONET`, kein `LIBXML_NOENT`, ggf.
  `libxml_set_external_entity_loader(fn () => null)`).

**Empfehlung:** Upload-Größe begrenzen (z. B. 5 MB), zusätzlich die
entpackte XML-Größe deckeln, `loadXML($xml, LIBXML_NONET)` verwenden.

---

## Niedrig / Härtung

### L1 – `registration_default_role` nicht validiert
`RegistrationController::updateSettings()` speichert `default_role` ungeprüft
(`trim(...) ?: 'stufenmitglied'`). Das Auswahlfeld blendet `admin` aus, der
POST-Handler erzwingt das nicht → ein Admin kann per direktem POST
`default_role=admin` setzen; danach erzeugt jede Selbst-Registrierung /
Einladung einen Admin. **Empfehlung:** serverseitig gegen die Rollenliste ohne
`admin` prüfen.

### L2 – Schwaches Login-Ratelimit
`LogRepository::recentFailedAttempts()` zählt nur Fehlversuche mit **gleicher
E-Mail und gleicher IP**. Verteilte Angriffe (IP-Rotation) bekommen pro IP
5 Versuche je Konto; ein Angreifer von einer IP kann 5 × (beliebig viele Konten)
Passwörter testen. Kein konto- oder IP-weiter Deckel.
**Empfehlung:** zusätzlich pro IP drosseln (z. B. 20 Fehlversuche / 10 min) und
optional einen globalen Backoff.

### L3 – Passwort-Reset
- **Enumeration über Timing:** bekannte Adresse → DB-Insert + synchroner
  SMTP-Versand, unbekannte → sofort zurück. Antwortzeit verrät die Existenz.
- **Kein Ratelimit** auf `POST /forgot-password` und `POST /reset-password`.
- **Kein Audit-Eintrag** beim Self-Service-Reset (der Admin-ausgelöste Reset
  wird protokolliert).
- Ein erfolgreicher Reset macht andere offene Reset-Tokens desselben Kontos
  nicht ungültig und meldet andere Sitzungen nicht ab.
**Empfehlung:** Ratelimit ergänzen, Versand in eine Warteschlange/asynchron
oder mit konstanter Mindestlaufzeit, Audit-Eintrag schreiben, beim Reset alle
`password_resets` des Kontos auf `used` setzen und `session`/Passkey-Status
unangetastet lassen ist ok, aber Session-Regeneration erzwingen.

### L4 – Massenversand über `POST /mail/start`
`start()` und `test()` prüfen `requireMailAccess()` (= `mail.send` **oder**
`mail.contact_single`). Der „nur eine Person"-Riegel greift nur, wenn
`isMemberContactMode()` wahr ist – und das ist **falsch**, sobald jemand
`contacts.manage` hat. Eine (untypische) Rolle mit `contacts.manage` +
`mail.contact_single`, aber ohne `mail.send`, kann so per direktem POST mit
`recipient_mode=all` ein Massen-Mailing auslösen. Standardrollen sind nicht
betroffen. **Empfehlung:** für jeden `recipient_mode` außer „genau eine Person"
explizit `mail.send` verlangen.

### L5 – Zuordnung während „Als Benutzer anmelden"
Aktionen im Impersonation-Modus laufen unter `Auth::user()` (dem Zielkonto),
z. B. schreibt `ContactController::update()` `updated_by = Zielkonto`. Start und
Ende der Impersonation werden zwar unter dem Original-Admin protokolliert, die
Einzelaktionen dazwischen nicht. **Empfehlung:** bei aktiver Impersonation im
Audit zusätzlich den Original-Benutzer vermerken.

### L6 – `MigrationService::applyOne()`
Akzeptiert jeden Namen, der zu einer vorhandenen `.sql`-Datei passt – auch
bereits angewendete. Bei einer nicht idempotenten Migration kann das Schaden
anrichten (nur Admin). **Empfehlung:** nur Migrationen aus `pending()` zulassen.

### L7 – IP-Daten / Aufbewahrung / Datenschutz
`login_attempts.ip_address` (Klartext-IP), `audit_log` (alte Feldwerte =
personenbezogen, unbegrenzt), `mail_log` (Empfänger + Betreff, unbegrenzt),
`registration_invites.ip_hash` und `event_token_hits.source_hash`
(= `sha256(IP)` mit festem Pepper im Code → für IPv4 trivial zurückrechenbar,
also faktisch die IP). Nichts davon wird automatisch gelöscht.
**Empfehlung:** Aufbewahrungsfristen definieren und per Cron/Update-Schritt
alte Zeilen löschen (`login_attempts` z. B. 30 Tage, `event_token_hits`
nach Terminabschluss). In der **Datenschutzerklärung** IP-Protokollierung,
Audit-Log und Versandprotokoll benennen. Für die Hashes einen zufälligen,
außerhalb des Codes liegenden Pepper verwenden (in `config.php`).

### L8 – Reset-Link mit Token + E-Mail im Query-String
`/reset-password?token=…&email=…` landet in Zugriffslogs, Browser-History und
(ohne `Referrer-Policy`, siehe M7) im Referrer. **Empfehlung:** Token im
Pfad-Segment führen und die E-Mail serverseitig aus dem Token auflösen; sonst
zumindest `Referrer-Policy` setzen.

### L9 – `Auth::user()` nicht gecacht
Jeder Aufruf (dutzende pro Seite über `can()` im Layout/Templates) macht eine
`findById()`-Abfrage. Kein Sicherheitsloch, aber unnötige Last und ein
Verstärker für DoS. **Empfehlung:** das User-Array pro Request memoisieren.

### L10 – Login-Timing / Benutzer-Enumeration
`Auth::attempt()` kehrt bei unbekannter Adresse ohne `password_verify()` zurück –
messbar schneller. **Empfehlung:** immer einen Dummy-`password_verify()` gegen
einen festen Hash rechnen.

### L11 – Deploy-Hygiene
`.rsyncignore` schließt seit 0.42 `docs/`, `docker/` und die reinen
Doku-`*.md` aus. Weiterhin auf den Webspace kopiert: `docker-compose.yml`
(enthält die **lokalen** Dev-DB-Passwörter), `.dockerignore`,
`scripts/docker-*.sh`, `scripts/deploy.sh`. Alles liegt außerhalb von
`public/` (nicht per HTTP abrufbar) und die Passwörter sind Dev-only, sauber ist
es trotzdem nicht. **Empfehlung:** `docker-compose.yml`, `.dockerignore`,
`scripts/docker-*.sh`, `scripts/deploy*.sh` in `.rsyncignore` aufnehmen.
`config/config.example.php` muss bleiben (Fallback in `Config::load`).

### L12 – Kontaktliste: Filter nur im Template
`ContactController::index()` reicht die voll hydratisierten Kontakte
(inkl. E-Mails/Telefonnummern) an die View; welche Felder eine Rolle sieht,
entscheidet erst das Template über `can_view_contact_field()`. Aktuell korrekt
umgesetzt (Spalten kommen nur bei Berechtigung ins DOM), aber ein künftiger
Template-Fehler leakt PII. **Empfehlung:** die nicht sichtbaren Felder schon im
Controller entfernen, bevor das Array in die View geht.

### Weitere Kleinigkeiten
- CSRF-Token rotiert nicht beim Login (SameSite=Strict trägt den Schutz).
- `Session::enforceTimeout()` startet nach Ablauf ohne `session_regenerate_id()`.
- `UserController::updateOwnPassword()` meldet andere Sitzungen nach der
  Passwortänderung nicht ab.
- „Backup zurückspielen (Alles ersetzen)" überschreibt `users` inkl.
  Passwort-Hashes – gewollt, aber ein altes/fremdes Backup = kompletter
  Konten-Übernahme; „Zusammenführen" (Standardmodus) hat kein Bestätigungswort.

---

## Bug (bei der Prüfung gefunden)

### B1 – `redirect()` ist nicht definiert
`LegalController::updateLegal()` ruft am Ende `redirect(url('/admin/legal/' . $page))`
auf. Eine Funktion `redirect()` gibt es nicht (nur `App\Support\Redirect::to()`).
Ergebnis: nach jedem Speichern von Impressum/Datenschutz ein 500 – der Wert
**wird aber vorher gespeichert**. **Fix:** `Redirect::to('/admin/legal/' . $page)`.

---

## Was gut ist

- **SQL:** durchgehend PDO-Prepared-Statements, `PDO::ATTR_EMULATE_PREPARES => false`.
  Dynamische Sortier-/Filterteile sind Whitelists (`match`). Keine Injection in
  den normalen Request-Pfaden (Ausnahme H2, nur über ein manipuliertes Backup).
- **CSRF:** auf jedem zustandsändernden Endpunkt `Csrf::validate()` – einzige
  Lücke ist M1. Token per `random_bytes(32)`, Vergleich mit `hash_equals()`.
- **Sessions:** `HttpOnly`, `SameSite=Strict`, `Secure` (config-gesteuert),
  30-min-Inaktivitäts-Timeout, `session_regenerate_id(true)` bei Login und
  Rollenwechsel/Impersonation.
- **Passwörter:** `password_hash()`/`password_verify()` (bcrypt), Minimum
  12 Zeichen überall erzwungen. Reset- und Einladungs-Token als
  `bin2hex(random_bytes(32))`, in der DB nur als bcrypt-Hash.
- **WebAuthn** (Eigenimplementierung): prüft Challenge (einmalig, aus der
  Session), `type`, `origin`, `crossOrigin`, `rpIdHash` (`hash_equals`),
  UP-/UV-Flags (`userVerification: required`), Signatur (`openssl_verify`) und
  den Signaturzähler (Regression → Ablehnung).
- **Rechte:** `permissionDefaults()` gibt `users.manage`, `audit.view`,
  `contacts.export` per Default nur an Admin (leere Liste + Admin-Sonderfall).
  Jede `/admin`-, `/settings`-, `/users`-Route ist mit `requirePermission()`
  abgesichert. Rollen-interner Name ist auf `[a-z0-9-]` beschränkt (keine
  CSV-Verwirrung in der Rechte-Matrix).
- **Uploads:** Inhalts-MIME-Prüfung (`mime_content_type`), Zufallsdateinamen,
  `php_flag engine off` + `RemoveHandler` in `public/assets/uploads/` und
  `storage/`, Mail-Anhänge außerhalb des Webroots, `storage/backups/` mit
  `Require all denied`.
- **Ersteinrichtung:** `POST /setup/admin` prüft `adminExists()` serverseitig
  vor jeder Aktion – nach dem ersten Admin tot.
- **Secrets:** weder `config/config.php` noch `scripts/deploy.env` sind im Git
  oder im rsync-Deploy. Kein `.env`, keine Keys im Repo. History wurde in
  v0.15.0 mit `git filter-repo` bereinigt (vor dem Public-Release nochmal
  `git log -p | grep -iE 'passwort|password|secret|smtp'` gegenprüfen und alte
  Branches/Tags auf GitHub kontrollieren).
- **Sichtbarkeit:** `noindex` im Layout + `X-Robots-Tag` + `robots.txt
  Disallow: /`. Fehlerseiten (`render_error_page`) escapen und geben nur bei
  `app.debug = true` Details aus (Default aus).

---

## Empfohlene Reihenfolge

1. **H1, H2** – Restore härten (Dateinamen-Allowlist, Backticks/Spalten-Whitelist).
2. **B1** – `redirect()` → `Redirect::to()` (Einzeiler).
3. **M1** – CSRF bei den Rechtstexten; **M3** – ein `e()`; **M7** – Header-Block;
   **M8** – CSV-Zellen neutralisieren; **M9** – SVG verbieten. Alles kleine, klar
   abgegrenzte Änderungen.
4. **M2** – Theme-Token-Validierung; **M4** – Mailadress-Validierung + `sendRaw()`
   härten; **M10** – Upload-Limit + `LIBXML_NONET`.
5. **M5, M6** – Mail-Zugangsdaten (Backup ohne Passwörter / Platzhalter im
   Formular; `novalidate-cert` weg).
6. **L1, L4, L6** – kleine serverseitige Prüfungen. **L2, L3** – Ratelimits.
7. **L7** – Aufbewahrungsfristen + Datenschutzerklärung. **L11** –
   `.rsyncignore`. Rest nach Gelegenheit.

---

## Umsetzung in 1.0.0

| # | Umsetzung |
|---|---|
| H1 | `BackupService::isSafeUploadName()` – beim Restore und beim Merge-Foto nur `^[A-Za-z0-9_-]+\.(jpe?g\|png\|gif\|webp)$`, kein `.htaccess`/`.php`/SVG, keine zweite Endung. |
| H2 | `importTable()` filtert Spalten gegen `information_schema.columns` (`columnsOf()`) und verdoppelt Backticks in Tabellen-/Spaltennamen. |
| M1 | `LegalController::updateLegal()` ruft `Csrf::validate()`; `sanitize_rich_html()` (DOM-Allowlist) beim Speichern **und** beim Rendern von Impressum/Datenschutz. |
| M2 | `ThemeService::tokenValueIsValid()` – Farben/Längen/Fonts gegen enge Muster, `;{}<>`/`url()`/`@import` verworfen → Fallback auf Standard. |
| M3 | `events/detail.php` – `e()` um Teilnehmername + Vote-Link im `allVoteLinks`-Textarea. |
| M4 | `cleanEmail()`/Telefon-Bereinigung entfernt alle Steuerzeichen (inkl. CR/LF); `MailService::safeAddress()` weist Adressen mit Zeilenumbruch ab. |
| M5 | Backup-Export lässt `mail_smtp_password`/`mail_imap_password` weg (`SECRET_SETTINGS`); Formular zeigt weiterhin keinen Klartext. |
| M6 | IMAP: `validate-cert` statt `novalidate-cert`; Socket-Kontext mit `verify_peer`/`verify_peer_name`/SNI. |
| M7 | `send_security_headers()` in `public/index.php`: CSP (nonce-basiert, `csp_nonce()`), `X-Content-Type-Options`, `X-Frame-Options: DENY`, `Referrer-Policy: same-origin`, `COOP`, HSTS bei HTTPS. Inline-`onsubmit`/`onclick` → `data-confirm` + zentraler Handler in `app.js`. |
| M8 | `CsvExportService::safeCell()` stellt `=+-@`/Tab/CR-Zellen ein `'` voran. |
| M9 | Logo-Upload ohne SVG (`storeBrandAsset`), `accept`-Attribut angepasst. |
| M10 | Import-Größenlimit (`security.import_max_size`, 5 MB); `XlsxReader` `loadXML(…, LIBXML_NONET …)` + 40-MB-Deckel für entpacktes XML. |
| L1 | `registration_default_role` serverseitig gegen die Rollenliste ohne `admin` geprüft; `complete()` weist `admin` ebenfalls ab. |
| L2 | `recentFailedAttemptsByIp()` + `security.login_max_attempts_ip` (Standard 20/10 min). |
| L3 | Reset: Anti-Spam (1 Mail je Konto / 5 min, `password_resets.created_at` – Migration), Audit-Eintrag, alle offenen Tokens des Kontos werden verbraucht, Dummy-Hash für Unbekannte. |
| L4 | `MailController::guardMassSend()` – jeder Empfängerkreis außer „genau eine Person" braucht `mail.send`. |
| L5 | `LogRepository::addAudit()` hängt bei aktiver Impersonation `[… durch Konto #N]` an die Details. |
| L6 | `MigrationService::applyOne()` nur für Migrationen aus `pending()`. |
| L7 | Probabilistische GC in `index.php`: `pruneLoginAttempts` (30 T.), `pruneExpiredPasswordResets`, `pruneTokenHits` (120 T.), konfigurierbar. `source_hash()` mit optionalem `security.hash_pepper`. |
| L8 | **Erledigt in 1.20.0:** Reset-Link ist jetzt `/passwort-neu/<token>` (Pfad-Segment, keine E-Mail mehr im Link). Lookup über `password_resets.token_sha` (SHA-256-Index), Gültigkeit weiter per bcrypt-`token_hash`. Alt-Links (`/reset-password?token=…`) leiten per 302 auf die Pfad-Form um. `Referrer-Policy: same-origin` bleibt als zweite Schicht. |
| L9 | `Auth::user()` pro Request gecacht, Invalidierung bei Login/Logout/Impersonation. |
| L10 | `Auth::attempt()` rechnet auch für unbekannte Konten einen `password_verify()`. |
| L11 | `.rsyncignore` um `docker-compose.yml`, `.dockerignore`, `scripts/docker-*.sh` erweitert. |
| L12 | `ContactController::redactHiddenFields()` leert nicht sichtbare PII-Felder vor der View (Status-Chips bleiben korrekt, eigener Kontakt ausgenommen). |
| Kleinigkeiten | CSRF-Token wird beim Login verworfen; `Session::enforceTimeout()` mit `session_regenerate_id(true)`; `updateOwnPassword()` regeneriert die Session. |
| B1 | `redirect()` → `Redirect::to()`; `admin/legal-edit.php`-Markup + Button-Klassen repariert. |

**Neue Migration:** `2026-09-11-security-haertung.sql` (`password_resets.created_at`).

**Neue config-Schlüssel** (alle mit sinnvollem Default, in `config.example.php`):
`security.import_max_size`, `security.login_max_attempts_ip`,
`security.login_attempts_retention_days`, `security.token_hit_retention_days`,
`security.hash_pepper`.

**Nachgezogen in 1.20.0 („Batch 4"):**
- **Verschlüsselung „at rest"** für die Mailserver-Passwörter: `App\Support\Crypto`
  (libsodium `secretbox`), Schlüssel aus `config('security.secret_key')` oder
  automatisch erzeugter `storage/app.key` (0600, nicht im Deploy/Backup).
  `SettingRepository` ver-/entschlüsselt `mail_smtp_password`/`mail_imap_password`
  transparent; `reencryptSecrets()` zieht Altbestände beim ersten Lauf nach. Hebt
  die Latte gegen reine DB-Kompromisse deutlich.
- **Backup-ZIP optional AES-256-verschlüsselt**: Passwortfeld beim Export
  (`ZipArchive::setEncryptionName`/`EM_AES_256`), Passwortfeld beim
  Wiederherstellen mit klaren Fehlermeldungen.
- **L8** erledigt (siehe Tabelle oben).

**Bewusst nicht geändert:** Backup „Alles ersetzen" überschreibt weiterhin die
`users`-Tabelle (Kernfunktion, mit Bestätigungswort). Der `at-rest`-Schlüssel
liegt auf Shared Hosting weiterhin auf demselben Server wie die Daten – schützt
also gegen DB-Dumps/SQL-Injection und weitergegebene Backups, nicht gegen eine
vollständige Server-Übernahme. Echter Screenreader-Test steht weiterhin aus.


---
# Zweiter Durchgang – Stand v1.43.0 (2026-09)

- **Stand:** 2026-09, Code nach Release v1.43.0.
- **Anlass:** Seit dem ersten Audit (v1.0.0) sind viele Features dazugekommen –
  Termine/Abstimmungen, Gruppen inkl. Gruppen-Mail und -Abstimmung,
  Daten-Check-Link (Selbst-Service ohne Login), PWA, „at rest"-Verschlüsselung,
  dynamische Routen mit `{param}`, Kontakt-Zusammenführung, Anmelde-Übersicht +
  Sitzung-aus-der-Ferne-beenden, „Gesendete Nachrichten", „Erhaltene Mails",
  Kontaktfelder Beruf/Webseite, globale Suche über alle sichtbaren Felder.
- **Methodik:** manuelle Quelltext-Analyse, Blickwinkel „wie käme ich rein".
  Kein Pentest gegen eine laufende Instanz, kein Abhängigkeits-Scan.
- **Gesamtbild:** Die Grundlagen sind solide. Die schwerwiegenden Befunde aus
  v1.0.0 sind weiterhin geschlossen; die neuen Flächen sind überwiegend sauber
  gebaut (CSRF, parametrisierte Queries, WebAuthn korrekt verifiziert). Es gibt
  **keine gefundene Möglichkeit, ohne gültiges Konto oder gültigen Token
  einzudringen.** Die Befunde unten sind Härtung bzw. Missbrauchs-/DoS-Themen.

Bewertung: **mittel** = zeitnah · **niedrig** = bei Gelegenheit / Härtung.

## Zusammenfassung

| # | Titel | Schwere | Voraussetzung |
|---|---|---|---|
| A1 | Geheim-Tokens im Query-String (`/meine-daten?token=`, `/registrieren?token=`) statt im Pfad – Leak über Zugriffslogs, Verlauf, Referrer | **mittel** | – (nur Kenntnis/Abfangen des Links) |
| A2 | Daten-Check-Link ist nicht einmalig: `findValidByToken` ignoriert `used_at`, Link 30 Tage lang wiederverwendbar für PII-Schreibzugriff | **mittel** | gültiger Daten-Check-Token |
| A3 | `registration_invites`: Token-Suche macht bis zu 50× bcrypt pro Request, ohne Formatprüfung/Rate-Limit → CPU-DoS | **mittel** | – (unauthentifiziert) |
| A4 | Passwortwechsel/-Reset beendet andere Sitzungen nicht | **mittel** | kompromittierte Sitzung |
| A5 | `users.manage` an eine Nicht-Admin-Rolle vergeben = faktisch Admin-Gewalt über Admin-Konten (Passwort setzen, impersonieren, sperren, Admin anlegen) | **mittel** | Fehlkonfiguration durch Admin |
| B1 | Kein IP-unabhängiges Login-Lockout pro Konto → verteilter Brute-Force gegen bekannte Adresse möglich | niedrig | – |
| B2 | `/orga-team` ohne Rate-Limit – jede eingeloggte Person kann unbegrenzt Mail ans Orga-Team schicken | niedrig | Konto (auch Mitglied) |
| B3 | `sanitize_rich_html` lässt protokollrelative URLs (`//fremd.tld`) durch | niedrig | Admin (`users.manage`) |
| B4 | Backup-Restore: keine Größenbegrenzung für ZIP / `database.json`; `DELETE FROM` ohne Backtick-Escape des Tabellennamens | niedrig | Admin + große/bösartige Datei |
| B5 | `password_hash` liegt im View-Scope (`currentUser`), `UserRepository::search` lädt `users.*` in die Such-Ansicht | niedrig | – (latent, nirgends ausgegeben) |
| B6 | `events.ical_uid` aus `UUID()` (MariaDB v1, teils vorhersehbar) für den öffentlichen `.ics`-Link | niedrig | Erraten der UID |
| B7 | XLSX-Import: kein `is_uploaded_file()` (nur `error === UPLOAD_ERR_OK`) | niedrig | Admin |
| B8 | Daten-Check-`save` ohne Rate-Limit | niedrig | gültiger Token |

---

## Umsetzung in 1.45.0

Nach Rücksprache umgesetzt:

- **A1 – erledigt (Teil).** Daten-Check- und Einladungslink tragen den Token
  jetzt im Pfad (`/meine-daten/<token>`, `/registrieren/<token>`). Alt-Links
  im `?token=`-Format laufen über einen Rückfallpfad weiter. `page_title()`
  kennt die Pfadform. Die Gültigkeit/Mehrfach-Nutzung des Daten-Check-Links
  wurde bewusst **nicht** geändert (siehe A2).
- **A2 – bewusst offen gelassen.** Der Daten-Check-Link soll nach Wunsch
  30 Tage lang mehrfach nutzbar bleiben („jederzeit noch etwas ändern").
  Abgemildert durch die kurze `save`-Sperre (B8) und den Pfad-Token (A1).
- **A3 – erledigt.** `registration_invites.token_sha` (Index) + Formatprüfung;
  genau ein bcrypt-Vergleich pro Aufruf. Rückfallpfad für Alt-Einladungen ist
  auf 20 Zeilen begrenzt und drainiert mit deren Ablauf.
- **A4 – erledigt.** `updateOwnPassword`, Admin-`setPassword` und
  `PasswordResetService::reset` rufen `UserSessionRepository::revokeAllForUser`.
  Die eigene aktive Sitzung bleibt.
- **A5 – bewusst so gelassen.** `users.manage` bleibt eine bewusste
  Admin-Entscheidung; Hinweis in Doku/UI genügt.
- **B2 – erledigt.** Sitzungsbasiertes Limit (30 s Abstand, 6/Stunde).
- **B3 – erledigt.** Regex lässt nur einzelnes `/` zu, nicht `//`.
- **B4 – erledigt (Größe).** `MAX_ARCHIVE_BYTES` / `MAX_DATABASE_JSON_BYTES`,
  Prüfung via `statName` vor dem Entpacken. Der `DELETE FROM`-Tabellenname war
  bereits mit Backticks eingefasst und auf real existierende Tabellen begrenzt.
- **B5 – erledigt.** `Auth::user()`/`originalUser()` und
  `UserRepository::all()/search()` entfernen `password_hash`. Neue
  `Auth::verifyPassword()` für die Passwort-ändern-Prüfung.
- **B6 – erledigt.** Migration `2026-09-27` würfelt `ical_uid` laufender
  Termine neu (`RANDOM_BYTES`); die Backfill-Zeile der Ursprungsmigration
  nutzt kein `UUID()` mehr.
- **B7 – erledigt.** `is_uploaded_file()` im XLSX-Import.
- **B8 – erledigt.** 10-s-Sperre pro Sitzung vor erneutem `save`.

Weiterhin offen (brauchen eigene Entscheidung/Release): **B1** (Konto-weites
Login-Lockout vs. DoS-Risiko).

---

## Befunde im Detail

### A1 – Geheim-Tokens im Query-String

**Betroffen:** `/meine-daten?token=…` (Daten-Check-Link, `DataCheckController`),
`/registrieren?token=…` (Einladungs-/Registrierungslink, `RegistrationController`,
`inviteUrl()`).

Der Passwort-Reset wurde in v1.0.0 bewusst vom Query in ein **Pfad-Segment**
verlegt (`/passwort-neu/<token>`) – Begründung im Code: „landet so nicht in
Server-Logs, Browser-Verlauf oder Referrer-Headern". Genau diese Begründung
gilt für die beiden anderen Links **gleichermaßen**, sie stehen aber weiter im
Query:

- `Referrer-Policy: same-origin` → beim Laden von `/assets/*` auf der
  Token-Seite geht die **volle URL inkl. Token** als `Referer` an denselben
  Server (steht damit in dessen Access-Log).
- Die Anfragezeile `GET /meine-daten?token=… HTTP/1.1` steht ohnehin im
  Webserver-Log.
- Browser-Verlauf, geteilte Bookmarks, „Link kopieren".

**Auswirkung:** Der Daten-Check-Token autorisiert **Schreibzugriff auf
personenbezogene Daten** (Name, Adresse, Geburtstag, Telefon, E-Mail, Beruf,
Webseite) eines Kontakts. Der Einladungstoken führt zu einem **neuen Konto
für diese Adresse** (Angreifer setzt das Passwort).

**Empfehlung:** Beide Links analog zum Passwort-Reset auf ein Pfad-Segment
umstellen (`/meine-daten/<token>`, `/registrieren/<token>`), Alt-Query-Links per
302-Shim auf die Pfad-Form umleiten. Zusätzlich `Referrer-Policy` auf
`strict-origin` oder `no-referrer` für diese Seiten setzen.

### A2 – Daten-Check-Link nicht einmalig

`DataCheckRepository::findValidByToken()`:

```sql
WHERE dc.token_hash = :hash
  AND dc.expires_at >= NOW()
  AND c.archived_at IS NULL AND c.deleted_at IS NULL
```

Es fehlt `AND dc.used_at IS NULL`. `markUsed()` wird zwar aufgerufen, aber nie
ausgewertet. Der Link bleibt damit bis zum Ablauf (Standard 30 Tage) gültig –
`contacts.data_check_days`. In Kombination mit A1 (Token im Query, leakt in
Logs/Verlauf) heißt das: wer den Link je gesehen hat, kann die Kontaktdaten
30 Tage lang ändern.

**Hinweis:** Die Wiederverwendbarkeit ist teils Absicht („Du kannst über
denselben Link jederzeit noch etwas ändern, solange er gültig ist"). Wenn das
so bleiben soll: Gültigkeit deutlich verkürzen (z. B. 72 h Default), Token in
den Pfad (A1), und optional die Person beim Öffnen per Mail informieren
(„Dein Daten-Link wurde gerade geöffnet").

### A3 – bcrypt-Verstärkung bei der Einladungs-Token-Suche

`RegistrationInviteRepository::findValidByToken()` lädt bis zu 50 offene
Einladungen und ruft für jede `password_verify($token, $row['token_hash'])`
(bcrypt). Kein `preg_match`-Formatcheck vorher, und der Pfad `/registrieren`
(GET `form` und POST `complete`) hat **kein** Rate-Limit (das
`recentCountByIp`-Limit sitzt nur in `requestSelf`).

**Auswirkung:** Ein unauthentifizierter Angreifer, der `/registrieren?token=x`
in einer Schleife abruft, erzwingt jeweils bis zu 50 bcrypt-Operationen
(~2–5 s CPU pro Request). Bei mehreren offenen Einladungen ein wirksamer
Rechen-DoS.

**Empfehlung:** Analog zu `password_resets` (v1.0.0) eine
`token_sha CHAR(64)`-Spalte + Index einführen, per SHA-256 in O(1) die eine
Zeile holen und nur **einmal** `password_verify` rechnen. Zusätzlich
Formatprüfung (`/^[a-f0-9]{48}$/`) und ein Rate-Limit auf `/registrieren`.

### A4 – Sitzungen überleben Passwortwechsel

`UserController::updateOwnPassword()`, `UserController::setPassword()` (Admin)
und `PasswordResetService::reset()` setzen den `password_hash` neu, beenden aber
**keine bestehenden `user_sessions`**. Wer eine Sitzung auf einem anderen Gerät
hat (z. B. ein Angreifer nach Phishing), bleibt dort bis zum Timeout
(30 min Inaktivität) angemeldet – auch wenn das Opfer sofort das Passwort
ändert.

**Empfehlung:** Seit v1.33 gibt es `user_sessions` + „Sitzung beenden". Bei
jedem Passwortwechsel `UPDATE user_sessions SET revoked_at = NOW() WHERE
user_id = :id` (außer der aktuellen Sitzung) – der Bootstrap-Check in
`index.php` meldet die anderen dann beim nächsten Request ab.

### A5 – `users.manage` ist „alles über alle, auch Admins"

`UserController::store/setPassword/sendReset/toggleActive/resetPasskeys` und
`impersonate` prüfen nur `requirePermission('users.manage')` bzw.
`canAsOriginal('users.manage')` – **nicht** die Rolle des Ziel-Kontos relativ
zur eigenen. Die Rechte-Matrix (`SettingsController::updatePermissions`) erlaubt
es einem Admin, `users.manage` einer beliebigen Rolle zu geben.

Folge: Eine Rolle mit `users.manage`, die nicht `admin` ist, kann
Admin-Passwörter neu setzen, sich als Admin anmelden („impersonieren"),
Admin-Konten sperren und neue Admin-Konten anlegen. Die einzige Bremse ist der
„letzter aktiver Admin kann nicht gesperrt werden"-Check.

**Empfehlung:** Eine von zwei Maßnahmen:
1. In `updatePermissions` verhindern, dass `users.manage` einer Nicht-Admin-Rolle
   zugewiesen wird (mit sichtbarer Begründung), **oder**
2. In den genannten Aktionen zusätzlich `Auth::isAdmin()` verlangen, sobald das
   Ziel-Konto die Rolle `admin` hat (bzw. für `impersonate` generell, wenn das
   Ziel mehr Rechte hätte).

### B1 – kein Konto-weites Login-Lockout

`AuthController::login` sperrt nach `login_max_attempts` (5) Fehlversuchen pro
`(E-Mail, IP)` **oder** `login_max_attempts_ip` (20) pro IP. Ein Angreifer mit
vielen IPs (Botnet, Proxy-Pool) umgeht beides: pro IP 5 Versuche je Konto,
Lockout-Fenster 10 min. Es gibt **keinen** Zähler „X Fehlversuche für diese
E-Mail über alle IPs".

**Empfehlung:** Weiches Backoff pro E-Mail (z. B. ab 20 Fehlversuchen in 15 min
künstliche Verzögerung, oder eine kurze globale Cooldown-Phase). Kein hartes
Lockout pro Konto (sonst DoS: Angreifer sperrt gezielt Nutzer aus). 12-Zeichen-
Mindestlänge gilt bereits für Reset/Setup/Änderung – Alt-Konten aus dem Import
haben aber ein Zufallspasswort, das ist ok.

### B2 – `/orga-team` ohne Rate-Limit

`OrgaController::send` – `requireAuth` + CSRF, sonst keine Drossel. Jede
eingeloggte Person (auch „Mitglied") kann beliebig oft Betreff+Text ans
Orga-Team schicken. Reply-To ist die echte Absenderadresse, Ziel ist nur das
Orga-Team – der Schaden ist begrenzt, aber es ist ein Spam-/Reputations-Vektor
(SMTP-Kontingent, Absender-Domain als Spam markiert).

**Empfehlung:** Zähler wie bei der Selbst-Registrierung (z. B. max. 5/Stunde je
Konto).

### B3 – protokollrelative URLs in Rechtstexten

`sanitize_rich_html`: `if (!preg_match('#^(https?:|mailto:|tel:|/|\#)#i', $href))
{ removeAttribute }`. `//fremd.tld` beginnt mit `/` und passt. Der Link geht dann
(mit `rel="noopener noreferrer nofollow"`) auf eine fremde Seite, obwohl er
„intern" aussieht. Nur über Impressum/Datenschutz (Recht `users.manage`)
einschleusbar.

**Empfehlung:** `#^(https?://|mailto:|tel:|/(?!/)|\#)#i` – ein `/` nur, wenn ihm
kein weiteres `/` folgt.

### B4 – Backup-Restore: Größe & DELETE-Escape

`BackupController::restore` prüft `is_uploaded_file`, aber nicht die Dateigröße;
`BackupService::restoreArchive` liest `database.json` per `getFromName` komplett
in den Speicher und `json_decode`t es. Eine sehr große (oder stark komprimierte)
Datei kann den Speicher sprengen. Zusätzlich: `DELETE FROM \`$table\`` ohne
`str_replace('\`','\`\`', $table)` (im INSERT-Pfad wird escaped). `$table` ist
durch `tableExists()` auf echte Tabellennamen begrenzt, also praktisch nicht
ausnutzbar – aber inkonsistent.

**Empfehlung:** Obergrenze für die hochgeladene ZIP (z. B. 50 MB) und für die
entpackte `database.json` (z. B. 100 MB). Backtick-Escape auch im DELETE.

### B5 – `password_hash` im Ausgabe-Kontext

`Auth::user()` gibt die volle `users`-Zeile zurück (inkl. `password_hash`);
`BaseController::render()` reicht `currentUser` an **jedes** Template.
`UserRepository::search()` macht `SELECT users.*` und das Ergebnis geht in die
Such-Ansicht. Nirgends wird der Hash ausgegeben – aber ein einziges
`json_encode($currentUser)` oder `<?= $currentUser['password_hash'] ?>` würde
ihn exponieren.

**Empfehlung:** In `BaseController::render()` `unset($user['password_hash'])`
für das an die View gereichte `currentUser` (das interne `Auth::user()` für
`password_verify` unangetastet lassen). `UserRepository::search()` auf die
tatsächlich gebrauchten Spalten einschränken.

### B6 – vorhersehbare `ical_uid`

Migration `2026-09-18-termine-plus`: `UPDATE events SET ical_uid =
REPLACE(UUID(),'-','')`. MariaDBs `UUID()` ist Version 1 (Zeit + Knoten-ID),
also teilweise vorhersehbar. `/termine/termin.ics?k=<ical_uid>` ist öffentlich
und liefert Titel, Datum, Ort, Beschreibung eines Termins. Keine Teilnehmer-PII.

**Empfehlung:** `ical_uid` aus `bin2hex(random_bytes(16))` erzeugen (128 Bit
Zufall). Geringe Priorität.

### B7 – XLSX-Import ohne `is_uploaded_file`

`ContactPortController::importXlsx` prüft `error === UPLOAD_ERR_OK`, ruft aber
weder `is_uploaded_file()` noch `move_uploaded_file()` – der `tmp_name` geht
direkt an `ContactImportService`. In normaler PHP-Umgebung unkritisch (nur der
echte Upload-Temp kommt mit `UPLOAD_ERR_OK`), aber `is_uploaded_file()` wäre die
saubere Absicherung. Recht `contacts.manage`.

### B8 – Daten-Check-`save` ohne Rate-Limit

`DataCheckController::save` – wer den Token hat, kann beliebig oft speichern.
Der Token gibt ohnehin Schreibzugriff, also niedrige Priorität; ein kurzer
Cooldown (z. B. 5 s wie bei „nochmal an mich") schadet nicht.

---

## Geprüft und in Ordnung

- **CSRF:** flächendeckend. Token = `random_bytes(32)` hex, `hash_equals`,
  rotiert bei Login/Impersonation. Alle ~110 zustandsändernden POST-Routen
  validieren. Cron nutzt bewusst einen Schlüssel statt CSRF.
- **SQL-Injection:** durchgängig parametrisiert. `ORDER BY` über Allowlist +
  auf `ASC`/`DESC` normalisierte Richtung. `globalSearch` (neu): `?`-Platzhalter,
  Bedingungen sind literale Strings, `LIMIT (int)`. `sent_mails`-Suche:
  `contact_id` per `(int)`. Kein Fund.
- **WebAuthn (`WebAuthnService::finishAuthentication`):** prüft Challenge
  (einmalig, aus der Session, exakter Vergleich), Origin (exakt gegen
  `app.base_url`), RP-ID-Hash (`hash_equals`), `type = webauthn.get`,
  `crossOrigin`, Signatur (`openssl_verify` gegen den gespeicherten Public Key),
  Flags **UP und UV**, Credential-ID-Bindung, Zähler-Rücklauf. Die `user_id`
  kommt aus der gespeicherten Credential-Zeile, nie vom Client. **Kein
  Auth-Bypass.** ES256-only ist eine Kompatibilitäts-, keine Sicherheitsfrage.
- **Passwort-Reset:** 256-Bit-Token, bcrypt + SHA-256-Index, im **Pfad** (nicht
  Query), Ablauf + `used_at` in SQL geprüft, bei Nutzung werden **alle** offenen
  Tokens des Kontos verbraucht, Anti-Spam (max. 1/5 min), keine Enumeration
  (Dummy-Hash für unbekannte Adressen).
- **Datei-Uploads:** serverseitige MIME-Erkennung (`mime_content_type`, nicht
  der Client-Header), Zufalls-Dateinamen, feste Endung aus MIME, **SVG explizit
  abgelehnt** (Logo), Anhänge außerhalb des Webroots (`storage/tmp/`),
  `preg_replace`+`basename` auf Namen.
- **XXE:** `XlsxReader` nutzt `loadXML(..., LIBXML_NONET | LIBXML_NOERROR |
  LIBXML_NOWARNING)` – **ohne** `LIBXML_NOENT`/`LIBXML_DTDLOAD`, dazu
  `MAX_XML_BYTES`-Deckel. Externe Entities werden nicht aufgelöst.
- **Theme-Tokens → `<style>`:** `ThemeService::tokenValueIsValid` verbietet
  `< > { } ;`, `url(`, `expression`, `@import`, `/*`, Länge > 120; Farbe/Länge/
  Font je eigene Regex. `normalizeTokens` läuft bei **jedem** Render erneut. Ein
  `</style>`-Ausbruch ist nicht möglich (und die nonce-CSP würde eingeschleustes
  `<script>` ohnehin blocken).
- **`sanitize_rich_html`:** DOM-Allowlist, entfernt `script/style/iframe/object/
  embed/noscript/template` **samt Inhalt**, streift alle Attribute außer einer
  winzigen sicheren Menge, blockt `javascript:`/`data:` in `href` (bis auf den
  protokollrelativen Fall B3).
- **CSP:** `default-src 'self'`, Skripte nur mit Per-Request-Nonce,
  `frame-ancestors 'none'`, `object-src 'none'`, `base-uri 'self'`,
  `form-action 'self'`. `style-src 'unsafe-inline'` (für den Theme-`<style>`
  nötig) – begrenzt, weil Ausgaben escaped sind und Theme-Werte streng gefiltert.
- **Session-Cookie:** `HttpOnly`, `SameSite=Strict`, `Secure` (bei
  `app.force_https`), `session_regenerate_id(true)` bei Login (Fixation),
  Inaktivitäts-Timeout.
- **Impersonation:** `canAsOriginal('users.manage')` (kein Verketten), Ziel ≠
  selbst, Audit-Log; ENUM-Bug aus v1.7.1 behoben.
- **Router:** dynamische Routen über `preg_quote`, Parameter `[^/]+`,
  `rawurldecode` erst nach dem Match – keine Regex-Injection, kein
  Pfad-Durchbruch.
- **Backup-Restore:** Spaltennamen auf echte Spalten der Zieltabelle gefiltert
  (`array_intersect_key` + `columnsOf`), Werte parametrisiert, Upload-Dateinamen
  über `isSafeUploadName` (kein `.php`/`.htaccess`/Traversal). Die H1/H2-Befunde
  aus v1.0.0 bleiben geschlossen.
- **Mailversand:** CRLF aus allen Adressfeldern entfernt (`safeAddress`,
  `replyToList`), Betreff im `mail()`-Pfad base64-kodiert → keine
  Header-Injection.
- **Cron:** `hash_equals` + Mindestlänge, 404 bei falschem/fehlendem Schlüssel
  (Endpunkt verrät sich nicht).
- **Selbst-Registrierung:** `default_role` kann nie `admin` sein (expliziter
  Filter), Rate-Limit 5/h je Quelle, neutrale Antworten (keine Enumeration).
- **Keine Konto-Enumeration** über Login, Reset oder Registrierung (überall
  gleiche Antwort/Antwortzeit für „Adresse (un)bekannt").
- **`view_partial()`:** alle 5 Aufrufe mit fest verdrahteten Namen – aktuell
  keine LFI-Fläche. Falls je eine Variable übergeben wird: `..` und
  Nicht-`[a-z0-9/_-]` ablehnen.

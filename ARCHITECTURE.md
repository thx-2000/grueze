# Architekturüberblick

Für Mitentwickelnde. Ergänzt `CLAUDE.md` (Konventionen) und `docs/SPRACHE.md`
(sichtbare Texte). Der vollständige Sicherheitsbericht steht in
`docs/SECURITY-AUDIT.md`.

## Grundaufbau

Server-gerenderte PHP-App (PHP 8.2+), **kein Framework**, keine Build-Kette für
das Frontend. `public/` ist der DocumentRoot. `public/index.php` erledigt
Bootstrap, den kleinen DI-Container und das Routing. Controller bleiben schlank
und delegieren Datenzugriffe an **Repositories** und Fachlogik an **Services**;
Ausgabe über **Templates** im gemeinsamen Layout `templates/layout/app.php`.

Abhängigkeiten: MariaDB/MySQL (PDO), optional `phpmailer/phpmailer` (Composer)
für SMTP + Anhänge, PHP-Erweiterungen `sodium` (at-rest-Verschlüsselung) und
`zip` (Backup).

## Ordnerstruktur

```
public/
  index.php            Bootstrap, Container-Factories, GC, Router, alle Routen
  sw.js                Service Worker (statisch)
  assets/{css,js,fonts,img,uploads}
config/
  config.php           lokal, NICHT im Git/Deploy (Kopie von config.example.php)
database/
  schema.sql           vollständiges Schema für frische Installationen
  migrations/*.sql     additive, idempotente Änderungen (Dateiname = Migration)
src/
  Core/                Autoloader, Container, Router, Request, Auth, Csrf, Session, Config, Database, View
  Controllers/         eine Klasse je Bereich, Methoden = Routen-Handler
  Repositories/        alle SQL-Zugriffe, je Tabelle(ngruppe) eine Klasse
  Services/            Mail, Backup, Themes, Scheduler, WebAuthn, Import, Uploads, Validator, Crypto
  Support/             helpers.php (globale Funktionen), Crypto.php, Redirect.php
templates/             *.php, per require im Layout eingebunden
themes/                Theme-Definitionen (Datei + DB-Tabelle `themes`)
resources/help/        Admin-geschützte Hilfe-PDF/HTML (außerhalb DocumentRoot)
storage/{tmp,backups}/ Laufzeitdaten; storage/app.key = at-rest-Schlüssel
docs/                  ARCHITECTURE, SECURITY-AUDIT, UX-REVIEW, SPRACHE, NEUE-INSTANZ, REDESIGN
```

## Request-Lebenszyklus

`public/index.php`, von oben:

1. **Autoloader** (`src/Core/Autoloader.php`, PSR-4-artig: `App\Foo\Bar` →
   `src/Foo/Bar.php`), dann `helpers.php` + `Redirect.php`, dann – falls
   vorhanden – `vendor/autoload.php`.
2. `Config::load()` liest `config/config.php`. `Session::start()` (gehärtete
   Cookies). `send_security_headers()` setzt CSP (nonce) + die üblichen Header.
3. **Container-Factories registrieren.** Jede Klasse, die der Container liefern
   soll, braucht eine explizite `Container::factory(Klasse::class, fn () => …)`
   – **kein Autowiring.** Neue Controller/Repos/Services hier eintragen.
4. **Optionales Auto-Update** (`config('app.auto_migrate')`, Standard aus) und
   **probabilistische GC** (1/100 der Requests: alte Login-Versuche,
   abgelaufene Reset-/Token-Daten, Papierkorb leeren, Secrets nachverschlüsseln;
   1/20: `EventScheduler`/`GreetingScheduler` als Cron-Rückfallebene, max.
   1×/Stunde).
5. **Router** wird gebaut, alle Routen darunter registriert, dann
   `$router->dispatch($request)`.

Der Router-Handler ruft `Container::get(Controller::class)->methode($request)`.
Der Controller rendert via `BaseController::render('pfad/template', [...])` →
`View::render()` bindet `templates/layout/app.php` ein, das das eigentliche
Template per `require` lädt.

## Container & Router

- **`src/Core/Container.php`** – Service Locator. `factory()` registriert eine
  Closure (lazy), `get()` instanziiert einmalig und cached. Reihenfolge der
  `factory()`-Aufrufe ist egal, solange alles vor dem ersten `get()` registriert
  ist.
- **`src/Core/Router.php`** – exakter Pfad-+Methoden-Abgleich. Zusätzlich
  dynamische Segmente: `/{name}` im Pfad wird zu `([^/]+)` und als weiteres
  Handler-Argument nach `$request` übergeben (z. B.
  `/passwort-neu/{token}` → `showResetPassword(Request $r, string $token)`).
  Query-Parameter (`?id=`) liest der Controller über `$request->input('id')`.
- Öffentliche Routen (ohne Login) stehen mit im selben Block; der Schutz sitzt
  je Controller-Methode über `requireAuth()` / `requirePermission()`.

## Controller / Repository / Service / View – was gehört wohin

| Schicht | Aufgabe | Regel |
|---|---|---|
| Controller | Request → Validierung → Repo/Service aufrufen → `render()` oder `Redirect::to()` | keine SQL, keine MIME-/Krypto-Details |
| Repository | **alle** SQL-Statements zu einer Tabelle(ngruppe) | Prepared Statements, keine HTML-Ausgabe |
| Service | zustandslose Fachlogik (Mail, Backup, Krypto, Scheduler …) | kein direkter `$_SESSION`/Request-Zugriff außer WebAuthn (Challenge in Session) |
| Template | reine Ausgabe, `e()` um jede Ausgabe | Logik nur fürs Darstellen, deutsche Texte (`docs/SPRACHE.md`) |

`helpers.php` hält globale Funktionen: `e()`, `url()`, `asset_url()`,
`config()`, `can()`, `can_view_contact_field()`, `icon()`, `page_title()`,
`format_date()` / `format_deadline()` / `time_until_hint()`, `branding_*()`,
`system_version()`, `scheduler_stale()`, `system_update_pending()` u. a.

## Datenmodell (Kurzüberblick)

- **`users`, `roles`** – Zugänge, Rollen, Aktivstatus, `last_login_at`.
  `roles.name` = fixer Rechte-Schlüssel, `roles.label` = editierbarer Anzeigename.
- **`contacts`** – Stammdaten je Person. Spalte `anrede` (`m`/`w`/leer, früher
  `geschlecht`) steuert nur die Brief-Anrede. `beruf` und `webseite` sind
  freie Zusatzfelder (Webseite wird beim Speichern auf `https://…`
  normalisiert).
  `archived_at` / `deleted_at` / `retired_by` = Archiv bzw. 30-Tage-Papierkorb;
  „lebende" Kontakte haben beide Zeitstempel `NULL`
  (Filter `ContactRepository::LIVE`).
- **`contact_emails`, `contact_phones`, `contact_tags`, `categories`, `tags`**
  – Kontaktwege, Tags (n:m), Kategorie (1:n, bewusst einfach).
- **`contact_groups`, `contact_group_members`** (`role` member/lead),
  **`contact_group_join_requests`**, **`group_mail_log`** – frei definierbare
  Personengruppen quer zu Kategorie/Tag. Mitgliedschaft am Kontakt.
  `is_open` = Selbst-Beitritt, `mail_locked` = Notbremse.
- **`events`** – Termine/Abstimmungen. `kind` = `date_poll` | `fixed_date` |
  `poll`. `status` = open | closed | decided | archived. `group_id` bindet
  optional an eine Gruppe (kein DB-FK). `closes_at`, `result_recipients`,
  `reminder_sent_at`, `result_mail_sent_at` = Fristen-/Ergebnis-Automatik.
  `ical_uid` (Kalender-Download), `remind_days_before` + `event_reminder_sent_at`
  (Vorab-Erinnerung). `decided_option_id`.
  **`event_options`**, **`event_participants`** (`token` fürs Abstimmen ohne
  Login, `note` = freie Anmerkung), **`event_responses`**,
  **`event_token_hits`** (pseudonyme Quell-Hashes → „⚠ N Quellen"),
  **`event_response_log`**.
- **`password_resets`** – `token_hash` (bcrypt) + `token_sha` (SHA-256-Index für
  den Lookup ohne E-Mail).
- **`user_passkeys`** – WebAuthn-Credentials (Public Key PEM, sign_count).
- **`contact_data_checks`** – „Daten-Check-Link": SHA-256-Token, mit dem eine
  Person ihre eigenen Kontaktdaten ohne Login korrigiert.
- **`app_settings`** – Key-Value-Store (Branding, Rechte-/Sichtbarkeitsmatrix,
  Mail-Einstellungen, Grüße-Automatik, `scheduler_last_run` …). Beliebige Keys,
  `set()` per UPSERT – **für neue Keys keine Migration nötig.**
  Die Mail-Passwörter (`mail_smtp_password`, `mail_imap_password`) liegen
  verschlüsselt (siehe „at rest").
- **`registration_invites`** – Selbst-Registrierung (Token gehasht).
- **`user_sessions`** – angemeldete Browser-Sitzungen (SHA-256 der Session-ID,
  IP, User-Agent, `last_seen_at`, `ended_at`, `revoked_at`). Bei jedem Request
  aufgefrischt (`index.php`), speist „Verwaltung → Anmeldungen"; `revoked_at`
  meldet die Sitzung beim nächsten Request ab.
- **`sent_mails`** – Verlauf des Serienversands: eine Zeile je abgeschlossenem
  Auftrag mit Betreff, Rohtext und der Empfängerliste (JSON, inkl.
  Zustellstatus). `MailController::batch()` schreibt sie beim Abschluss;
  speist „Nachrichten → Gesendete Nachrichten" (Sender-Sicht, `SentMailController`)
  und „Erhaltene Mails" (Empfänger-Sicht, `ReceivedMailController` – findet über
  `recipients LIKE '%"contact_id":N,%'` die Mails an den eigenen Kontakt und
  rendert sie mit aufgelöster Anrede).
- **`audit_log`, `mail_log`, `login_attempts`, `schema_migrations`, `themes`**.

## Rollen & Rechte

- **`src/Core/Auth.php`.** `can($perm)` schlägt in der
  **Rechte-Matrix** nach (`SettingRepository::permissionMatrix()` →
  `perm => [rolenames]`, gespeichert als CSV `security_permission_<perm>` oder
  Code-Default `permissionDefaults()`). `admin` ist ein harter Sicherheitsanker:
  `isAdmin()` (`role_name === 'admin'`) ist überall implizit `true` und wird nie
  über die Matrix entzogen; die Rolle `admin` lässt sich nicht umbenennen.
- **Feld-Sichtbarkeit:** `canViewContactField($feld, $contact = null)` – welche
  Rolle welche Kontaktfelder sieht (`security_visibility_<feld>`), plus die
  Ausnahme „eigener verknüpfter Kontakt". `ContactController::redactHiddenFields`
  leert nicht sichtbare Werte, **bevor** die Liste an die View geht.
- **Gruppenleitung:** `GroupRepository::isLead()` +
  `GroupController::requireGroupManage()` erlauben das Verwalten der **eigenen**
  Gruppe ohne globales `groups.manage`.
- Rollen sind datengetrieben; `RoleController` legt an/benennt um/löscht,
  `SettingRepository::renameRoleEverywhere()` zieht Matrix-Werte + Default beim
  Umbenennen mit.

## Migrationen & `schema.sql`

**Regel:** Jede Schema-Änderung wandert in **BEIDE** Dateien –
`database/migrations/JJJJ-MM-TT-name.sql` (für Bestandsinstanzen) **und**
`database/schema.sql` (für frische Installationen). `MigrationService` markiert
bei einer leeren, frisch aus `schema.sql` importierten DB alle vorhandenen
Migrationen als angewendet (`seedPreExisting()`), damit sie nicht doppelt
laufen.

- Migrations-`.sql` läuft als Ganzes durch `PDO::exec` (mehrere DDL-Statements
  ok). MariaDB kann `ADD COLUMN IF NOT EXISTS` / `ADD KEY IF NOT EXISTS` →
  Migrationen bleiben idempotent.
- **Anwenden:** Verwaltung → *System* → **Aktualisieren** (`/admin/aktualisieren`,
  `UpdateService`): optionales Backup nach `storage/backups/`, dann alle offenen
  Migrationen, dann `app_settings.app_version` setzen. Ein Admin-Hinweisstreifen
  im Layout (`system_update_pending()`) erinnert daran.
- **Lazy-Fallback (`ensureSchema()`).** Damit die Seite auch im Fenster
  zwischen Datei-Upload und „Aktualisieren" nicht 500t, ziehen die Repos für
  neue, heiß genutzte Tabellen/Spalten selbst nach: `GroupRepository`,
  `ContactRepository` und `DataCheckRepository` rufen im Konstruktor ein
  `ensureSchema()` (statischer Guard, `CREATE TABLE IF NOT EXISTS` /
  `ALTER … ADD COLUMN IF NOT EXISTS`, `try/catch`). **Bei jeder neuen Spalte an
  `contacts`/`users` daran denken.**
- White-Label-Regel: Wird ein Code-Default markenneutral gemacht, sichert
  vorher eine `INSERT IGNORE`-Migration die Instanzwerte in `app_settings`.
  Umbenennungen (Slugs, Setting-Keys) bekommen für das Deploy-Fenster einen
  Code-Alias + eine `UPDATE`-Migration.

## Mailing

Zweistufig (`MailController` + `MailService`): Entwurf/Anhänge werden
entgegengenommen, der Versand läuft in kleinen Batches über wiederholte Requests
(Timeout-Schutz auf Shared Hosting, Fortschrittsanzeige). PHPMailer/SMTP wenn
vorhanden, sonst `mail()` (kein Anhang). Nach jedem Versand legt
`MailService::archiveSentCopy()` eine Kopie im IMAP-„Gesendet"-Ordner ab –
über die `imap`-Erweiterung oder, wenn die fehlt, über einen minimalen
handgeschriebenen IMAP-Client (siehe Klassen-Docblock).
Platzhalter `{Anrede}`/`{Vorname}`/`{Nachname}` (`renderMessageTemplate`),
`{Abstimmungslink}` setzt der Controller je Empfänger.

## Zeitgesteuerte Aufgaben

`EventScheduler` (Fristen schließen · 48-h-Erinnerung an Nicht-Abstimmende ·
Vorab-Erinnerung X Tage vor dem Termin · Ergebnis-Mail), `GreetingScheduler`
(täglicher Geburtstagsgruß). Einstieg: `GET/POST /intern/cron?key=…`
(`CronController`, `hash_equals` gegen `app.cron_key`, sonst 404). Zusätzlich
räumt der Cron den Kontakt-Papierkorb (`contacts_purged`). Ohne echten Cron
läuft die gedrosselte Rückfallebene aus `index.php` (max. 1×/Stunde, Flag
`scheduler_last_run`). Jeder Job ist idempotent (Zeitstempel-Spalten am Event).
Läuft seit >48 h nichts, zeigt der Verwaltungs-Hub einen Hinweisstreifen
(`scheduler_stale()`).

## Verschlüsselung „at rest"

`App\Support\Crypto` (libsodium `secretbox`) verschlüsselt einzelne sensible
`app_settings`-Werte (aktuell die Mail-Passwörter). Schlüssel:
`config('security.secret_key')` **oder** die automatisch erzeugte Datei
`storage/app.key` (0600, per `.gitignore`/`.rsyncignore` von Git und Deploy
ausgenommen – jede Instanz hat ihren eigenen). Ohne verfügbaren Schlüssel
fällt alles transparent auf Klartext zurück. `SettingRepository` ver-/
entschlüsselt in `get()`/`set()`; `reencryptSecrets()` (GC) zieht Altbestände
nach.

## Themes / Branding / White-Label

Aussehen in Themes (`themes/*.php` + Tabelle `themes`), aktives Theme in
`app_settings.active_theme`. `theme.css` = ~21 Basis-Tokens, vom `ThemeService`
als Inline-`<style>` überschrieben; `app.css` nutzt durchgängig Tokens.
`readable_ink()` = Auto-Kontrast. Instanzspezifisches (Name, Logo, Links,
Rechtstexte, Mail-Vorlagen) lebt in `config/config.php` (`branding.*`,
`defaults.*`) oder `app_settings`. **Keine Instanznamen, Personendaten oder
festen URLs im Code** – Ausnahme: der Produktname GRUEZE (`system_label`).

## PWA

`public/sw.js` (statischer Service Worker, cacht nur `/assets/` + `/app-icon.svg`
– nie Seiten mit Kontaktdaten). `PwaController` liefert
`/manifest.webmanifest` und `/app-icon.svg` (Farben/Kürzel aus Theme +
Branding). Metas + Registrierung im Layout bzw. `app.js`.

## Sicherheitsentscheidungen

- PDO + Prepared Statements überall (`EMULATE_PREPARES` aus).
- CSRF auf jedem zustandsändernden Endpunkt (`Csrf::validate`).
- Session: `httponly`, `secure`, `SameSite=Strict`, Inaktivitäts-Timeout,
  `session_regenerate_id` bei Login/Rollenwechsel/Timeout.
- CSP (nonce, `csp_nonce()`) + `X-Frame-Options: DENY`, `nosniff`,
  `Referrer-Policy: same-origin`, HSTS bei HTTPS – `send_security_headers()`.
  Keine Inline-Event-Handler; Rückfragen über `data-confirm` + zentraler
  Handler in `app.js`.
- Login-Ratelimit je (E-Mail, IP) **und** je IP; konstante Antwortzeit
  (Dummy-`password_verify`) gegen Konten-Enumeration.
- Passwort-Reset: Token als bcrypt-Hash + SHA-Index, Link im **Pfad-Segment**
  (`/passwort-neu/<token>`, nicht im Query), 1 Mail je Konto / 5 min, alte
  Tokens werden beim Zurücksetzen verbraucht.
- Uploads: Inhalts-MIME-Prüfung, Zufallsnamen, `engine off` + `RemoveHandler`
  im Upload-Ordner; kein SVG-Logo. Backup-ZIP optional AES-256.
- Admin-HTML (Impressum/Datenschutz) durch `sanitize_rich_html()` (DOM-
  Allowlist); Theme-Token-Werte durch `ThemeService::tokenValueIsValid()`.
- `config.php` / `deploy.env` / `storage/app.key` außerhalb von Git und Deploy.
- Voller Bericht: `docs/SECURITY-AUDIT.md`.

## Eine neue Seite hinzufügen – Checkliste

1. **Controller-Methode** in `src/Controllers/…` (oder neuen Controller +
   `Container::factory` in `index.php`). Erste Zeile `requireAuth()` bzw.
   `requirePermission('…')`.
2. **Route(n)** in `public/index.php` (`$router->get/post`).
3. **Repository-Methoden** für neue SQL-Zugriffe – keine Queries im Controller.
4. **Template** unter `templates/…`, im Layout automatisch verfügbar; genau
   **ein `<h1>`**, `e()` um jede Ausgabe.
5. **Nav/Verlinkung:** Rail (`templates/layout/app.php`) oder Verwaltungs-Hub
   (`templates/admin/hub.php`), rechte-gated. `page_title()` in `helpers.php`
   ergänzen.
6. **Schema-Änderung?** → Migration **und** `schema.sql`; bei neuer Spalte an
   `contacts`/`users` zusätzlich `ensureSchema()` im Repo.
7. **Neuer `app_settings`-Key?** → keine Migration, nur `set()`/`get()`.
8. **CSS** in `public/assets/css/app.css` mit Theme-Tokens; **JS** ans Ende von
   `public/assets/js/app.js` (kein Inline-Script ohne Nonce).
9. `system_version()` bumpen, `CHANGELOG.md` + `TODO.md` pflegen, committen,
   GitHub-Release, deployen (siehe `CLAUDE.md` bzw. Release-Prozess).

## Weiterarbeit

- Bestehende Mechanismen erweitern, nicht parallel duplizieren.
- Vor Änderungen die betroffenen Stellen lesen; Seiteneffekte prüfen.
- Pro Arbeitseinheit: Version bumpen, `CHANGELOG.md`/`TODO.md`, ein GitHub-
  Release, deployen.
- Zentrale Dateien: `public/index.php`, `src/Support/helpers.php`,
  `src/Repositories/SettingRepository.php`, `src/Core/Auth.php`,
  `src/Services/MailService.php`, `templates/layout/app.php`,
  `public/assets/css/app.css`, `themes/`.

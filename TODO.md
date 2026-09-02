# Offene Punkte / Backlog

Laufende Sammlung offener Ideen und Aufgaben für GRUEZE.
Wird nach jeder abgeschlossenen Arbeitseinheit aktualisiert.

## Neu


- **Security-Audit**: Vollständige Sicherheitsüberprüfung des Systems –
  u. a. Auth/Session/Passkey-Flows, CSRF, Rechte-/Rollenprüfung an jedem
  Endpunkt, SQL-/Template-Injection, Datei-Uploads (XLSX-Import, Logo),
  Passwort-Reset, Speicherung von Mailserver-Zugangsdaten, Deploy-Hygiene
  (rsync spielt aktuell auch Dev-Dateien wie `docker/`, `tests/`,
  `*_HANDOFF_*` auf den Webspace). Ergebnis mit Priorisierung dokumentieren.

- **Design-/UX-Überarbeitung (Rolle: Design- und UX-Agentur)**: Navigation
  neu gruppieren und klarer benennen, Gesamtbedienung eleganter und
  übersichtlicher machen. Vorgehen: zuerst erheben, welche Tätigkeiten am
  häufigsten auf der Seite anfallen (Rückfrage an die Nutzer:innen), dann
  diese Kern-Workflows so gestalten, dass sie mit möglichst wenigen,
  eindeutigen Schritten erledigt sind. Menüstruktur, Benennung, Seitenlayout
  und Einstiegspunkte daran ausrichten.
  Erkenntnisse aus dem 1. Gespräch (2026-09-01):
  - Nutzer real fast nur Admins, ggf. Orga-Team; Aktivität rund um jährliche
    Stufentreffen. White-Label wird ernst genommen (Firmen, Vereine, Familien,
    JGA …). Schwächste Nutzer:in: gar nicht technikaffin.
  - Zwei Modi: Admin/Orga = Power-Tool am Schreibtisch (alles verwalten,
    Mailings); alle anderen = Handy, sehr einfach (eigene Daten sehen/pflegen,
    andere finden/kontaktieren, an Abstimmungen teilnehmen).
  - Job-Phasen: erst Daten vervollständigen + Personen anlegen, später
    kontaktieren (meist an alle) + gelegentlich Korrektur.
  - Größter Schmerz: „wirkt zu überfrachtet". Ziel: cleaner, moderner, wertiger.
  - Self-Service gewünscht: jede:r ändert nur die eigenen Daten; Audit-Log muss
    alte Werte behalten (falsches Löschen/Ändern nachvollziehbar).

- **Terminfindungs-/Abstimmungstool** für die ganze Stufe: Ja/Nein/Vielleicht
  je Vorschlag, ggf. mehrere Uhrzeiten pro Tag, ggf. weitere Abstimmungs-
  varianten mitdenken (freie Auswahl, Ranking …). Doodle-artig. Wer legt an
  (nur Admin/Orga?), wie nehmen Nicht-Eingeloggte teil (Link/Token?).

- **„Mail ans Orga-Team"-Knopf**: schneller Kontakt-Button. Von wo erreichbar,
  für wen, an welche Adresse (fest vs. an alle mit Orga-/Admin-Rolle)?

- **Grüße-Pool mit Zufallsrotation**: 10–40 Standard-Wünsche (Geburtstag,
  Weihnachten) hinterlegen und erweitern, beim Versand geshuffelt zuweisen –
  assistiert (Admin prüft Batch) oder automatisch am Tag.

- **Voll-Import: Merge-Modus** – **erledigt in v0.22.0**. Backup → „Zusammen-
  führen" spielt Kontakte ins bestehende System ein, dedupliziert über
  Name/Geburtsname, ergänzt nur fehlende Angaben. Offen falls je nötig: auch
  Benutzer/Rollen mergen (bewusst ausgelassen, zu heikel).

- **Barrierefreiheit – Rest**: Durchgang abgeschlossen (v0.17.0 + v0.17.1).
  Offen bleibt nur ein echter Screenreader-Test (VoiceOver/NVDA) an den
  Kern-Workflows – das lässt sich nur manuell am Gerät machen, nicht im Code.
  Optional später: Feld-genaue Fehlermeldungen (`aria-invalid` +
  `aria-describedby` statt Sammel-Hinweis oben) – dafür müssten die
  Controller wissen, welches Feld betroffen ist.

- **SEO – Rest**: Grundausstattung erledigt (v0.16.0, siehe unten). Offen und
  erst sinnvoll, sobald es eine echte öffentliche Seite / White-Label-Landing
  gibt: Meta-Description je Seite mit echtem Inhalt, Open-Graph/Twitter-Cards,
  strukturierte Daten, Sitemap. Diese Seite müsste ihr `noindex` dann selbst
  wieder aufheben (aktuell sperrt der Layout-Head alles).

## Aus der ursprünglichen Übergabe (ChatGPT), noch offen

1. White-Label: **abgeschlossen in v0.12.0**, Mail-Feinschliff in **v0.19.0**
   (Platzhalter `{name}`/`{kurzname}` in Mail-Fuß + Betreff-Präfixen, neutrale
   Standard-Texte, Member-Modus über Berechtigung statt Rollenname).
2. Rollen- und Rechtekonzept: **datengetrieben in v0.20.0** – Rollen frei
   anlegen/umbenennen/löschen (Verwaltung → Rollen), Rechte-/Sichtbarkeits-
   Seiten lesen alle Rollen aus der DB. Offen (falls je gewünscht): den
   internen Rollen-Schlüssel umbenennbar machen (aktuell fix).
3. Sichtbarkeit einzelner Kontaktfelder: **erledigt in v0.21.0** – „eigener
   verknüpfter Kontakt immer sichtbar (außer Notizen)", abschaltbar.
4. Admin-Einstellungsbereich weiter strukturieren (Branding / Mail-Versand /
   Sichtbarkeiten-Rollen / ggf. System-Backup später).
5. Vorbereitung für neutrale Distribution: Installer-Konzept.
   (Backup/Restore erledigt in v0.4.0; konfigurierbare Startwerte + Setup-Doku
   erledigt in v0.12.0; Update-Konzept erledigt in v0.18.0.)
6. Update-Konzept: **erledigt in v0.18.0** – „Verwaltung → Aktualisieren"
   wendet offene Migrationen per Klick an, mit optionaler Vorab-Sicherung,
   Versionsanzeige und Admin-Hinweisstreifen. Optional `app.auto_migrate`.

## Erledigt

- Backup zusammenführen (v0.22.0): Dritter Restore-Modus `merge` in
  `BackupService::mergeContacts()`. Nur Kontakte + Mails/Telefone/Tags/
  Kategorien; Dedup über `ContactRepository::findImportMatch()` (Name +
  Geburtsname); bestehende Kontakte werden nur ergänzt (fehlende Mails/
  Telefone/Tags, leere Stammdatenfelder), nie überschrieben. Kategorien/Tags
  über Namen aufgelöst/angelegt, Kontaktfotos aus dem ZIP übernommen. Alles
  über natürliche Schlüssel → keine ID-Konflikte. Transaktional. UI:
  `templates/admin/backup.php` mit „Zusammenführen" als Standard-Modus,
  ohne Bestätigungswort (nicht destruktiv). Keine Migration.
- Eigene Kontaktdaten sichtbar (v0.21.0): `Auth::canViewContactField($feld,
  $kontakt=null)` – wenn ein Kontakt übergeben wird und es der eigene
  verknüpfte ist, greift die Ausnahme (Notizen ausgenommen). Schalter
  `security_own_contact_visible` (Standard an) auf der Sichtbarkeits-Seite.
  Auf `/kontakte` rendert `contacts/index.php` für verknüpfte Nutzer:innen ein
  Feld „Deine Kontaktdaten" (`ContactController::index` reicht `$ownContact`
  durch). Keine Migration.
- Volle Rollen-Verwaltung (v0.20.0): `roles`-Tabelle bekommt `label`
  (Anzeigename), interner `name` bleibt der fixe Rechte-Schlüssel. Neue Seite
  **Verwaltung → Rollen** (`RoleController`/`RoleRepository`,
  `templates/settings/roles.php`): anlegen, umbenennen (Label + Beschreibung),
  löschen. `admin` geschützt, Löschen blockiert solange Benutzer zugeordnet,
  danach `SettingRepository::pruneRole()` räumt den Namen aus den
  `security_permission_*`/`security_visibility_*`-Werten. `SettingsController`
  liest Rollen + Labels aus `RoleRepository` statt aus fester Liste;
  `role_label()`-Helper (pro Request gecacht) für Badges/Auswahllisten.
  `RoleRepository::ensureSchema()` legt die `label`-Spalte lazy an, falls die
  Migration noch aussteht. Migration `2026-09-03-rollen-label`.
- White-Label-Feinschliff Mail (v0.19.0): Platzhalter `{name}` / `{kurzname}`
  in Mail-Fuß und Betreff-Präfixen (`apply_branding_placeholders()`), ersetzt
  beim Versand in `MailController`. Neutrale Standard-Texte ohne „Orga-Team".
  „Eingeschränkte Kontaktaufnahme" (`isMemberContactMode`) läuft jetzt über
  `mail.contact_single && !mail.send && !contacts.manage` statt über den
  festen Rollennamen `stufenmitglied`; ebenso der Public-Site-Link in der Nav.
- Update-Konzept (v0.18.0): Neue Seite **Verwaltung → Aktualisieren**
  (`/admin/aktualisieren`, löst die alte „Migrationen"-Seite ab). Zeigt
  installierte vs. Code-Version (`app_settings.app_version`), „Zuletzt
  aktualisiert", offene Migrationen mit Kurzbeschreibung (erste `--`-Zeile der
  `.sql`) und einen **„Jetzt aktualisieren"-Knopf**: optionale Vorab-Sicherung
  nach `storage/backups/` (letzte 3 bleiben), dann alle offenen Migrationen
  der Reihe nach, Version + Zeitstempel setzen. Datei-Lock gegen Doppel-Läufe.
  Admin-Hinweisstreifen im Layout, wenn ein Update aussteht. Einzelanwendung
  bleibt als Fallback (eingeklappt). Optional `config('app.auto_migrate')`
  (Standard aus) wendet offene Migrationen beim ersten Request nach dem Upload
  automatisch an. Neu: `CHANGELOG.md`, wird auf der Seite ausschnittsweise
  angezeigt. `MigrationService`/`UpdateService` sauber getrennt.
- Barrierefreiheit, Abschluss (v0.17.1): Echte Fokus-Falle im aufgeklappten
  Mobil-Menü (Tab/Shift+Tab bleiben im Menü inkl. Hamburger-Knopf, Esc
  schließt). Inline-Links in Fließtext jetzt unterstrichen (WCAG 1.4.1 – vorher
  nur per Farbe vom Text unterscheidbar, Farbe fast identisch). Deckender
  Fokus-Ring auch auf Eingabefeldern bei Tastatur-Fokus (die Akzent-Aura
  allein trug den 3:1-Kontrast nicht auf allen Themes). Platzhaltertext mit
  `--color-muted` statt blassem Browser-Default. Fehlerhinweis nach
  fehlgeschlagener Aktion bekommt den Fokus. Seitenleiste von `<aside>`
  (irreführendes „complementary") zu `<div>`, `<nav aria-label="Hauptnavigation">`.
- Barrierefreiheit, Grunddurchgang (v0.17.0): Skip-Link + `<main id="main">`,
  durchgehender `:focus-visible`-Ring (statt UA-Default / `outline: none`),
  `@media (prefers-reduced-motion)`. Live-Regionen: Toast (`role="status"`),
  Flash-Meldungen (`status`/`alert`), Auswahl- und Versandstatus, Fortschritts-
  balken als `role="progressbar"`. Formulare: unbeschriftete Felder benannt
  (Global-Suche, Repeater-Zeilen für Mail/Telefon, Schnell-Anlegen), Tag-/
  Checkbox-Gruppen als `role="group"` mit Label, Pflichtfeld-Markierung.
  Tabellen: `scope="col"` + `aria-sort` (Kontakte, Benutzer), Auswahl-
  Checkboxen mit Kontaktnamen als `aria-label`. Doppelte App-Namen-Überschrift
  in der Topbar zu `<p>` gemacht (nur noch ein `<h1>`). Nebenbei: XSS im
  Versand-Ergebnis (`innerHTML` → `textContent`) geschlossen.
- SEO-Grundausstattung (v0.16.0): Die App hat keine öffentlichen Inhalte,
  daher defensiv statt offensiv. `public/robots.txt` (`Disallow: /`),
  `<meta name="robots" content="noindex, nofollow">` im Layout-Head plus
  `X-Robots-Tag`-Header über `.htaccess` (greift auch für PDF/CSV). Neu:
  sprechende `<title>` je Seite (`Abschnitt · Instanzname`) über
  `page_title()` bzw. die Render-Variable `$pageTitle`; `<link rel="canonical">`
  und eine generische `<meta name="description">`. Saubere Fehlerseiten:
  `render_error_page()` liefert für 404 (Router) und den 500-Fallback
  (`index.php`) eine eigenständige Seite mit korrektem Statuscode – der
  500-Fall verrät die Exception-Meldung nur noch bei `debug = true`.
- Distribution vorbereitet (v0.15.0): Instanz- und serverspezifische Reste
  aus Repo, Doku und History entfernt – Deploy-Ziel kommt jetzt aus
  `scripts/deploy.env` (lokal, nicht im Repo; Vorlage `deploy.env.example`),
  `config.production-template.php` und die instanzspezifischen Seed-/Rename-
  Migrationen sind raus, Docker-Namen (`grueze_*`, DB `grueze`) und
  `localStorage`-Schlüssel (`grueze_*`) vereinheitlicht, Session-Default-Name
  `grueze_session`, README/ARCHITECTURE/docs markenneutral.
  **Lokale Dev-Umgebung:** einmalig `bash scripts/docker-down.sh` mit
  Volume-Reset und `config/config.php` auf DB `grueze` / User `grueze_user`
  umstellen, dann neu hochfahren.
- Projektunterlagen aufgeräumt (v0.14.1): veraltetes Übergabe-Dokument
  gelöscht (nützliche Punkte jetzt in `ARCHITECTURE.md` → „Weiterarbeit"),
  `.rsyncignore` bereinigt, Commit-Historie geglättet (force-push).
- GRUEZE als Produktname + Repo-Umbenennung (v0.14.0): GRUEZE ist der
  Produktname (nicht Instanz-Branding) und bleibt auch im White-Label
  sichtbar – im Footer (`GRUEZE v0.14.0`, jetzt ohne den Punkt hinter dem v,
  wie der GitHub-Tag) und dezent in der Seitenleiste („läuft mit GRUEZE",
  Link auf `product_url`). `system_label` ist wieder ein reiner config-Wert
  (Default `GRUEZE`), kein Branding-Feld mehr; „Footer-Kürzel" aus der
  Branding-Seite entfernt. GitHub-Repo auf `grueze` umbenannt.
  Neue Prinzipien-Sektion in `ARCHITECTURE.md`: White-Label zuerst denken,
  Bestandsdaten bei einem Upload nie kaputt.
- Gespeicherte Empfängerlisten (v0.13.0): Eine benannte Momentaufnahme einer
  Kontaktauswahl. Im Schreiben-Dialog einer Rundmail „Diese Empfänger als
  Liste speichern" (per fetch, ohne den Entwurf zu verlieren). Im
  Empfängerkreis-Dialog neue Option „Gespeicherte Liste" (zeigt, wie viele
  Mitglieder aktuell noch eine Mailadresse haben); ein aufklappbarer Bereich
  „Gespeicherte Listen verwalten" zum Umbenennen/Löschen. Neue Tabelle
  `mail_recipient_lists` (Migration `2026-09-02-empfaengerlisten`, lazy
  angelegt, greift also auch vor der Migration).
- Startseite: Aktions-Kacheln + Layout (v0.12.1): Die drei Aktionen („Neuen
  Kontakt anlegen" / „Rundmail schreiben" / „Alle Kontakte") sind jetzt
  kräftige Kacheln mit Icon-Plakette und Schatten statt dünner Pillen.
  Nebenbei ein alter Layout-Fehler behoben: `.content` (Grid) streckte auf
  kurzen Seiten die Zeilen auf volle Höhe → Hero-Karte und Kennzahlen-Kacheln
  waren übermäßig hoch. `align-content: start` stellt das ab.
- White-Label abgeschlossen (v0.12.0): Die Code-Defaults sind jetzt neutral
  („Adress-Zentrale", „Interner Bereich", `[Verteiler]`, Rechtstext-Gerüst,
  knapper Mail-Fuß). Bestehende Instanzwerte (Branding, `system_label`,
  Login-Überschrift, Impressum, Datenschutzerklärung, Mail-Fuß,
  Betreff-Präfix) sichert eine Seed-Migration per `INSERT IGNORE` in
  `app_settings` – die laufende Instanz bleibt nach dem Anwenden identisch.
  Neu: Branding-Felder „Login-Überschrift" und „Footer-Kürzel";
  `system_label` jetzt über die Oberfläche pflegbar (leer = nur Version im
  Footer). `config.example.php` komplett neutral. Anleitung:
  `docs/NEUE-INSTANZ.md`. **Nach dem Deploy die Migration direkt anwenden**
  (bis dahin zeigen bestehende Instanzen kurz die neutralen Defaults).
- Toter Asset-Baum entfernt (v0.11.4): Der nicht ausgelieferte Top-Level-Ordner
  `assets/` (veraltete `css/`, `js/`, leeres `uploads/`) ist gelöscht. Ausgeliefert
  wird ausschließlich `public/assets/`; `asset_url()` löst ohnehin immer gegen
  `public/` auf. `.gitignore`, `.rsyncignore` und `.dockerignore` von den
  `assets/uploads/*`-Regeln bereinigt (die `public/assets/uploads/*`-Regeln
  bleiben). README-Ordnerliste auf `public/assets/…` korrigiert.
- Mitgeliefertes Theme neutral benannt (v0.11.3): Das Datei-Theme heißt jetzt
  `themes/signalfarbe.php` / „Signalfarbe" (vorher ein instanzspezifischer
  Name, der nicht in die neutrale Distribution gehört). Look identisch
  (gleiche Token-Werte). Eine Rename-Migration zog `active_theme` bestehender
  Instanzen mit; ein Alt-Slug-Alias fing das Fenster zwischen Deploy und
  Migration ab (beide in v0.15.0 wieder entfernt, weil abgeschlossen). Wer
  einen eigenen Namen will: „Signalfarbe" kopieren, umbenennen, aktivieren.
- Theme-Bearbeiten auffindbar (v0.11.1): Auf jeder Theme-Kachel gibt es jetzt
  einen sichtbaren Bearbeiten-Zugang. Bei den Vorlagen (signalfarbe/hell/dunkel)
  heißt der Knopf „Kopieren & bearbeiten" (legt eine Kopie an und öffnet den
  Editor); eigene Themes haben „Bearbeiten". Vorher zeigte nur „Duplizieren"
  auf den Editor – ohne dass das erkennbar war. Intro-Text entsprechend
  umformuliert (und die `<strong>`-Zeilenumbrüche darin entfernt).
- Visueller Theme-Editor (v0.11.0): Der Token-Editor
  (`templates/settings/theme-edit.php`) hat jetzt zwei Spalten – links die
  Felder (jedes Farbfeld mit nativem Farbwähler + Textfeld), rechts eine
  klebende **Live-Vorschau** (Kopfleiste, Karte, Buttons, Eingabefeld,
  Badges, Mini-Tabelle) im bearbeiteten Theme, unabhängig vom aktiven Theme.
  Änderungen greifen sofort in der Vorschau, gespeichert wird erst mit „Theme
  speichern". **Kontrasthinweise** direkt am Feld (WCAG-Verhältnis, Warnung
  rot unter 4.5:1) – Rechenweg wie `readable_ink()` serverseitig, in
  `public/assets/js/theme-editor.js`. Nebenbei: ein leeres Feld lässt den
  Token jetzt unverändert (vorher: Reset auf den globalen Standard).
- Mobil-Feinschliff (v0.10.2): Blickschutz-Knopf (und die Auswahl-Aktionen)
  in der schmalen Kopfleiste jetzt nur als Icon – Beschriftung bleibt für
  Screenreader per visually-hidden erhalten, aktiver Zustand füllt den Knopf.
  Aufgeklapptes Mobil-Menü ist nur noch so hoch wie sein Inhalt (die
  Basis-Regel `height: calc(100vh …)` griff auch mobil und ließ unter
  „Abmelden" eine große leere Fläche stehen → `height: auto`).
- Dunkel-Theme Nachkontrolle (v0.10.1): Kompletter Seitendurchgang mit aktivem
  Dunkel-Theme. Gefunden und behoben: Häkchen, Radios und Slider zogen sich
  das OS-Standardblau (auf dem Rundmail-Empfängerdialog gut sichtbar) – jetzt
  global `accent-color: var(--color-secondary)` (signalfarbe Bernstein, hell
  Petrol, dunkel Hellblau). Rest (Login, Start, Kontaktliste + Blickschutz, Rundmail,
  Verwaltung, Theme-Editor, Mobil-Menü) auf Dunkel geprüft und in Ordnung.
- Dunkles Theme + Feinschliff (v0.10.0): Mitgeliefertes Datei-Theme
  `dunkel.php` (warmneutrale Dunkelfläche, Bernstein-Akzent). Beim Durchgehen
  aller Seiten gefundene und behobene Stellen: Kopfleisten-Knöpfe und der
  Hamburger („Menü") nutzen jetzt die automatische Kontrastfarbe der
  Akzentfläche statt der Textfarbe (waren auf Dunkel unlesbar); Eingabefelder
  haben eigene Tokens (`--field-bg` / `--field-border`) und heben sich in
  beide Richtungen sichtbar von der Karte ab; `--edge-light` (Glaskante) und
  der Überscroll-Bereich (`html`-Hintergrund) folgen jetzt dem Theme; `.toast`
  nutzt Primär- statt „strong"-Farbe. `themes/README.md` um einen Abschnitt zu
  dunklen Themes ergänzt.
- Theme-Feinschliff, erste Stufe (v0.9.0): `app.css` durchgehend auf Tokens
  umgestellt – Flächen, Kanten und Schatten mischen sich jetzt per
  `color-mix` aus den Basis-Tokens (`--surface-veil`, `--surface-sunken`,
  `--edge-hairline`, `--ink-shadow` …) und tragen damit auch andere
  Paletten. Neu: **automatische Schriftfarbe** auf farbigen Flächen –
  `readable_ink()` wählt Schwarz oder Weiß nach WCAG-Kontrast
  (`--color-on-primary/-danger/-accent`, vom ThemeService berechnet).
  **Favicon** ist jetzt dynamisch: abgerundete Kachel in der Akzentfarbe des
  aktiven Themes mit kontrastierender Initiale (SVG-Data-URI). Footer-Tooltip
  korrigiert: „Grüß-Zentrale" (nicht „Gruß-Zentrale").
- Theme-System (v0.8.0): Das komplette Aussehen (2 Schriften, 15 Farben,
  4 Eckenradien) steckt jetzt in benannten **Themes**. Neue Seite Verwaltung →
  „Themes" (`/settings/themes`): Kachelübersicht mit Farbstreifen, aktives
  Theme wechseln, duplizieren, eigene Themes bearbeiten (gruppierter
  Token-Editor mit Farbvorschau), umbenennen, löschen. Datei-Themes im Ordner
  `themes/` (`signalfarbe.php`, `hell.php`) bieten sich automatisch als Vorlage
  an; Doku in `themes/README.md`. Der bisherige Look heißt jetzt
  **Signalfarbe**, neuer Standard für frische Installationen ist **Hell**
  (viel Weiß, Orange-Akzent).
  „Design & Branding" ist in **Branding** (Name/Texte/Logo) und **Themes**
  aufgeteilt; die alten Farb-/Font-Felder auf der Branding-Seite sind weg.
  `app.css` in den sichtbaren Kern-Elementen (Buttons, Text, Links, Fokus,
  Umschalter, Kopfleiste) auf Tokens umgestellt. Bestehende Instanzen bleiben
  optisch unverändert (aktives Theme = `signalfarbe`).
  **Nach dem Deploy unter Verwaltung → Migrationen `2026-08-31-themes` anwenden.**
- Namensliste erweitert (v0.7.2): Filter „nur ohne Mailadresse" / „nur ohne
  Handynummer" (die Namensliste ist damit auch die Lückenliste). Versand jetzt
  zweistufig: „Als Rundmail an eine Gruppe" reicht die Liste als
  Nachrichtentext an den Rundmail-Flow weiter (Empfängerwahl alle / Kategorie /
  Tags, Vorschau, gestapelter Versand), „An diese Adressen senden" bleibt für
  wenige eingetippte Adressen. Nebenbei: `mail_draft` wird nach dem Öffnen des
  Schreiben-Dialogs aus der Session entfernt (kein Nachhall mehr).
- Kategorien & Tags verwalten (v0.7.1): eigene Seite unter Verwaltung →
  „Kategorien & Tags" (`/verwaltung/kategorien-tags`). Anlegen, Inline-
  Umbenennen (mit Dublettencheck), Löschen mit Rückfrage. Beim Löschen einer
  Kategorie verlieren betroffene Kontakte nur die Zuordnung (FK SET NULL),
  beim Löschen eines Tags nur die Tag-Verknüpfung (CASCADE). Jede Zeile zeigt
  die Anzahl zugeordneter Kontakte. Der Schnell-Anlegen-Aufklappbereich auf
  der Kontaktliste verweist auf die neue Seite.
- Namensliste (v0.7.0): `/namensliste` (Verwaltung → „Werkzeuge" und Link im
  Filterbereich der Kontaktliste). Reine Namensliste als Kopiervorlage –
  Kategorie-Filter, Sortierung Nachname/Vorname, nummeriert an/aus, editierbares
  Textfeld + Kopieren-Knopf. Verschicken per Mail an eine oder mehrere
  eingegebene Adressen (+ optional Kopie an sich selbst), mit Betreff und
  optionalem Einleitungstext; jede Zustellung landet im Versandprotokoll.
- Mobil-Navigation (v0.6.2): Auf dem Handy statt des langen Menü-Stapels eine
  schlanke, klebende Kopfleiste (☰ Menü + Name). Das Menü (Start/Kontakte/
  Rundmail/Verwaltung + Konto + Abmelden) klappt als Overlay auf und schließt
  per ☰-Knopf, Tipp daneben, Menüpunkt oder Esc. Der „Arbeitsbereich"-Kopf und
  die globale Suchleiste sind auf dem Handy ausgeblendet (Suche über Start).
  Nebenbei: `[hidden]`-Attribut wird jetzt zuverlässig durchgesetzt.
- Kontakte-Seite entschlackt (v0.6.1): Hero-Card + 3 Statistik-Kacheln raus,
  stattdessen schlanke Kopfzeile („Kontakte" + Anzahl + „Neuen Kontakt").
  Verwaltungs-Erklärtexte entfernt. Massen-/Sammelaktionen (Schnellauswahl,
  Aktionen für Auswahl, Workflow-Hinweise) in einen zugeklappten Bereich
  „Auswählen & Sammelaktionen", Spaltenauswahl in „Spalten ein-/ausblenden".
  Tabelle sitzt jetzt direkt unter Suche/Filter statt weit unten.
  Ansicht-Umschalter (Tabelle/Karten) in die Kopfzeile. CSV-Export und
  „Rundmail an diese Liste" als kompakte Buttons unter den Filtern.
- Rundmail-Bereich (v0.6.0): eigener Menüpunkt „Rundmail". Empfängerkreis
  wählen – alle mit Mailadresse / eine Kategorie / bestimmte Tags / die
  aktuelle gefilterte Kontaktliste (Link „Rundmail an diese Auswahl" im
  Filterbereich). Jede Option zeigt die Empfängerzahl. Weiter → bestehender
  Schreiben-Dialog. „Neue Mail an alle" aus dem Signalbalken entfernt (jetzt
  über Rundmail → „Alle mit Mailadresse").
- BUG behoben (v0.5.4): Kontakt mit `mailto:`-Präfix in der Adresse (Alt-
  Importdaten) ließ sich nicht speichern – das
  `<input type="email">` blockte das Absenden ohne sichtbare Meldung.
  Fix: E-Mail-Felder im Kontaktformular auf `type="text" inputmode="email"`,
  `mailto:`/`tel:`-Präfixe werden beim Speichern automatisch entfernt, und die
  Migration `2026-08-31-mailto-praefixe-bereinigen` säubert bestehende
  Datensätze. **Auf Prod unter Verwaltung → Migrationen anwenden.**
- Suche ergebnis-zuerst + Suchfeld-Typo (v0.5.3): Die Namenssuche von der
  Startseite geht jetzt auf `/search` (durchsucht auch den Ort). Die
  Suchergebnis-Seite wurde entrümpelt: schlanke Suchleiste oben, direkt
  darunter die Treffer als anklickbare Karten – keine Erklärtext-Wand mehr
  davor. Suchfeld-Schrift größer (1,4 rem) und halbfett.
- Startseiten-Feinschliff (v0.5.2): Suchfeld ist jetzt eine geschlossene
  Einheit (Rahmen nur außen, Eingabe + Knopf randlos und gleich hoch,
  Fokus-Ring auf dem Container statt sichtbarem Innenrahmen), Suchtext größer
  (1,2 rem). Die drei Schnellaktionen (Neuer Kontakt / Rundmail / Alle
  Kontakte) sind einheitlich hoch und gleich geformt; auf dem Handy volle
  Breite untereinander.
- Footer-Feinschliff (v0.5.1): „GRUEZE"/Versionsnummer saßen wegen kleinerer
  Schriftgröße einen Tick zu hoch – `.site-footer-inner` auf
  `align-items: baseline` gestellt, sitzt jetzt auf gemeinsamer Grundlinie.
- UX-Gerüst (v0.5.0): Neue ruhige **Startseite** (`/`) mit großem Suchfeld,
  Schnellaktionen und 3 Kennzahlen (gesamt / ohne Mailadresse / ohne
  Handynummer, verlinken in die gefilterte Liste). Kontaktliste auf eigene
  Route `/kontakte`. Neuer **Verwaltung**-Hub (`/verwaltung`) mit Kacheln
  statt 9 Einzel-Menüpunkten. Hauptmenü auf **Start · Kontakte · Verwaltung**
  reduziert. Mobil: kompakte horizontale Navi statt 12-Punkte-Stapel. Neue
  Kontaktfilter „ohne Mailadresse" / „ohne Handynummer".
- Datensicherung (v0.4.0): Voll-Backup aller Tabellen + Uploads als ZIP
  (manifest.json / database.json / uploads/) unter „Datensicherung"
  (/admin/backup, nur Admin). Wiederherstellung aus Backup in zwei Modi:
  „Alles ersetzen" (mit Tipp-Bestätigung) und „Nur wenn leer". Protokolle
  optional abwählbar. Merge-Modus noch offen (siehe Backlog).
- White-Label-Vorbereitung, Schritt 1 (v0.3.1): Branding-Standardwerte
  (Name, Kurzname, Links, Support-Mail, Login-/Sidebar-Texte, `system_label`
  „GRUEZE") kommen jetzt über `config('branding.*')` mit eingebautem Fallback.
  Dokumentierte `branding`-Sektion in `config/config.example.php`.
  Hartcodierte instanzspezifische Mail-/Präfix-Fallbacks in Templates entfernt.
- Blickschutz-Knopf (v0.3.0): clientseitiger Toggle im Signalbalken, der alle
  personenbezogenen Kontaktfelder (E-Mail, Telefon, Adresse, Geburtstag,
  Notizen, verknüpfte Login-Mail) in Liste, Karten, Suche und Empfängerliste
  weichzeichnet. Zustand wird pro Gerät gemerkt (localStorage), kein
  Server-Roundtrip. Bewusst nicht auf dem Bearbeiten-Formular aktiv.
- Footer-Klasse `privacy-note` → `site-footer` umbenannt und als `<footer>`
  ausgezeichnet (v0.2.8). Grund: Inhalts-/Cookie-Blocker (Filterlisten) haben
  den alten Klassennamen erkannt und Teile des Footers – inkl. Versionsanzeige –
  bei manchen Besuchern ausgeblendet. Mouse-over an der Versionsanzeige erklärt
  jetzt den Namen GRUEZE (Grüezi / „Gruß-Zentrale").
- Lokale Docker-Testumgebung eingerichtet (PHP 8.2 + Apache + MariaDB 10.11,
  siehe `docker/README.md`), angenähert an den all-inkl-KAS-Produktivstand.

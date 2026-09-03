# Offene Punkte / Backlog

Laufende Sammlung offener Ideen und Aufgaben für GRUEZE.
Wird nach jeder abgeschlossenen Arbeitseinheit aktualisiert.

## Neu

- **Gruppen (Stufen A–E + Nachträge):** A v1.1.0, B v1.2.0, C v1.3.0, D v1.4.0,
  E (Terminfindung + Gruppenleitung) v1.5.0, Beitrittsanfragen v1.6.0,
  Adressbuch-Verzahnung (Gruppen-Spalte/-Filter, Tags/Gruppen sortierbar,
  „Aus Tag/Auswahl eine Gruppe machen") v1.7.0. **Gruppen-Thema durch.**
- **Cron:** eingerichtet auf der produktiven Instanz (all-inkl, 15-Min-Aufruf
  von `/intern/cron?key=…`, Test lief ok – 2026-09-03). Anleitung für künftige
  Instanzen: Verwaltung → System → „Cronjob einrichten" (`/hilfe/cron`).
- **buymeacoffee:** das Projekt GRUEZE auf der eigenen buymeacoffee-Seite
  ergänzen (Aktion von TH). Kurztext dafür: siehe unten bzw. Chat 2026-09-03.
- **Lizenz:** Name in `LICENSE` von TH bestätigt (2026-09-03) – erledigt.

Ideen, falls es weitergeht (kein Muss – v1.0.0 ist erreicht, Backlog leer):
- Echter Screenreader-Test (VoiceOver/NVDA) an den Kern-Workflows – nur am
  Gerät machbar.
- Termine: Ranking-Variante; „Grüße automatisch am Tag" (braucht Cron).
- Reset-Token ins Pfad-Segment statt Query (kosmetische Härtung, siehe L8).
- Optionale Verschlüsselung von Backup-ZIPs / Mail-Zugangsdaten „at rest".

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
  Stand: Richtung abgestimmt (`docs/REDESIGN.md`), Redesign-Stufen +
  „Mein Eintrag" + Verwaltung-Hub + Konsistenz-Durchgang + README umgesetzt.
  Redesign damit im Wesentlichen durch. Offen aus dem Gesamt-Backlog: nur noch
  der Security-Audit.

- **Selbst-Registrierung / Account-Anlage mit niedriger Berechtigung**:
  **Stufe 1 in v0.37.0, Stufe 2 in v0.38.0** – alle drei Wege umgesetzt:
  Einladungslinks · Selbst-Anmeldung bekannte Adresse · unbekannte Adresse →
  Freigabe-Warteschlange. Passkey-Option beim Anlegen, Rate-Limit (5/Std./IP).
  Standard-Rolle Mitglied, Selbst-Anmeldung per Schalter (Standard aus).
  Ursprüngliche Anforderung zur Referenz:
  Es soll leicht sein, sich einen (niedrig berechtigten) Account anzulegen.
  Beim Anlegen legt die Person selbst ein Kennwort fest **oder** hinterlegt
  einen Passkey. Drei Wege, je nach Ausgangslage:
  1. **Einladungslink durch berechtigte Person** (Admin/Orga, ggf. per Rolle
     freischaltbar): Wenn die Mailadresse schon bekannt ist, verschickt die
     berechtigte Person – idealerweise direkt aus dem Tool – einen Link, der
     genau diesen Kontakt zur Account-Anlage berechtigt. Link ist personen-
     gebunden und einmalig / befristet.
  2. **Selbst-Anmeldung mit bekannter Adresse**: Person trägt ihre Mailadresse
     ein. Stimmt sie mit einer hinterlegten Kontakt-Mailadresse überein, ist
     die Anlage grundsätzlich erlaubt – **aber erst nach Klick auf einen
     Bestätigungslink**, der an genau diese Adresse geht (Double-Opt-in, kein
     Account ohne Mail-Bestätigung).
  3. **Selbst-Anmeldung mit unbekannter Adresse**: Adresse ist in keinem
     Kontakt hinterlegt → Anlage erst nach **Freigabe durch Admin oder Orga**
     (Freigabe-Warteschlange). Danach wie Weg 2 mit Bestätigungslink.
  Offene Punkte: welche Rolle bekommen solche Accounts (Standard-Minimalrolle,
  konfigurierbar?); Verknüpfung mit vorhandenem Kontakt vs. neuer Kontakt;
  Ablauf/Frist der Links; Missbrauchsschutz (Rate-Limit, schon vergebene
  Adresse); Zusammenspiel mit bestehendem Passwort-Reset und Passkey-Flow;
  Sichtbarkeit der Warteschlange in der Verwaltung. Sicherheitsrelevant →
  im Security-Audit mitdenken.

- **Terminfindungs-/Abstimmungstool** – **v1 in v0.31.0** (Bereich „Termine",
  Datumsabstimmung, Token-Links ohne Login, Ergebnismatrix, Ergebnis
  festlegen, Zusagen, Archiv). Offen:
  - **v0.32 – erledigt:** Token-Links per Nachricht (`{Abstimmungslink}`),
    „an Teilnehmer" mit Kreis-Filter (alle / nur Zusagen / nur Offene),
    Abstimmungs-Verlauf für Admins.
  - **v0.33 – erledigt:** Typen fester Termin + Abstimmung ohne Datum;
    offene Abstimmungen in „Mein Konto".
  - offen: Ranking-Variante andenken; Termine in „Mein Eintrag" (Handy)
    prominenter, sobald dieser Screen kommt.
  - Datenschutz-Feinheit: aktuell nur die Fremd-Link-Warnung, keine
    Mail-Bestätigung beim Abstimmen (bewusst so entschieden 2026-09).

- **„Mail ans Orga-Team"-Knopf**: **erledigt in v0.34.0** – `/orga-team`
  (`OrgaController`), Link in Seitenleiste + „Mein Konto". Ziel: feste
  Adresse `mail_orga_address` oder alle aktiven Nutzer:innen mit Rolle
  `orga.contact_target` (Standard Team+Admin). Reply-To = Login-Mailadresse.

- **Grüße-Pool mit Zufallsrotation**: **v1 in v0.35.0** – getrennte Listen
  Geburtstag/Weihnachten, CRUD, 40 Seed-Texte, Weihnachts-Serienversand mit
  Vorschau + „neu mischen" (assistiert). Offen:
  - **v0.36 – erledigt:** Geburtstagsgrüße (`/gruesse/geburtstage`, Zeitraum-
    Tabs, Vorschau + Shuffle + Einzelversand).
  - später ggf. „automatisch am Tag" (braucht Cron – aktuell kein Cron).

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

- **Security-Audit + alle Fixes → v1.0.0**: vollständiger Code-Durchgang
  dokumentiert in `docs/SECURITY-AUDIT.md` (2 hoch / 10 mittel / 12 niedrig +
  1 Bug), anschließend komplett abgearbeitet (Tabelle „Umsetzung in 1.0.0" im
  Dokument). Neue config `security.*`, `SECURITY.md`, Migration
  `2026-09-11-security-haertung` (`password_resets.created_at`). Bug B1
  (`redirect()` → `Redirect::to()`) behoben.
- Lizenz + Spendenhinweis v0.43.0: `LICENSE` = PolyForm Noncommercial 1.0.0
  (kanonischer Text von github.com/polyformproject/polyform-licenses Tag 1.0.0
  + kurzer Kopf mit Licensor/Required-Notice/Klartext-Zusammenfassung).
  README-Abschnitt „Lizenz". Helper `product_donate_url()` (config-only,
  `branding.product_donate_url`, Standard buymeacoffee.com/thomashageleit,
  `''` = aus), dezente `.hub-foot`-Zeile unten in `templates/admin/hub.php`
  (nur wenn `system_label` und `product_donate_url` gesetzt). Doku in
  `config.example.php` + `NEUE-INSTANZ.md`. Keine Migration.
- GitHub-Projektbeschreibung v0.42.0: README neu (Kurzvorstellung, Screenshot-
  Galerie, Feature-Überblick, Systemvoraussetzungen, Docker-Schnellstart,
  Shared-Hosting-Kurzanleitung, dezenter Spendenhinweis
  buymeacoffee.com/thomashageleit). Neutrale Demo-Instanz „Chor Nordwind"
  (12 Kontakte, 2 Termine) per SQL in `/tmp` – Screenshots mit playwright-core +
  gecachtem Chromium unter `docs/screenshots/*.png` (Desktop 2x + 1 mobil).
  `.rsyncignore` um `docs/`, `docker/`, Doku-`*.md` erweitert (CHANGELOG.md
  bleibt, wird von der Update-Seite gelesen). Nur Doku.
- Konsistenz-Durchgang alle Seiten v0.41.0: Login ohne floating-icon-Kasten
  (`page-head` + `panel`). Neuer einheitlicher `.page-head`/`.page-head--split`
  löst die uneinheitlichen `hero-card`/`hero-row`/`floating-icon`-Köpfe ab –
  ~24 Templates (settings/*, admin/*, auth/*, logs/*, security, mail/compose+
  status, contacts/import, legal/*, users). Jede Seite hat jetzt genau ein
  `<h1>` (vorher hatten die hero-card-Seiten keins). `strong { display:block }`
  war global → Fließtext-Fettung brach um; jetzt inline, Block nur für
  `.subsection-card > strong` & `strong:has(+ p)`. `.hero-card`-CSS + tote
  `is-*-compact`-hero-Regeln + `.floating-icon` entfernt. h1-Größe auf allen
  Kernscreens 1.8rem. Fehlende `page_title`-Einträge ergänzt. `setup/admin`
  ohne vorbelegten Namen. Keine Migration.
- Verwaltung-Hub in 3 Gruppen v0.40.0: `templates/admin/hub.php` – Gruppen
  „Zugänge · Erscheinungsbild · System" (statt Personen & Zugänge / Erscheinungs-
  bild & Texte / Werkzeuge / System). Kategorien & Tags + Grüße-Pool →
  Erscheinungsbild, Vollständigkeit → System. Kopf jetzt `<h1>Einstellungen</h1>`
  (`contact-detail-head` statt `hero-card`). `page_title('/verwaltung')` →
  „Einstellungen". Reine Template-Änderung, keine Migration.
- „Mein Eintrag" Selbst-Service v0.39.0: `/account` neu strukturiert (h1
  „Mein Eintrag", führt mit „Das haben wir zu dir" = bearbeitbares Formular für
  Stammdaten/Adresse/Kontaktwege, klebende Speichern-Leiste,
  `[data-detail-form]`). `ContactController::updateOwnProfile` +
  `sanitizeOwnProfilePayload` (POST `/mein-eintrag`) – nur eigene Felder,
  Kategorie/Tags/Notizen/Login werden aus dem Bestand übernommen, Audit als
  handelnde Person. Gate: `contacts.manage` ODER
  `canViewContactField('address', ownContact)` (Selbst-Service-Schalter); sonst
  Nur-Lese-Ansicht. `UserController::account` reicht `ownContact`/`canEditOwn`/
  `phoneLabels` durch (ContactRepository injiziert). „Mein Konto" → „Mein
  Eintrag" in allen sichtbaren Texten. Keine Migration.
- Selbst-Registrierung Stufe 2 v0.38.0: Freigabe-Warteschlange
  (`createAwaitingApproval` / `approveRequest` / `rejectRequest`, optionale
  Notiz), Passkey-Option beim Anlegen (`mode=passkey` → Account + Redirect
  `/account#passkeys`), Rate-Limit `recentCountByIp` (5/Std.). Migration
  `2026-09-10-registrierung-freigabe` (`note`, `ip_hash`).
- Footer/Seitenleiste: `<footer>` nur noch für Gäste; Impressum + Datenschutz
  als `.rail-legal` im Balken über `.rail-product` (`templates/layout/app.php`).
- Selbst-Registrierung Stufe 1 v0.37.0: `registration_invites`-Tabelle
  (Migration `2026-09-09-registrierung`), `RegistrationInviteRepository`
  (Token-Hash wie Passwort-Reset), `RegistrationController` (`/registrieren`
  GET/POST dispatch via `submit()`, `/verwaltung/registrierung` Settings +
  offene Einladungen, `/verwaltung/einladung` von der Kontakt-Detailseite).
  `SettingRepository::registrationSettings()`, `UserRepository::roleIdByName`,
  `ContactRepository::findIdByEmail`. Login-Seite + Hub verlinken.
  Nebenbei: Orga-Team-Seite „Geht ans gesamte Orga-Team." (statt Zahl).
- Geburtstagsgrüße v0.36.0: `/gruesse/geburtstage` (`GreetingController::
  birthdayForm/birthdayPreview`, `birthdaysWithin($days)`),
  `ContactRepository::withBirthdays()`, Helper `birthday_countdown()`.
  Geteilte `greetings/preview.php` (generisches `$rebuild`). Nebenbei:
  Helper `format_birth_name()` → Geburtsname als „(ehem. X)" in Adressbuch,
  Detail, Suche, Vollständigkeit.
- Grüße-Pool v0.35.0: `greetings`-Tabelle (Migration `2026-09-08-gruesse-pool`,
  40 Seeds, auch in `schema.sql` für Neuinstallationen),
  `GreetingRepository::assign()` (Bag-Shuffle), `GreetingController`
  (CRUD `/verwaltung/gruesse` + `/gruesse/weihnachten` Picker→Vorschau),
  `MailController::sendGreetings` + `mail_job['per_contact_message']` in
  `batch()`. Hub-Kachel + Link im Nachrichten-Screen.
- „Orga-Team schreiben" v0.34.0: `OrgaController` (`/orga-team`),
  Berechtigung `orga.contact_target` (Gruppe „Orga-Team"), Setting
  `mail_orga_address` (Mail-Einstellungen), `SettingRepository::orgaContactRoles/
  orgaContactAddress`, `UserRepository::activeByRoleNames`. Links in
  `.rail-orga` (Seitenleiste) + `account/index.php`. Nebenbei `.linkish`-
  Kontrastfix (globales `button{color:on-primary!important}` überschrieben).
- Termine v0.33.0: `events.kind` (Migration `2026-09-07-termin-typ`),
  Typen `date_poll`/`fixed_date`/`poll`. `EventController::applyOptions`
  (fixed_date → 1 Option + auto-`setDecidedOption`; poll → `syncTextOptions`).
  `event_option_label()`-Helper (Datum oder Freitext). Typ-Auswahl auf
  `/termine/neu`, `form.php`/`detail.php`/`vote.php`/`index.php`
  typ-abhängig. „Mein Konto": `openEventsForContact()` →
  `templates/account/index.php` offene Abstimmungen (nur wenn
  `users.contact_id` gesetzt).
- Termine v0.32.0: „an Teilnehmer" (`EventController::messageParticipants`
  + `participantContactIds($id, all|confirmed|pending)`) → Nachrichten-Screen
  mit Preset. `{Abstimmungslink}` je Person im `MailController` ersetzt
  (`applyVoteLink`, `mail_job['event_tokens']` via `EventRepository::tokensForEvent`;
  auch in `test()`). Abstimmungs-Verlauf `event_response_log` (Migration
  `2026-09-06-event-response-log`), `saveResponses` loggt nur echte
  Änderungen, `responseLog()` auf der Detailseite.
- Neuer Bereich „Termine", v1 (v0.31.0): `EventController` + `EventRepository`,
  Migration `2026-09-05-termine` (5 Tabellen). Übersicht `/termine`
  (Aktuell/Archiv), Detail (Eckdaten, Datumsoptionen `syncDateOptions`,
  Teilnehmerkreis `syncParticipants` mit Token), Abstimmen ohne Login
  `/abstimmen?token=` (Fremd-Link-Warnung, `event_token_hits` pseudonymer
  Quell-Hash → „⚠ N Quellen"), Ergebnismatrix + `setDecidedOption` + Zusagen.
  Neue Berechtigung `events.manage` (Gruppe „Termine" in den Rechten).
  Nav-Punkt „Termine" (`icon('calendar')`). `format_weekday_date()`-Helper.
- Neuer Look, Stufe 7 – Vollständigkeit (v0.30.0): `templates/contacts/completeness.php`
  + `ContactController::completeness()/shareCompleteness()/namenslisteMoved()`
  lösen `MailController::namensliste*` + `mail/namensliste.php` ab.
  `/vollstaendigkeit` (+ `?which=email|phone` von den Start-To-dos),
  `/namensliste` → Redirect. Überblickskacheln filtern die Lücken-Liste,
  je Person Bearbeiten + (mit Mail) Schreiben. Kopiervorlage + „an Gruppe"
  eingeklappt. Raw-Adressen-Versand entfällt.
- Neuer Look, Stufe 6 – Nachrichten (v0.29.0): `templates/mail/nachricht.php`
  vereint Empfängerkreis + Text auf einem Screen (`rundmail.php` entfällt,
  `rundmailStart` weg). „Alle" vorgewählt, Live-Zahl via
  `GET /rundmail/anzahl` (`MailController::recipientCount` +
  `resolveRecipientIds`). `start()`/`test()`/`saveRecipientList()` lösen
  Empfänger aus `recipient_mode` auf, wenn gesetzt. Adressbuch-Auswahl →
  `compose()` rendert für Staff jetzt `nachricht` mit Modus „selection";
  Mitglieder-Einzelkontakt bleibt auf `mail/compose`. `data-message-form`
  in app.js (Optionen-Sync, Live-Count, Betreff-Vorschau, Als-Liste-Speichern).
- Neuer Look, Stufe 5 – Kontakt-Detail (v0.28.0): `templates/contacts/detail.php`
  ersetzt `form.php` (gelöscht) für Ansehen+Bearbeiten und Neu-Anlegen.
  Ganze Seite editierbar, klebende Speichern-Leiste bei Änderung
  (`[data-detail-form]`/`[data-save-bar]` in app.js), beforeunload-Schutz.
  Notizen als „nur intern" markiert. Änderungsverlauf mit Altwerten für
  `audit.view`: `LogRepository::contactAuditTrail()` +
  `ContactController::contactChanges()`, Spalte `audit_log.changes`
  (Migration `2026-09-04-audit-changes`, `addAudit()` 5. Param). Redirect
  nach Speichern zurück auf die Detailseite.
- Adressbuch: Spalten-Menü (v0.27.0): Tags/Adresse/Geburtstag/E-Mail/
  Telefon/Login einzeln zuschaltbar (`[data-column-toggle]`, `.column-menu`
  in der `.list-bar`). Standard schlank (leeres Set), pro Gerät gemerkt
  (`grueze_visible_contact_columns`); `applyVisibleColumns` setzt
  `.columns-managed` gegen Aufblitzen.
- Neuer Look, Stufe 4 – Adressbuch (v0.26.0): `templates/contacts/index.php`
  neu. Filter reduziert (Suche + Kategorie sichtbar, Rest hinter „Filter").
  Die vier Aufklapp-Bereiche zu einem „Auswählen"-Modus (`is-selecting`,
  `data-select-mode-toggle` in app.js) mit Aktionsleiste vereint,
  Spalten-Toggle entfällt. Tabelle nur Name/Kategorie/Status, Status als
  Chip. Tabelle↔Karten für alle Rollen, pro Gerät gemerkt, am Handy Karten.
- Neuer Look, Stufe 3 – Startseite (v0.25.0): „Steht an"-To-do-Liste statt
  drei Kennzahl-Kacheln (`.start-board`/`.start-todo`), großes Suchfeld,
  zwei Schnellaktionen. Erste Seite mit echtem `<h1>`.
- Neuer Look, Stufe 1 (v0.23.0): Fraunces + Hanken Grotesk lokal eingebettet
  (`public/assets/fonts/*.woff2`, `assets/css/fonts.css`, im Layout vor
  theme.css verlinkt). Themes „Grün"/„Dunkel", `theme.css`-`:root` und
  `ThemeService::DEFAULTS` auf die neue Palette (Waldgrün statt Leuchtgrün,
  #f5f7f3-Grund, weiche Ecken). `h1–h4` global in der Display-Schrift.
  Kein HTML-Umbau – nur Tokens + Schriften. Nächste Stufen laut
  `docs/REDESIGN.md`: Seitenleiste/Kopf, dann Screens, dann „Termine".
- Neuer Look, Stufe 2 (v0.24.0): laute gruene Kopfleiste -> ruhige helle
  Topbar (Suche + Blickschutz); neue Seitenleiste (Wortmarke+Punkt,
  Mein-Eintrag-Karte, Aktiv-Streifen in Lindgruen, Verwaltung als Gruppe) in
  templates/layout/app.php + neuer CSS-Block am Ende von app.css
  (.app-shell/.app-rail/.app-topbar, ersetzt .signal-bar/.page-shell/.sidebar).
  Menue: Kontakte->Adressbuch, Rundmail->Nachrichten. Gast-Seiten ohne Chrome.
  Achtung: globales `button { color: !important }` musste fuer die Huellen-
  Knoepfe ueberschrieben werden.
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

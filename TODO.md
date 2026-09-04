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
- **buymeacoffee:** GRUEZE auf der buymeacoffee-Seite ergänzt (TH, 2026-09-03) – erledigt.
- **Lizenz:** Name in `LICENSE` von TH bestätigt (2026-09-03) – erledigt.

- **GitHub-Repo:** öffentlich seit 2026-09-03.
- **Grüße automatisch am Tag:** umgesetzt v1.10.0 (GreetingScheduler).
- **Interner Rollenschlüssel umbenennbar:** umgesetzt v1.10.0.

- **„Praktisch nützlich" – 4 Ausbaurichtungen** (von TH am 2026-09-03 komplett
  bestellt):
  1. **Termine rund machen** – umgesetzt v1.12.0: `.ics`-Kalenderdatei für
     festgelegte Termine (auch in der Mail), automatische Erinnerung X Tage
     vorher, Freitext-Anmerkung beim Abstimmen.
     Nachtrag v1.16.0: Teilnehmerkreis auch nach Tag / Gruppe auswählbar
     (Auswahlfeld „Auswahl ergänzen um alle aus …").
  2. **Datenpflege leichter** – **komplett erledigt**:
     - Archiv & Papierkorb für Kontakte (Archiv dauerhaft ODER 30-Tage-
       Papierkorb, automatische Endlöschung per Cron) – v1.13.0.
     - Daten-Check-Link (Token, ohne Login, /meine-daten) zum Prüfen und
       Korrigieren der eigenen Stammdaten/Adresse/Kontaktwege – v1.14.0.
     - Dubletten-Finder + Zusammenführen (/kontakte/dubletten) – v1.15.0.
  3. **Handy & Alltag** – teilweise:
     - Startseiten-Widget (Geburtstage der Woche + Abstimmungen mit offenen
       Rückmeldungen) – **v1.17.0 erledigt**.
     - vCard (`.vcf`)-Export einzeln / Auswahl / gefilterte Liste – **v1.18.0
       erledigt**.
     - PWA (Manifest + Icon + schlanker Service Worker, „Zum Home-Bildschirm")
       – **v1.19.0 erledigt**. Damit ist Batch 3 komplett.
  4. **Sicherheit nachziehen** – **v1.20.0 erledigt**: Mailserver-Passwörter
     „at rest" verschlüsselt (`App\Support\Crypto`, `storage/app.key`),
     Backup-ZIP optional AES-256, Reset-Token im Pfad-Segment (`/passwort-neu/…`,
     L8). **Alle vier „Praktisch nützlich"-Batches abgeschlossen.**

Ideen, falls es weitergeht (kein Muss):
- Echter Screenreader-Test (VoiceOver/NVDA) an den Kern-Workflows – nur am
  Gerät machbar.
- Termine: Ranking-Variante.
- ~~**Kontaktfeld „Beruf/Tätigkeit" + „Webseite"**~~ – **v1.34.0 erledigt.**
  Spalten `contacts.beruf` / `contacts.webseite` (Migration
  `2026-09-23-beruf-webseite`), Formular überall, Karte + „Deine
  Kontaktdaten", Änderungsverlauf, CSV, vCard (`TITLE` / `URL`).
- ~~**`contacts.geschlecht` → `contacts.anrede`**~~ – **v1.35.0 erledigt.**
  Spalte umbenannt (Migration `2026-09-24-anrede-umbenennen`, `CHANGE COLUMN
  IF EXISTS`), alle Fundstellen + Request-Feldname `anrede`. Codes `m`/`w`/leer
  unverändert (kein Wert-Remap – das wäre nur Kosmetik ohne Nutzen).
- ~~**Anmelde-Übersicht für Admin**~~ – **v1.33.0 erledigt.** Tabelle
  `user_sessions`, `/verwaltung/anmeldungen` (`SessionController`): „Gerade
  online" + Verlauf + Sitzung aus der Ferne beenden (`revoked_at`).
- ~~**IP-Adressen: Datenschutz vs. Nutzen**~~ – **v1.38.0 erledigt.**
  `login_attempts` speichert die IP jetzt immer nur gehasht; `user_sessions`
  nur bei `security.store_ip = true` (Standard aus), sonst wird die Spalte
  „Von wo" ausgeblendet und Altbestände per GC gelöscht. Retention über
  `security.session_retention_days`. **TH-Aktion offen:** `'store_ip' => true`
  in die eigene `config.php` eintragen, wenn die IPs behalten werden sollen –
  und ggf. die Datenschutzerklärung anpassen.
  Idee für später: statt roher IP eine entschärfte „bekannte/neue Quelle"-
  Anzeige (grobes Geo oder Hash-Vergleich).
- ~~**Gesendete Mails einsehen (für Sende-Berechtigte)**~~ – **v1.35.x
  erledigt.** Tabelle `sent_mails`, `/rundmail/verlauf` (`SentMailController`):
  Liste + Detail (Text, Empfänger + Zustellstatus) + „als Entwurf übernehmen"
  (alle oder einzelne). `MailController::batch` schreibt die Zeile bei
  Abschluss.
- ~~**Empfangene Mails einsehen (für Empfangs-Berechtigte)**~~ – **v1.37.0
  erledigt.** `/meine-nachrichten` (`ReceivedMailController`): Liste + Detail
  (mit aufgelöster Anrede + Fuß) + „An mich senden". Keine Migration – nutzt
  `sent_mails.recipients` (JSON-`LIKE`-Suche auf `contact_id`).

## Galerien (Fotos & Videos)

Entscheidungen TH 2026-09-04: Server-Bildverarbeitung (GD/ImageMagick),
EXIF/GPS bewusst DRIN lassen (Aufnahmezeit für Sortierung), HEIC→JPG wandeln,
Papierkorb (nur Admin sichtbar), Bilder nicht in die DB, Videos erlaubt.

- **Stufe 1 – v1.47.0 erledigt.** Galerien-CRUD, Drag-and-drop-Upload
  (Bilder + Videos, mehrere gleichzeitig, Fortschritt), GD-Thumbnails +
  Web-Größe, HEIC→JPG via `convert`, EXIF-Aufnahmezeit, Sortierung
  captured/uploaded/manual, Bildunterschrift, Titelbild, ZIP-Download,
  Lightbox, Papierkorb + GC. `storage/media/` außerhalb Webroot,
  PHP-Auslieferung mit `galleries.manage` + Range-Requests. Recht
  `galleries.manage` (Standard nur Admin). Migration `2026-09-29-galerien`.
  Nicht im ZIP-Backup (Hinweis auf der Backup-Seite).
- **Stufe 3 – Rollen & Nutzungshinweis + Sicherung – v1.48.0 erledigt.**
  Rechte `galleries.view` / `galleries.upload` / `galleries.manage` (Standard:
  Team verwaltet, Team+Mitglieder sehen+laden hoch). Uploader dürfen eigene
  Medien (via `uploaded_by`) beschriften/löschen. Nutzungshinweis (editierbar,
  `app_settings.gallery_usage_notice`) bei Ansehen/Lightbox/in jeder ZIP als
  `HINWEIS.txt`. Medien-Sicherung im System: Export „Alle Medien sichern"
  (ZIP + manifest.json) und Import (neue Galerien, kein Merge), Limit
  `media.backup_max_bytes`. „Fehler 200"-Fix (ob_start + JSON-Puffer-Discard).
- **Stufe 2 – Weitergabe-Link + QR + Auffangraum – v1.49.0 erledigt.**
  `gallery_upload_links` (Token sha+bcrypt, gallery_id NULL = Auffangraum,
  expires/max_uploads/revoke). Öffentliche Seite `/beitragen/<token>`
  (`GalleryContributeController`, kein Login, `via_link=1`, `uploaded_by=NULL`,
  Session-Cap 150). QR clientseitig (`vendor-qrcode.js`, MIT, Kazuhiko Arase).
  Auffangraum `/galerien/auffang`: Mehrfachauswahl → in Galerie/neue Galerie
  verschieben. Migration `2026-09-30-galerie-beitragen`.
- ~~**Sichtbarkeit einzelner Galerien auf Gruppen begrenzen / Galerien für
  Gruppenleitung**~~ – **erledigt (v1.51.0).** `owner_group_id` (wem gehört
  die Galerie – deren Leitung darf verwalten, auch ohne `galleries.manage`)
  + `visible_group_id` (Ansehen auf diese Gruppe eingeschränkt, NULL = normale
  globale Rechte). Gruppenleitung kann ohne globales Recht anlegen/verwalten;
  globale Verwaltung kann jede Galerie auf jede Gruppe einschränken.
- ~~**QR-Code für Alt-Links**~~ – **erledigt (v1.54.0).** Da der Klartext-
  Token nie gespeichert wird, kein Nachträglich-Anzeigen möglich – stattdessen
  „Neuer QR-Code": zieht den Link zurück, erstellt sofort einen neuen mit
  denselben Eckdaten (Route `/galerien/link/erneuern`).
- ~~**Galerie-Eigentümerschaft nachträglich übertragen**~~ – **erledigt
  (v1.54.0).** Globale Verwaltung kann `owner_group_id` jetzt frei setzen/
  ändern/aufheben (`_visibility-fields.php` + `GalleryRepository::update()`
  schreibt die Spalte jetzt mit) – Gruppenleitung selbst weiterhin nicht
  (bleibt bei Neuanlage fix, geprüft per Testfall mit manipuliertem Request).
- ~~**Sicherungs-Export als Stream statt Temp-ZIP**~~ – **erledigt (v1.56.0).**
  Neuer `App\Support\StreamZip` (eigener minimaler ZIP-Writer, STORE-Methode
  ohne Kompression) schreibt die Medien-Sicherung direkt in den HTTP-Ausgabe-
  Stream statt erst eine komplette temporäre ZIP-Datei auf der Platte
  aufzubauen – Download startet sofort, nie doppelter Plattenplatz. Kein
  ZIP64 nötig (Größen ohnehin durch `media.backup_max_bytes` gedeckelt).

## Dokumente (TH-Wunsch 2026-09-04, „Teil A")

TH-Wunsch: Orga-Team und Gruppenleitung sollen Dokumente (Word/Excel/PDF/…)
hochladen können, mit Ordnern, Beschreibung und Direktlink (weiterhin mit
Login). Rechte/Gruppen-Modell auf TH-Wunsch identisch zu den Galerien.

- **v1.52.0 erledigt.** Neuer Bereich „Dokumente". Rechte `documents.view` /
  `documents.upload` / `documents.manage` (Standard: Team verwaltet+lädt hoch,
  Team+Mitglieder sehen – anders als bei Galerien lädt „Mitglied" per Default
  NICHT hoch, nur Team/Gruppenleitung, da TH explizit „Orga-Team (und
  Gruppenleiter)" nannte). Ordner mit `owner_group_id`/`visible_group_id` –
  gleiches Muster wie die Galerien-Gruppenleitung (v1.51.0): Gruppenleitung
  legt/verwaltet eigenen Ordner ohne globales Recht, Sichtbarkeit „alle" oder
  „nur eigene Gruppe" wählbar, globale Verwaltung schränkt jeden Ordner ein.
  Eigene Uploads dürfen ohne Verwalten-Recht selbst bearbeitet/gelöscht
  werden. Direktlink = normale angemeldete URL (kopierbar), kein Token.
  **Bewusst kein Papierkorb** (wie bei Gruppen – Löschen ist endgültig).
  Dateitypen per erlaubter Dateiendung (nicht per Server-MIME-Erkennung, da
  Office-Formate auf manchen Hostern nur als „application/zip" erkannt
  werden): PDF, Word, Excel, PowerPoint, ODF, Text, CSV, RTF, ZIP, JPG/PNG –
  `config('documents.allowed_extensions')`. `storage/documents/` außerhalb
  Webroot, getrennt von Galerie-Medien, nicht im ZIP-Backup und nicht
  mitrsynct. Migration `2026-10-02-dokumente`.
- **Feiner (offen):** Unterordner/Verschachtelung; Sortierung/Suche bei vielen
  Dateien; Datei-Vorschau (aktuell öffnet der Browser die Datei direkt, PDF
  meist inline, Office-Formate meist als Download); Medien-Sicherungsknopf
  (wie bei Galerien) auch für Dokumente in der Verwaltung → Datensicherung.

## Termine & Abstimmungen getrennt (TH-Wunsch 2026-09-04, „Teil B")

TH-Frage: „Termine" war bisher Voting + Ankündigung in einem System.
TH-Entscheidungen: **vollständige Trennung** (nicht nur Umbenennung); neue
Termine-Sichtbarkeit **„alle angemeldeten Personen, mit der Möglichkeit, sie
auf Personen/Gruppen/Tag-Ebene einzuschränken – Admin sieht immer alles"**;
bestehende „Fester Termin"-Einträge **automatisch** in Ankündigungen
übernehmen.

- **v1.53.0 erledigt.**
  - **Abstimmungen** (`/abstimmungen`, war `/termine`): `EventController`/
    `EventRepository`/Tabelle `events` unverändert, nur URLs+Wortlaut
    umbenannt. `fixed_date` aus `EventController::KINDS` und dem
    Typ-Picker entfernt (nicht mehr neu anlegbar) – Lesen/Bearbeiten
    bestehender Alt-Zeilen bleibt möglich (defensiv, falls je gebraucht).
    Alt-URLs bewusst stabil gehalten: `/abstimmen?token=` (persönlicher
    Link) und `/termine/termin.ics?k=` (Kalender-Download) – beide stehen
    in bereits verschickten Mails.
  - **Termine – neu** (`AnnouncementController`/`AnnouncementRepository`,
    Migration `2026-10-03-termine-ankuendigungen`): drei Tabellen –
    `announcements` (title/info/location/starts_at/ends_at/audience_mode),
    `announcement_audience` (kind ENUM contact/group/tag + ref_id),
    `announcement_links` (label/kind ENUM extern/dokument/abstimmung/url/
    position – die URL wird beim Speichern fertig berechnet, nicht die
    Referenz-ID; beim Bearbeiten wird die ID aus der gespeicherten URL per
    Regex zurückgewonnen, um die Auswahl vorzubelegen).
  - **Sichtbarkeit:** `audience_mode` wird serverseitig aus der Auswahl
    abgeleitet (leer = `all`, sonst `restricted`) – **kein Radio-Feld
    nötig**. `AnnouncementRepository::isVisibleTo()` prüft Kontakt-ID
    direkt, Gruppen-Mitgliedschaft (`GroupRepository::forContact`) und
    Tags (neu: `TagRepository::tagIdsForContact()`). Verwaltung
    (`announcements.manage`, Standard nur `orga`) sieht immer alles, mit
    Klartext-Hinweis wer/was eingeschränkt ist (`audienceLabels()`).
    **Kein eigenes `announcements.view`** – Ansehen ist für jede
    angemeldete Person offen, nur die Einschränkung pro Ankündigung regelt
    die Sichtbarkeit.
  - **Links:** „Extern" (freie URL), „Dokument" (Picker über
    `DocumentRepository::allWithFolder()`, neu), „Abstimmung" (Picker über
    alle offenen poll/date_poll-Events; verlinkt bei Gruppen-Abstimmungen
    auf `/gruppen/abstimmung?id=` – für die Gruppe erreichbar –, sonst auf
    `/abstimmungen/detail?id=` – nur mit `events.manage` erreichbar,
    bewusst als Verwaltungs-Querverweis akzeptiert).
  - **Kein Papierkorb** (wie bei Gruppen/Dokumenten) – Löschen ist
    endgültig, cascadet auf `announcement_audience`/`announcement_links`.
  - **Migration übernimmt bestehende „Fester Termin"-Einträge automatisch**
    (Titel, Beschreibung+Uhrzeit/Kosten/Mitbringen als zusammengefasstes
    Info-Feld, erstes Options-Datum als `starts_at`) und setzt die
    Quell-Events auf `status='archived'` (Daten bleiben erhalten, im
    Abstimmungs-Archiv einsehbar).
  - **Bugfix während der Umsetzung:** `AnnouncementRepository::audienceLabels()`
    nutzte anfangs denselben Named-Placeholder (`:id`) dreimal in einer
    UNION-Query – bricht mit `PDO::ATTR_EMULATE_PREPARES=false` (Projekt-
    Standard, siehe `config.example.php`) mit „SQLSTATE[HY093]: Invalid
    parameter number". Fix: drei eigene Platzhalter. **Lehre:** named
    Placeholder in diesem Projekt nie mehrfach in derselben Query
    verwenden – entweder eigene Namen je Vorkommen oder positionelle `?`
    mit `array_fill`/`array_merge` (siehe `GalleryRepository::hasGalleriesForGroups()`).
  - Rail-Nav: „Termine" (alle, neues `calendar`-Icon-Ziel `/termine`) +
    „Abstimmungen" (nur `events.manage`, neues `poll`-Icon) statt des einen
    alten „Termine"-Links. Start-Schnellaktionen ebenso aufgeteilt
    (`$canAnnouncements`/`$canEvents`).
  - Hilfe-Seiten `orga-team.html` + `mitglied.html` an die Trennung
    angepasst (Navigation, „fester Termin" aus der Typenliste raus,
    Hinweis-Kasten zu Ankündigungen). ~~PDFs dazu noch nicht neu erzeugt.~~
    **PDFs neu erzeugt (v1.53.1).**
  - **Getestet** (Docker, curl): Migration inkl. echter Alt-Daten
    („Grillabend" aus früherer Session + eigens angelegter Testfall) korrekt
    übernommen + archiviert; Abstimmungen-Bereich unverändert nutzbar;
    Ankündigung mit allen drei Link-Typen + Gruppen+Tag-Einschränkung
    angelegt, bearbeitet, gelöscht (Cascade geprüft); Sichtbarkeit geprüft
    mit Gruppen-Mitglied (sieht es) vs. Außenstehende:r (sieht es nicht,
    404 bei Direktzugriff); Gruppen-Abstimmung-Link routet korrekt auf
    `/gruppen/abstimmung`, normale Abstimmung auf `/abstimmungen/detail`;
    alle Testdaten danach entfernt.
- **Feiner (offen):** Audience-Picker sind einfache Mehrfach-Selects ohne
  Live-Vorschau („X Personen sehen das"); Medien-/Dateibereiche (Galerien,
  Dokumente) könnten künftig auch
  an eine Termine-Ankündigung statt an eine Abstimmung verlinkbar sein
  (aktuell zeigt `galleries.event_id` weiter auf `events`/Abstimmungen).

## Account-Einladungen (TH-Fragen 2026-09-04)

**Was es schon gibt:**
- Einzelne Person: Kontakt öffnen → „Einladungslink erstellen" (oder
  Verwaltung → Selbst-Registrierung). Der Link ist an die Kontakt-Mailadresse
  gebunden, die Person legt nur Name + Passwort fest (**min. 12 Zeichen wird
  geprüft**, keine weitere Stärkeprüfung) oder Passkey. Rolle = das in
  Verwaltung → Selbst-Registrierung eingestellte `registration_default_role`
  (nie „admin"; auf „Mitglied"/Stufenmitglied stellen).
- Selbst-Anmeldung: unter `/registrieren` kann jemand die eigene bekannte
  Mailadresse eintragen und bekommt (nach Klick auf Bestätigungslink) den
  Zugang; unbekannte Adressen landen in einer Freigabe-Warteschlange.
- ~~**Sammel-Einladung**~~ – **erledigt (v1.50.0).** Verwaltung →
  Selbst-Registrierung → „Sammel-Einladung": nach Kategorie, Tag(s),
  Gruppe(n) oder „alle ohne Zugang"; zusätzlich im Adressbuch „Auswählen" →
  „Einladungen für Auswahl". Vorschau zeigt eingeladbar/übersprungen (kein
  Login-fähig, schon Zugang, Einladung schon offen) vor dem Versand;
  Häppchen-Versand mit Fortschrittsseite wie beim Rundmail-Versand.
- ~~**Admin-Benachrichtigung**~~ – **erledigt (v1.50.0).** Sobald sich
  jemand über einen Link tatsächlich einen Zugang einrichtet, geht eine kurze
  Mail an alle Zugänge mit `users.manage` (Name + Mailadresse + Link zu
  „Zugänge"). Fehler dabei stören die Registrierung nie.
- ~~**Passwort-Stärke**~~ – **erledigt (v1.54.0).** Zusätzlich zur Länge
  (min. 12 Zeichen) eine kleine Blockliste (`App\Support\PasswordPolicy`,
  neu) – häufige Wörter als Teilstring („passwort", „qwertz", …), reine
  Zahlenfolgen, ein wiederholtes Zeichen, durchgehend auf-/absteigende
  Zeichenfolgen. **Bewusst keine Zeichenklassen-Pflicht** (TH-Entscheidung).
  An allen fünf Stellen, wo ein Passwort gesetzt wird (Ersteinrichtung,
  Registrierung, Reset, eigenes ändern, Admin setzt fremdes) ersetzt.

## Berechtigungs-Matrix (TH-Frage 2026-09-04)

**Gibt es schon:** Verwaltung → Berechtigungen ist eine Matrix (Rechte-Zeilen
× Rollen-Spalten mit Häkchen); Rollen anlegen/umbenennen/löschen unter
Verwaltung → Rollen. Möglicher Ausbau: beides auf einer Seite, „neue Rolle"
inline in der Matrix, Rechte nach Bereich gruppiert sichtbar.
- **Chat für Online-Nutzer:innen** – TH hält es selbst für unwahrscheinlich,
  nur mitführen. Größter Brocken (Polling/SSE, Moderation, Datenschutz).

~~Galerie-Medien in eine separate Sicherung einbinden~~ – erledigt (v1.48.0/
v1.49.1, „Medien-Sicherung" in Verwaltung → Datensicherung).

~~**Chunked Upload, Video-Metadaten, EXIF-Original-Drehung**~~ – **erledigt
(v1.55.0).**
- **Chunked Upload für sehr große Videos:** Client teilt Dateien über
  `media.chunk_threshold_bytes` (Standard 15 MiB) in Stücke von
  `media.chunk_size_bytes` (Standard 4 MiB) und lädt sie einzeln hoch – so
  reicht die Standard-`php.ini` vieler Shared-Hoster auch für sehr große
  Videos, ohne `upload_max_filesize`/`post_max_size` anheben zu müssen.
  Drei neue Endpunkte (`/galerien/chunk/start|teil|abschliessen`,
  `GalleryController`), Sitzungsverwaltung dateibasiert unter
  `storage/tmp/chunks/<id>/` (`MediaService`, kein neues DB-Schema nötig).
  Abgebrochene Sitzungen räumt `pruneStaleChunkSessions()` im bestehenden
  GC-Block auf (24 h). Zunächst nur der eingeloggte Galerie-Upload –
  ~~öffentlicher Beitrags-Link noch nicht angepasst~~ **erledigt (v1.56.0):**
  eigene, tokenbasierte Chunk-Route in `GalleryContributeController`
  (`/beitragen/<token>/chunk/start|teil|abschliessen`), Sitzungsbindung
  über `link_id` statt `user_id`, geprüft per echtem anonymem Roundtrip
  (kein Login, Cross-Token-Hijack-Versuch korrekt mit 404 abgewiesen).
- **Video-Dauer/-Maße serverseitig:** reines PHP, kein ffmpeg/getID3 nötig –
  Hand-Parser für MP4/MOV-Boxen (`moov`/`mvhd`/`trak`/`tkhd`) in
  `MediaService::readMp4Meta()`. ~~WebM bleibt ohne Metadaten~~ **erledigt
  (v1.56.0):** eigener EBML-Parser (`readWebmMeta()`) für `Segment`/`Info`/
  `Tracks`/`TrackEntry`/`Video`. Dauer zeigt sich als Badge auf dem
  Video-Vorschaubild (`format_duration()`-Helfer, neu).
- **Bild-Rotation aus EXIF fürs Original:** bisher nur die Vorschau-Varianten
  gedreht, jetzt auch das Original selbst (`MediaService::rotateOriginalIfNeeded()`,
  nur JPEG). GD verwirft beim Neu-Speichern alle Metadaten inkl. des
  Orientation-Tags – das Original braucht danach keine Dreh-Korrektur mehr
  durch den Browser, Breite/Höhe werden nach der Drehung neu gelesen.

## UX-Review – Umsetzung (Entscheidungen TH 2026-09-04)

Vollständige Analyse: `docs/UX-REVIEW.md`. Entscheidungen:
1. Verwaltungs-Hub neu gruppieren → **ja** (4 Gruppen).
2. Startseiten rollenspezifisch → **ja**, aber Aufgabenliste je Rolle wird
   danach einzeln mit TH abgestimmt.
3. Gruppenleitung → **haben wollen**, Ziel: „so einfach wie möglich, eine
   Gruppe anschreiben und abstimmen".
4. Betrachter-Rolle → **streichen**.

- **Sofort-Paket (A1, A2, B1, B2, B4-Teil) + Betrachter raus** – **v1.21.0
  erledigt**.
- **Verwaltungs-Hub 4 Gruppen (B3)** – **v1.22.0 erledigt** (+ Cron-Kachel →
  bedingter Hinweisstreifen via `scheduler_stale()`).
- **Gruppenleitungs-Einstieg (C1)** – **v1.23.0 erledigt**: Abschnitt „Gruppen,
  die du leitest" auf `/gruppen` mit Groß-Knöpfen „Nachricht schreiben" +
  „Abstimmung starten", Beitrittsanfragen direkt auf der Karte.
- **Startseiten rollenspezifisch (C4 + C2)** – **v1.24.0 erledigt.** Rollen
  mit TH abgestimmt: Admin/Orga = „Steht an" (Abstimmungen Frist-zuerst, dann
  Datenlücken) + Schnellaktionen inkl. „Neuer Termin". Mitglied = „Deine
  offenen Abstimmungen" + Meine Daten/Orga-Team. Gruppenleitung = Mitglied +
  „Deine Gruppen".
- **C2 (Rail-Reihenfolge „Mein Eintrag" rollenabhängig)** – bewusst offen
  gelassen; „Mein Eintrag" oben ist für Mitglieder richtig, für Verwalter nur
  ein kleiner Schönheitsfehler. Bei Bedarf später.

**Der UX-Review-Block ist damit weitgehend abgearbeitet** (A1–A2, B1–B4,
C1/C3/C4). Rest siehe unten „lets discuss": Ansprache, Kommentare, Refactoring.

**Nachtrag Navigation (v1.39.0, nach TH-Rückfrage 2026-09-04):** Seitenleiste
entstaubt – „Nachricht schreiben" statt „Nachrichten", eigene Icons je
Mail-Eintrag + für Gruppen. Hub: neue Gruppe „Protokolle & Verlauf" (Gesendete
Nachrichten / Änderungsprotokoll / Versandprotokoll / Anmeldungen) aus „System"
herausgelöst. Offener Feinschliff-Gedanke für später: entschärfte
„bekannte/neue Quelle"-Anzeige statt roher IP; „Mein Eintrag" in der Rail für
Verwalter tiefer einsortieren.

## Zur Entscheidung – „lets discuss" (2026-09-04, noch NICHT beauftragt)

Themen, die TH zur Diskussion gestellt hat. Thema 4 (UX/IA-Review) ist
entschieden und in Umsetzung (siehe Abschnitt oben). Die übrigen drei erst
besprechen, dann entscheiden. Ausführliche Einschätzung im Chat vom 2026-09-04.
Reihenfolge, falls alle kommen: nach dem UX-Umbau, damit sie den aufgeräumten
Stand betreffen.

1. ~~**Genderneutrale/inklusive Ansprache**~~ – **v1.25.0 erledigt.** House-
   Style (TH): Neutralformulierung > Doppelpunkt > kein Sternchen. „Geschlecht"-
   Feld → „Anrede" (Neutral/Liebe/Lieber), „Teilnehmer" → „Teilnehmende",
   „Benutzer" → „Zugänge/Konto" usw. Stil in `docs/SPRACHE.md`. Spalte
   `geschlecht` → `anrede` mit v1.35.0 nachgezogen.
2. ~~**Code-Kommentierung** + `ARCHITECTURE.md`~~ – **v1.26.0 erledigt.**
   `ARCHITECTURE.md` neu (Lebenszyklus, Datenmodell, Migrations-/ensureSchema-
   Regeln, „neue Seite"-Checkliste); `MailService` + `WebAuthnService`
   kommentiert. Offen bei Bedarf: `app.js` (Abschnittsdoku), die großen
   Controller.
3. **Refactoring / Verschlankung** – **läuft, in kleinen Schritten:**
   - v1.27.0: Kontakt-Payload-Logik (war 3× kopiert) → `App\Support\ContactInput`;
     ein paar tote CSS-Regeln raus.
   - ~~**großer Dead-CSS-Durchgang**~~ – **v1.28.0 erledigt.** Alle toten
     Cluster aus `app.css` raus (`.signal-bar*`, `.page-shell`, `.sidebar*`,
     `.content-topbar`, `.rundmail-*`, `.account-panel/-summary/-badge`,
     `.filter-grid`/`.stats-grid`, `.contact-meta-list`, `.tag-account`,
     `.branding-color-grid`, `is-*-compact`, `.group-member-list` u. a.),
     inkl. der zugehörigen Media-Query-Blöcke. Datei ~6.500 → 5.455 Zeilen,
     CSS ~145 KB → ~110 KB. Sieben Seiten Desktop+mobil geprüft, Optik
     unverändert.
   - **fette Dateien aufteilen** – **läuft:**
     - v1.29.0: `ContactController` 970 → ~400 Z.; raus in
       `ContactArchiveController`, `ContactPortController`,
       `CompletenessController`, `LinkedAccountService`, `ContactDiff`,
       `ContactFieldRedactor`.
     - v1.30.0: `MailController` 826 → ~500 Z.; raus in
       `MailRecipientResolver`, `MailComposer`, `RecipientListController`,
       `JsonResponse`.
     - v1.31.0: `templates/contacts/index.php` 685 → ~235 Z.; Blöcke in
       `templates/contacts/_index/` (own-contact/toolbar/table/cards/
       bulk-edit), neuer Helfer `view_partial()`.
     - v1.32.0: Dubletten/Merge (~275 Z.) aus `ContactRepository` in
       `ContactMergeService`. Repo 1120 → ~845 Z.
     - **Controller + Haupttemplate alle < ~500 Z.** Größte Dateien jetzt:
       `EventRepository` 988 Z., `ContactRepository` 845 Z.,
       `SettingRepository` 616 Z., `app.js`. Weiter nur bei konkretem Bedarf –
       Repos sind kohäsiv (eine Tabelle, viele Abfragen), Aufteilen bringt
       dort weniger als bei den Controllern.

- **Design-/UX-Überarbeitung (Rolle: Design- und UX-Agentur)**: Navigation

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
  - ~~**Teil B (TH-Wunsch 2026-09-04): Termine/Abstimmungen trennen**~~ –
    **erledigt (v1.53.0).** Vollständige Trennung wie entschieden:
    „Abstimmungen" (`/abstimmungen`, unverändertes poll/date_poll-Verhalten)
    + neu „Termine" (`/termine`, reine Ankündigungsseite mit Sichtbarkeits-
    Einschränkung + Links). Bestehende „Fester Termin"-Einträge automatisch
    übernommen. Details siehe Abschnitt „Dokumente" unten (Teil A) und die
    Projekt-Memory. Hilfe-Seiten (`orga-team.html`, `mitglied.html`) an die
    neue Trennung angepasst – PDFs neu erzeugt (v1.53.1).

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

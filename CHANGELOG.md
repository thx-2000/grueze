# Changelog

Kurzüberblick je Version. Nach einem Datei-Upload bringt
**Verwaltung → Aktualisieren** die Datenbank auf den passenden Stand.

## 1.47.0

**Neu: Galerien (Fotos & Videos).** Stufe 1 – vorerst nur für die Verwaltung.

- **Galerien anlegen** mit Titel, Beschreibung, optionalem Datum und
  optionaler Verknüpfung zu einem Termin.
- **Bilder und Videos hochladen** per Drag-and-drop (mehrere gleichzeitig,
  mit Fortschrittsanzeige). Serverseitig entstehen Vorschau- und Web-Größen
  (GD); das Original bleibt unangetastet, inklusive EXIF/Aufnahmezeit.
- **HEIC** (iPhone-Standard) wird beim Upload zu JPG umgewandelt, wenn der
  Server ImageMagick hat. Videos: das Vorschaubild zieht der Browser aus dem
  ersten Frame.
- **Sortierung** wahlweise nach Aufnahmezeit, Upload-Reihenfolge oder manuell
  (Ziehen). Bildunterschriften, Titelbild wählen, **ganze Galerie als ZIP**.
- **Lightbox** zum Durchblättern (Tasten ←/→/Esc), Einzel-Download.
- **Papierkorb** (nur Admin) mit automatischer Endlöschung inkl. Dateien.
- Neue **Medien liegen unter `storage/media/`** (außerhalb des Webroots,
  Auslieferung nur mit Rechteprüfung, Video-Seeking via Range-Requests) und
  sind **nicht im ZIP-Backup** – separat sichern (Serverbackup).
- Neues Recht **`galleries.manage`** (Standard: nur Admin) – die feinere
  Rollenverteilung folgt in einer späteren Stufe.

**Migration `2026-09-29-galerien`.** Optional in `config/config.example.php`:
Größenlimits, Papierkorb-Frist, `convert`-Pfad. Für Video-Uploads über ~30 MB
braucht der Webspace eine eigene `php.ini` mit größerem `upload_max_filesize`.

## 1.46.0

**Adressbuch: ganze Zeile klickbar + Archiv leichter zu finden.**

- In der Adressliste (Tabelle **und** Karten) öffnet jetzt ein Klick auf
  die ganze Zeile bzw. Karte die Kontakt-Detailseite – nicht mehr nur der
  Pfeil rechts. Klicks auf Links, Buttons, Checkboxen usw. bleiben davon
  unberührt; Text lässt sich weiter markieren; im Auswahl-Modus schaltet ein
  Klick die Auswahl um. Der Pfeil/„Bearbeiten"-Link bleibt für die
  Tastaturbedienung.
- **„Archiv & Papierkorb"** hat jetzt eine eigene Kachel unter
  **Verwaltung → Inhalt & Struktur** (bisher nur im „Datenpflege"-Menü des
  Adressbuchs versteckt).

## 1.45.1

**Hotfix zur Migration `2026-09-27`.** `RANDOM_BYTES()` gibt es erst ab
MariaDB 10.10 – auf älteren Servern brach „Aktualisieren" mit
„FUNCTION … RANDOM_BYTES does not exist" ab. Die Migration nutzt jetzt
`SHA2(CONCAT(UUID(), RAND(), id))` (überall verfügbar). Wer v1.45.0 schon
ohne Fehler aktualisiert hat, braucht nichts zu tun.

## 1.45.0

**Sicherheits-Härtung aus dem zweiten Audit.**

- **Token im Pfad statt im Query.** Der Daten-Check-Link (`/meine-daten/…`)
  und der Einladungslink (`/registrieren/…`) tragen ihren Token jetzt im
  Pfad – so landet er nicht mehr in Server-Logs, Browser-Verlauf oder
  Referrer-Headern (wie schon beim „Passwort vergessen"-Link). Alte Links im
  `?token=`-Format funktionieren weiter.
- **Passwortwechsel beendet fremde Sitzungen.** Wer sein Passwort ändert
  (oder es per „Passwort vergessen" bzw. durch eine:n Admin neu gesetzt
  bekommt), meldet damit alle anderen Geräte/Browser ab. Die eigene aktuelle
  Sitzung bleibt.
- **Einladungslinks: schnellerer, DoS-fester Abgleich.** Statt für jeden
  Aufruf bis zu 50 bcrypt-Vergleiche zu rechnen, findet ein Index die
  passende Einladung direkt (neue Spalte `registration_invites.token_sha`).
- **Orga-Team-Knopf mit sanftem Limit** gegen versehentliches Doppel-Senden
  und Spam (Mindestabstand + Stundenobergrenze).
- **Kalender-Schlüssel neu gewürfelt.** `events.ical_uid` wird für laufende
  Termine auf einen echten Zufallswert gesetzt (frühe Bestände kamen aus
  `UUID()` und waren in Grenzen erratbar). Wer den Kalender-Link eines
  laufenden Termins gespeichert hat, muss ihn einmalig neu kopieren.
- **Kleinkram:** Backup-Wiederherstellung mit Größengrenze gegen
  „ZIP-Bomben"; XLSX-Import prüft `is_uploaded_file`; protokollrelative
  Links (`//fremde-domain`) in Rechtstexten werden entfernt; Passwort-Hash
  wird nicht mehr in den View-/Listen-Kontext gereicht.

## 1.44.0

**Nur Doku: zweiter Sicherheits-Audit.**

- Vollständiger Code-Durchgang der seit v1.0.0 dazugekommenen Bereiche
  (Termine, Gruppen, Daten-Check-Link, Sitzungen, gesendete/empfangene Mails,
  Suche, …). Ergebnis in `docs/SECURITY-AUDIT.md`, Abschnitt „Zweiter
  Durchgang".
- Kein Einfallstor ohne Konto/Token gefunden. Die Befunde sind Härtung bzw.
  Missbrauchs-/DoS-Themen; Umsetzung folgt in weiteren Releases.

## 1.43.0

**Suche durchsucht jetzt alle sichtbaren Felder.**

- Die globale Suche (Startseite und Kopfleiste) findet Treffer nicht mehr nur
  in Name, Geburtsname und Ort, sondern in **allem, was die eigene Rolle
  sehen darf**: Kategorie, Tags, Gruppen, Beruf, Webseite – und, falls für
  die Rolle sichtbar, Adresse, E-Mail, Telefon und Notizen.
- Jeder Treffer zeigt, **wo** der Begriff gefunden wurde („Webseite: …",
  „E-Mail: …").
- Die Ergebnisliste lässt sich direkt **eingrenzen** – nach Kategorie und
  nach Fundstelle (Name / Adresse / E-Mail / …).

## 1.42.0

**Feinschliff: Icons und Auswahlfelder.**

- Icons in Link-Buttons (z. B. die Werkzeugleiste im Adressbuch) saßen zu
  tief – sie sind jetzt sauber mittig zum Text ausgerichtet und einen Tick
  größer. Gilt in allen Ansichten und Rollen.
- Auswahlfelder (`<select>`) zeigen nicht mehr die klobige System-Darstellung
  (auffällig in Safari), sondern haben dieselbe Höhe und Optik wie ein
  Textfeld, mit einem dezenten eigenen Pfeil.

## 1.41.0

**„Kreide" ist der neue Standard.**

- Das Theme **Kreide** wird beim Update aktiviert und ist ab jetzt der
  Standard für neue Installationen. Jederzeit unter Verwaltung → Themes
  wieder umstellbar (auch auf ein eigenes).
- **Terrakotta** überarbeitet: die Aktionsfarbe ist jetzt ein gedecktes
  Ton-/Ziegelrot statt eines Orange, insgesamt weniger orange-lastig.
- Migration `2026-09-26-theme-kreide` – nach dem Upload
  **Verwaltung → Aktualisieren**.

## 1.40.0

**Zwei neue Farbwelten zur Auswahl.**

- **Terrakotta** – warme Erdtöne (Sand, Ton, Espresso) mit gebranntem Orange
  als einziger Signalfarbe.
- **Kreide** – fast weiße, kühle Flächen und kräftige, matte Farben für
  wichtige Elemente (Petrol / Ziegelrot / Amber), etwas schärfere Ecken.

Beide erscheinen unter **Verwaltung → Themes**. Zum Ausprobieren „Aktivieren",
zum Anpassen „Kopieren & bearbeiten". Alle Farbkontraste erfüllen WCAG AA.
Der bisherige Standard („Grün") bleibt unverändert aktiv.

## 1.39.0

**Navigation aufgeräumt – nichts entfernt, nur klarer sortiert.**

- **Seitenleiste:** „Nachrichten" heißt jetzt „Nachricht schreiben"; die drei
  Mail-Einträge (Schreiben / Erhaltene Mails / Orga-Team) haben jeweils ein
  eigenes Icon, „Gruppen" ein eigenes. Kein doppelter Briefumschlag mehr.
- **Einstellungen:** Neue Gruppe **„Protokolle & Verlauf"** – Gesendete
  Nachrichten, Änderungsprotokoll, Versandprotokoll und Anmeldungen sind aus
  „System" dorthin gewandert. „System" enthält jetzt nur noch Aktualisieren
  und Datensicherung.
- Keine Migration.

## 1.38.0

**Datenschutz: IP-Adressen sparsamer.**

- **Login-Versuche** speichern die Herkunft ab sofort nur noch **pseudonym
  (gehasht)** – reicht fürs Rate-Limit, die IP selbst wird nirgends gebraucht
  oder angezeigt. (Altbestände verschwinden nach 30 Tagen.)
- **Angemeldete Sitzungen** (Verwaltung → Anmeldungen) speichern die IP nur
  noch, wenn `security.store_ip` in der `config.php` auf `true` steht –
  Standard ist **aus**. Ist der Schalter aus, wird die Spalte „Von wo"
  ausgeblendet und vorhandene IPs werden bei der nächsten Aufräumrunde
  entfernt.
- Neue optionale Einstellungen: `security.store_ip` (Standard `false`) und
  `security.session_retention_days` (Standard 90).
- Keine Migration. **Wer die IPs in „Anmeldungen" behalten möchte, trägt
  `'store_ip' => true` im `security`-Block der `config.php` ein.**

## 1.37.0

**Neu: „Erhaltene Mails" – jede Person sieht die Rundmails, die an sie gingen.**

- Wer einen verknüpften Adressbuch-Eintrag hat, findet unter **Erhaltene
  Mails** (Seitenleiste bzw. „Mein Eintrag") die Serien-Mails wieder, die an
  ihn verschickt wurden – mit aufgelöster Anrede und aktuellem Mail-Fuß, so
  wie sie im Postfach ankamen.
- Pro Nachricht ein Knopf **„An mich senden"**: schickt sie erneut an die
  eigene Login-Mailadresse.
- Keine Migration – nutzt die mit 1.36.0 eingeführte Tabelle `sent_mails`.
  Sichtbar werden nur Nachrichten, die ab 1.36.0 verschickt wurden.

## 1.36.0

**Neu: „Gesendete Nachrichten" – Verlauf des Serienversands.**

- Unter **Nachrichten → Gesendete Nachrichten** (Recht „Nachrichten senden")
  sehen Sende-Berechtigte alle früheren Rundmails wieder: Betreff, Text,
  Empfängerkreis mit Zustellstatus, Zeitpunkt und wer sie verschickt hat.
- Aus dem Detail lässt sich eine Nachricht **erneut verschicken** – an alle
  noch vorhandenen Empfänger oder nur an einzelne ausgewählte Personen. Der
  Text landet als Entwurf auf der Schreiben-Seite; abgeschickt wird erst dort.
- Jeder abgeschlossene Serienversand schreibt automatisch einen Eintrag.
  Aufbewahrung standardmäßig 365 Tage (`mail.sent_retention_days`).
- Migration `2026-09-25-gesendete-mails` (Tabelle `sent_mails`) – nach dem
  Upload **Verwaltung → Aktualisieren**.

## 1.35.0

**Nur Aufräumen – kein sichtbarer Unterschied.**

- Die Datenbankspalte `contacts.geschlecht` heißt jetzt `contacts.anrede` –
  passend dazu, wie das Feld in der Oberfläche schon lange genannt wird. Die
  gespeicherten Werte und das Verhalten (Brief-Anrede „Lieber"/„Liebe"/
  „Hallo") ändern sich nicht.
- Migration `2026-09-24-anrede-umbenennen` – nach dem Upload
  **Verwaltung → Aktualisieren**. Ältere Backups mit dem alten Spaltennamen
  lassen sich weiterhin einspielen.

## 1.34.0

**Neu: zwei Kontaktfelder – „Beruf/Tätigkeit" und „Webseite".**

- Beide erscheinen in den Stammdaten – in der Verwaltung, in „Mein Eintrag"
  und im Daten-Check-Link. Eine bloße Domain in „Webseite" wird automatisch
  zu `https://…` ergänzt.
- Anzeige auf der Kontaktkarte und unter „Deine Kontaktdaten"; im
  Änderungsverlauf, im CSV-Export und in der vCard (`TITLE` / `URL`).
- Migration `2026-09-23-beruf-webseite` – nach dem Upload
  **Verwaltung → Aktualisieren**.

## 1.33.0

**Neu: Anmelde-Übersicht für die Verwaltung.**

- Unter **Verwaltung → Anmeldungen** (Recht „Zugänge verwalten") sieht man,
  wer gerade online ist (Name, Rolle, seit wann, zuletzt aktiv, IP, Gerät)
  und einen Verlauf der letzten Sitzungen (online / abgemeldet / aus der
  Ferne beendet / abgelaufen).
- Jede fremde Sitzung lässt sich per **Beenden** aus der Ferne abmelden –
  beim nächsten Aufruf wird sie ausgeloggt.
- Dafür schreibt die App pro Browser-Sitzung eine Zeile mit (nur der
  SHA-256-Hash der Session-ID wird gespeichert, nicht die ID selbst). Ältere
  Einträge verschwinden nach 90 Tagen automatisch.
- Migration `2026-09-22-anmelde-uebersicht` legt die Tabelle `user_sessions`
  an – nach dem Upload **Verwaltung → Aktualisieren**.

## 1.32.0

**Nur Umbau unter der Haube – kein sichtbarer Unterschied, keine Migration.**

- Dubletten-Finder und Zusammenführen lagen mit ~275 Zeilen im
  `ContactRepository` (Clustering per Union-Find, eine mehrstufige
  Transaktion über ein Dutzend Tabellen). Jetzt in `ContactMergeService`
  ausgelagert. `ContactRepository` schrumpft von ~1120 auf ~845 Zeilen.
- Verhalten unverändert. Getestet: Doppel-Einträge finden (Zähler +
  Übersicht) und zwei Kontakte zusammenführen inkl. Änderungsverlauf.

## 1.31.0

**Nur Umbau am Adressbuch-Template – kein sichtbarer Unterschied, keine Migration.**

- `templates/contacts/index.php` war mit ~685 Zeilen die letzte fette Datei.
  Die großen Blöcke liegen jetzt als Teil-Templates unter
  `templates/contacts/_index/`: `own-contact` (eigener Kontakt),
  `toolbar` (Filterleiste + Werkzeuge), `table` (Tabellenansicht),
  `cards` (Kartenansicht), `bulk-edit` (Sammelbearbeitung).
- Neuer Helfer `view_partial()` – bindet ein Teil-Template mit eigenem
  Scope ein.
- `index.php` selbst: ~235 Zeilen (Seitenkopf, Ansichts-/Auswahl-Leiste,
  gemeinsames Auswahl-Formular).
- HTML-Ausgabe unverändert (Struktur 1:1 geprüft), alle interaktiven Teile
  (Auswählen-Modus, Spalten-Menü, Sammelbearbeitung, Filter) getestet.

## 1.30.0

**Nur Umbau unter der Haube – kein sichtbarer Unterschied, keine Migration.**

Der `MailController` (~830 Zeilen) ist entzerrt:

- **`MailRecipientResolver`** (Service) – „wer bekommt die Nachricht": den
  gewählten Empfängerkreis (alle / Filter / gespeicherte Liste / Kategorie /
  Tags / Auswahl) in Kontakt-IDs auflösen, dazu die Anzeigetexte.
- **`MailComposer`** (Service) – „wie ist die Mail adressiert und
  unterschrieben": Absender, Antwortweg, Betreff-Präfix, Anrede-Modus,
  Mail-Fuß und der „eingeschränkte Kontaktaufnahme"-Modus.
- **`RecipientListController`** – gespeicherte Empfängerlisten anlegen,
  umbenennen, löschen.
- **`JsonResponse`** (Support) – kleiner Helfer für die fetch()-Endpunkte.
- `MailController` schrumpft auf ~500 Zeilen (Schreiben, Testmail, Versand
  in Häppchen).
- Alle URLs unverändert. Getestet: Empfängerkreis-Wahl, Live-Zahl,
  Empfängerliste speichern/umbenennen/löschen, Schreiben-Seite.

## 1.29.0

**Nur Umbau unter der Haube – kein sichtbarer Unterschied.**

- Der `ContactController` war mit ~970 Zeilen die dickste Datei im Projekt und
  vermischte Adressbuch-CRUD, Archiv/Papierkorb, Dubletten, Import/Export und
  Vollständigkeit. Jetzt aufgeteilt:
  - `ContactArchiveController` – Archiv, Papierkorb, Wiederherstellen,
    endgültig löschen, Dubletten-Finder, Zusammenführen.
  - `ContactPortController` – XLSX-Import, CSV- und vCard-Export.
  - `CompletenessController` – die Seite „Vollständigkeit".
  - `LinkedAccountService` – den optional an einem Kontakt hängenden Login
    anlegen/aktualisieren/deaktivieren.
  - `ContactDiff` / `ContactFieldRedactor` (Support) – Änderungsverlauf bzw.
    das Ausblenden gesperrter Felder, vorher doppelt im Controller.
  - `ContactController` selbst schrumpft auf ~400 Zeilen (Liste, Anlegen,
    Bearbeiten, „Mein Eintrag", Sammeländerung, Gruppe aus Auswahl).
- Alle URLs unverändert. Getestet: Bearbeiten mit Änderungsverlauf, Archiv →
  Wiederherstellen, vCard-Export, „Liste teilen".

## 1.28.0

**Nur Stylesheet – nichts an der Anwendung geändert.**

- Großer Dead-CSS-Durchgang in `app.css`: alle Regeln der abgelösten Hülle
  (`.signal-bar*`, `.page-shell`, `.sidebar*`, `.content-topbar`) und weiterer
  nicht mehr genutzter Bausteine (`.rundmail-*`-Empfängerauswahl,
  `.account-panel/-badge/-summary`, `.contact-meta-list`, `.tag-account`,
  `.branding-color-grid`, `.status-icon`, Kompakt-Varianten der Kontaktliste,
  `.group-member-list` u. a.) entfernt – inklusive der zugehörigen
  Media-Query-Blöcke.
- Datei von rund 6.500 auf 5.455 Zeilen geschrumpft, geladenes CSS von
  ~145 KB auf ~110 KB. Optik und Verhalten unverändert (Start, Adressbuch,
  „Mein Eintrag", Nachrichten, Verwaltung, Termine, Gruppen geprüft –
  Desktop und mobil).

## 1.27.0

**Aufräumen unter der Haube – kein sichtbarer Unterschied.**

- Die Aufbereitung der Kontakt-Formularfelder (E-Mail-/Telefon-Zeilen säubern,
  Stammdaten) lag dreifach kopiert vor (Verwaltung, „Mein Eintrag",
  Daten-Check-Link). Jetzt an einer Stelle: `App\Support\ContactInput`.
- Ein paar seit dem letzten Umbau tote CSS-Regeln entfernt (die alte
  „Steht an"-Liste, Reste der alten Topbar).

## 1.26.0

**Nur Doku – nichts an der Anwendung geändert.**

- `ARCHITECTURE.md` komplett überarbeitet: Request-Lebenszyklus, Container/
  Router, Datenmodell (alle aktuellen Tabellen), Migrations- und
  `ensureSchema`-Regeln, at-rest-Verschlüsselung, PWA, plus eine Checkliste
  „Eine neue Seite hinzufügen".
- `MailService` und `WebAuthnService` (die beiden kniffligsten Dateien –
  handgeschriebener IMAP-Client bzw. WebAuthn ohne Library) sind jetzt
  ausführlich kommentiert.

## 1.25.0

**Inklusive, geschlechtsneutrale Sprache**

- Sichtbare Texte verwenden jetzt durchgehend neutrale Formen:
  „Teilnehmende" statt „Teilnehmer", „Zugänge/Konto" statt „Benutzer",
  „abgestimmt wird" statt „jede:r stimmt ab". Kein Gendersternchen; wo keine
  gute Neutralform existiert, ein Doppelpunkt.
- Das Kontaktfeld **„Geschlecht (Männlich/Weiblich)"** heißt jetzt **„Anrede"**
  mit den Auswahlmöglichkeiten *Neutral – „Hallo …"* / *„Liebe …"* /
  *„Lieber …"*. An den Daten ändert sich nichts.
- Der Stil ist in `docs/SPRACHE.md` festgehalten.

## 1.24.0

**Startseite je nach Rolle (UX Teil 4)**

Die Startseite zeigt jetzt für jede Rolle das Passende:

- **Admin & Orga:** „Steht an" führt Abstimmungen mit fehlenden Rückmeldungen
  zuerst (die mit der nächsten Frist ganz oben, „bald fällig"-Markierung),
  danach Datenlücken. Schnellaktionen: Person hinzufügen · Neuer Termin ·
  Nachricht schreiben.
- **Mitglied:** „Deine offenen Abstimmungen" (wo die Rückmeldung fehlt oder
  noch änderbar ist, mit Frist) und die Knöpfe „Meine Daten" / „Orga-Team
  schreiben".
- **Gruppenleitung:** zusätzlich „Deine Gruppen" mit offenen Beitrittsanfragen.
- **Geburtstage diese Woche** für alle, die das Geburtstagsfeld sehen dürfen.

## 1.23.0

**Gruppenleitung: alles Wichtige auf einen Blick (UX Teil 3)**

- Wer eine Gruppe leitet, findet auf „Gruppen" jetzt ganz oben den Abschnitt
  **„Gruppen, die du leitest"** – mit den zwei Kernaktionen als großen Knöpfen:
  **Nachricht schreiben** und **Abstimmung starten**. Kein Umweg über
  „Verwalten" mehr.
- Offene Beitrittsanfragen werden direkt auf der Karte angezeigt und verlinkt.

## 1.22.0

**Einstellungen neu sortiert (UX Teil 2)**

- Der Verwaltungs-Bereich ist von drei auf **vier klarere Gruppen** umgestellt:
  *Zugänge & Rollen*, *Inhalt & Struktur*, *Aussehen & Texte*, *System*.
  „Vollständigkeit" liegt jetzt bei den inhaltlichen Werkzeugen, nicht mehr
  unter „System".
- Die Kachel „Cronjob einrichten" ist weg. Stattdessen erscheint oben ein
  Hinweisstreifen – aber nur, wenn seit über zwei Tagen keine zeitgesteuerte
  Aufgabe mehr gelaufen ist. Läuft die Automatik, ist nichts im Weg.

## 1.21.0

**UX aufgeräumt (Teil 1 von mehreren)**

- **Startseite für Mitglieder** zeigt jetzt „Meine Daten" und „Orga-Team
  schreiben" statt einer Aufgabenliste, die auf eine gesperrte Seite verlinkte.
  Der „Steht an"-Block erscheint nur noch für Rollen, die die
  Vollständigkeits-Seite auch öffnen dürfen.
- **„Orga-Team schreiben"** steht für Mitglieder jetzt in der Hauptnavigation
  statt versteckt im Fuß der Seitenleiste.
- **Adressbuch-Werkzeugleiste** von sechs Knöpfen auf drei: „Rundmail an diese
  Liste", „Exportieren ▾" (CSV / vCard) und „Datenpflege ▾" (Vollständigkeit,
  Doppel-Einträge, Archiv & Papierkorb).
- Der erweiterte Filterbereich heißt jetzt „Mehr Filter".
- Die nicht mehr genutzte Adresse `/namensliste` und die Standard-Rolle „Gast"
  für neue Instanzen wurden entfernt. Eine vorhandene „Gast"-Rolle lässt sich
  bei Bedarf unter Verwaltung → Rollen löschen.

## 1.20.0

**Sicherheit nachgezogen**

- **Mailserver-Passwörter verschlüsselt gespeichert** („at rest"): ein
  DB-Auszug enthält jetzt nur noch Chiffretext. Der Schlüssel liegt in einer
  eigenen Datei (`storage/app.key`), die nicht mit deployt und nicht ins
  Backup aufgenommen wird – oder frei über `security.secret_key` gesetzt.
  Bestehende Passwörter werden automatisch nachverschlüsselt.
- **Backup-ZIP optional mit Passwort** (AES-256): beim Herunterladen ein
  Passwort vergeben, beim Wiederherstellen wieder eingeben. Ohne Passwort
  bleibt alles wie bisher.
- **Passwort-vergessen-Link ohne Query-String**: der Link heißt jetzt
  `…/passwort-neu/<Token>` statt `…/reset-password?token=…&email=…`. Der Token
  landet damit nicht mehr in Server-Logs oder im Browser-Verlauf. Ältere Links
  aus schon verschickten Mails funktionieren weiter (Weiterleitung).

## 1.19.0

**Web-App fürs Handy („Zum Home-Bildschirm")**

- Die Seite lässt sich jetzt auf iPhone und Android wie eine App auf den
  Startbildschirm legen: eigenes Icon (in der Theme-Farbe, mit dem Kürzel der
  Instanz), Vollbild ohne Browser-Leiste, Farbe der Statusleiste passend.
- Ein schlanker Hintergrund-Cache beschleunigt das Laden von Design und
  Skripten. Inhalte mit Kontaktdaten werden bewusst **nicht** zwischen­
  gespeichert und laufen immer direkt übers Netz.

## 1.18.0

**vCard-Export (.vcf)**

- Einzelner Kontakt: „Als vCard" auf der Kontaktseite.
- Auswahl: im Auswahl-Modus des Adressbuchs der Knopf „vCard".
- Ganze (gefilterte) Liste: „vCard exportieren" neben „CSV exportieren".
- Die Datei (vCard 3.0) lässt sich direkt in Apple Kontakte, Google Kontakte,
  Outlook oder auf dem Handy importieren – mit Name, Adresse, allen Mail- und
  Telefonnummern, Geburtstag, Kategorie/Tags und Notiz.

## 1.17.0

**Startseite: Geburtstage & offene Rückmeldungen auf einen Blick**

- Die Startseite zeigt jetzt **„Offene Rückmeldungen"** – laufende Abstimmungen,
  bei denen noch nicht alle geantwortet haben, mit Stand und Frist.
- Und **„Geburtstage diese Woche"** – die nächsten sieben Tage, mit Datum und
  dem Alter, das die Person erreicht.
- Beide Blöcke erscheinen nur, wenn es etwas zu zeigen gibt, und richten sich
  nach den Rechten (Termine verwalten bzw. Geburtstage sehen dürfen).

## 1.16.0

**Teilnehmerkreis: Auswahl nach Tag oder Gruppe**

- Beim Festlegen des Teilnehmerkreises einer Abstimmung gibt es jetzt neben den
  Kategorie-Knöpfen ein Auswahlfeld **„Auswahl ergänzen um alle aus …"**: ein
  Klick auf eine Gruppe oder ein Tag hakt alle passenden Personen an.
- Wie die Kategorie-Knöpfe ergänzt es die Auswahl (nimmt nichts weg) – mehrere
  Tags/Gruppen lassen sich nacheinander kombinieren.

## 1.15.0

**Dubletten-Finder & Zusammenführen**

- Neue Seite **Adressbuch → „Doppelt?"**: findet Kontakte, die vermutlich
  doppelt angelegt wurden (gleicher Name oder gleiche Mailadresse) und fasst
  sie zu Gruppen zusammen.
- Pro Gruppe wählst du den Hauptkontakt; die anderen werden hineingeführt und
  danach gelöscht. Übernommen wird **alles**: E-Mail-Adressen, Telefonnummern,
  Tags, Gruppen-Mitgliedschaften (inkl. Leitung), Termin-Rückmeldungen und der
  Änderungsverlauf. Leere Felder des Hauptkontakts werden aus den anderen
  aufgefüllt, Notizen zusammengehängt.
- Hat der Hauptkontakt noch keinen Zugang, übernimmt er einen vorhandenen
  Login des zusammengeführten Kontakts.
- Kein neues Datenbank-Schema.

## 1.14.0

**Daten-Check-Link – Kontakte pflegen ihre Daten selbst, ohne Login**

- Auf jeder Kontaktseite gibt es jetzt „Daten-Check-Link": einen Link ohne
  Login, über den die betreffende Person ihre eigenen Stammdaten, Adresse und
  Kontaktwege prüfen und korrigieren kann.
- Kategorie, Tags, interne Notizen und ein verknüpfter Zugang bleiben dabei
  unangetastet – wie beim eingeloggten Selbst-Service „Mein Eintrag".
- Der Link ist 30 Tage gültig (einstellbar), pro Kontakt ist immer nur einer
  aktiv, und er lässt sich jederzeit zurückziehen. Jede Änderung landet im
  Änderungsverlauf des Kontakts.
- Der Token steht nur als Hash in der Datenbank.

## 1.13.1

**Fix:** Nach dem Hochladen von 1.13.0 zeigte die Startseite einen Serverfehler,
solange „Verwaltung → Aktualisieren" noch nicht gelaufen war (die neuen
Archiv-Spalten fehlten). Das Adressbuch zieht die Spalten jetzt notfalls selbst
nach – die Seite bleibt im Zeitfenster bis zur Migration bedienbar.

## 1.13.0

**Archiv & Papierkorb für Kontakte**

- Beim Entfernen eines Kontakts gibt es jetzt zwei Wege: **ins Archiv**
  (bleibt dauerhaft erhalten, jederzeit zurückholbar) oder **in den
  Papierkorb** (30 Tage Aufbewahrung, dann automatisch endgültig gelöscht).
- Neue Seite **Adressbuch → „Archiv & Papierkorb"**: Kontakte zurückholen,
  vom Archiv in den Papierkorb schieben oder sofort endgültig löschen.
- Archivierte und im Papierkorb liegende Kontakte tauchen nicht mehr im
  Adressbuch, in Mailings, Geburtstagsgrüßen, der Suche oder in Gruppen auf.
- Das automatische Aufräumen des Papierkorbs läuft über den Cronjob.

## 1.12.0

**Termine rund gemacht**

- **In den Kalender:** Sobald ein Termin feststeht, gibt es auf der
  Detailseite, im Abstimmungs-Link und in der Ergebnis-/Einladungsmail einen
  **„In den Kalender"-Link** (`.ics`) – ein Klick übernimmt Titel, Datum,
  Uhrzeit, Ort und Eckdaten in den eigenen Kalender.
- **Erinnerung vor dem Termin:** Beim Anlegen oder Bearbeiten lässt sich
  einstellen, dass alle Zusagen X Tage vorher automatisch eine Erinnerung
  bekommen (mit Kalender-Link). Läuft über den Cronjob.
- **Anmerkung beim Abstimmen:** Wer abstimmt, kann eine kurze Notiz
  hinterlassen (z. B. „kann erst ab 20 Uhr"). Die Verwaltung bzw. Gruppenleitung
  sieht alle Anmerkungen gesammelt auf der Detailseite.

## 1.11.0

**Zur Abstimmung einladen – mit vorbereitetem Text**

- „Teilnehmer erreichen" auf der Terminseite öffnet jetzt eine **fertig
  formulierte Einladung**: Bitte um Rückmeldung, der persönliche Link je Person,
  automatisch das **Fristende** und der normale Mail-Fuß. Text und
  Empfängerkreis lassen sich vor dem Versand noch anpassen. „Nur an Offene"
  schickt dieselbe Mail als Erinnerung.
- Nach dem Speichern des Teilnehmerkreises weist ein Hinweis direkt darauf hin.
- **Gruppen-Abstimmungen:** auf der Abstimmungsseite gibt es für die Leitung
  „Mitglieder per Nachricht informieren" – öffnet eine vorbereitete Nachricht an
  die Gruppe mit Titel, Fristende und Hinweis, wo abgestimmt wird.

## 1.10.0

**Geburtstagsgrüße automatisch verschicken**

- Unter **Verwaltung → Grüße-Pool** lässt sich der automatische Versand
  einschalten: täglich ab einer wählbaren Uhrzeit bekommt jede Person, die
  heute Geburtstag hat und eine Mailadresse hinterlegt hat, einen zufällig aus
  dem Pool gezogenen Geburtstagsgruß (mit Anrede und Mail-Fuß). Betreff frei
  wählbar, `{Vorname}` wird ersetzt.
- Läuft über den Cronjob (wie die Abstimmungs-Automatik). Ist keiner
  eingerichtet oder kein Geburtstagsgruß aktiv, passiert nichts.

**Interner Rollenschlüssel änderbar**

- Der technische Schlüssel jeder Rolle (außer Admin) lässt sich unter
  **Verwaltung → Rollen** ändern. Rechte-, Sichtbarkeits- und
  Registrierungs-Einstellungen werden dabei automatisch mitgezogen.

## 1.9.1

- Die **Cronjob-Anleitung** ist jetzt nur noch für Admins zugänglich
  (`/hilfe/cron`, hinter Login + Recht „Benutzer verwalten"). Die übrigen
  Anleitungen (Mitglieder, Gruppenleitung, Orga-Team) bleiben bewusst auch ohne
  Login erreichbar – sie enthalten keine instanz- oder personenbezogenen Daten.

## 1.9.0

**Mail: Antwortweg wählbar**

- **Gruppen-Nachricht:** Die absendende Person wählt jetzt, ob Antworten *nur an
  sie* gehen oder *an die gesamte Gruppenleitung* – praktisch, wenn eine Gruppe
  zu mehreren geleitet wird.
- **Rundmail (Nachrichten):** Im Feld „Antwort-an" gibt es zusätzlich die Option
  **„Ich selbst"** – dann kommen die Antworten direkt ins eigene Postfach statt
  ans Team-Postfach. Standard bleibt das Team. (Die sichtbare Absenderadresse ist
  weiterhin das eingerichtete Mailkonto.)

**Anleitungen erweitert**

- Neu **für Mitglieder**: „Dein Zugang zur Adress-Zentrale" – eigene Daten, Leute
  kontaktieren, an Abstimmungen teilnehmen, Gruppen.
- Neu **für Admins**: „Cronjob bei All-Inkl einrichten" – Schritt für Schritt,
  auch im Verwaltungs-Hub unter System verlinkt.
- Orga- und Gruppenleitungs-Anleitung um den neuen Antwortweg ergänzt.
- Alle vier Anleitungen unter **Hilfe & Anleitungen** und je als PDF.

## 1.8.0

**Hilfe &amp; Anleitungen in der App**

- Neuer Menüpunkt **„Hilfe &amp; Anleitungen"** in der Seitenleiste. Dahinter zwei
  bebilderte Schritt-für-Schritt-Anleitungen:
  - **fürs Orga-Team** – Kontakte, Gruppen, Rundmails, Grüße, Termine
  - **für Gruppenleitungen** – Abstimmungen anlegen, Nachrichten an die Gruppe
    senden und was danach mit den Antworten passiert
- Jede Anleitung gibt es direkt als **PDF** (`/hilfe/orga-team.pdf`,
  `/hilfe/gruppenleitung.pdf`) und im Browser mit einem „Als PDF speichern"-Knopf.
- Auf der Gruppen-Übersicht ist die Gruppenleitungs-Kurzanleitung direkt verlinkt.
- **Namensklärung:** GRUEZE steht für **GRU**ppen-**E**rreichbarkeits-**ZE**ntrale
  (klingt wie „Grüezi"). README, Doku, Footer-Tooltip und die GitHub-Beschreibung
  entsprechend korrigiert.

## 1.7.1

**„Als diese Person anmelden" aus dem Kontakt**

- Auf der Kontaktseite einer Person mit eigenem Zugang gibt es jetzt den Knopf
  **„Anmelden als …"**. Damit sieht und bedient man das System genau wie diese
  Person; über die Seitenleiste geht es mit einem Klick zurück zum eigenen
  Konto. Sichtbar nur für Konten mit „Benutzer verwalten".
- Start und Ende so einer Sitzung stehen jetzt sauber im Änderungsprotokoll –
  vorher scheiterte der Protokolleintrag still (das ENUM `audit_log.action`
  kannte die beiden Werte nicht). Alle Aktionen während der Sitzung bleiben
  dem steuernden Admin zugeordnet.

## 1.7.0

**Adressbuch und Gruppen verzahnt**

- **Spalte „Gruppen"** in der Kontaktliste (über „Spalten" zuschaltbar), plus
  ein **Gruppen-Filter** neben dem Tag-Filter.
- Die Spalten **„Tags" und „Gruppen"** sind jetzt anklickbar und sortieren die
  Liste danach – so stehen gleiche Tags/Gruppen untereinander.
- **„Aus Tag eine Gruppe machen"** (Verwaltung → Kategorien & Tags): legt eine
  Gruppe mit dem Tag-Namen an und übernimmt alle Kontakte mit diesem Tag.
  Auf Wunsch wird der Tag danach gelöscht.
- **„Aus der Auswahl eine neue Gruppe machen"** in der Sammelbearbeitung des
  Adressbuchs: markierte Kontakte direkt zu einer neuen Gruppe zusammenfassen.
- Beide Wege brauchen zusätzlich das Recht „Gruppen verwalten".

## 1.6.0

**Beitrittsanfragen für Gruppen**

- Nicht-offene Gruppen erscheinen in „Meine Gruppen" unter **„Andere Gruppen"**
  mit „Beitritt anfragen" (optional mit kurzer Nachricht).
- Die **Gruppenleitung** (oder die globale Verwaltung) sieht offene Anfragen auf
  der Gruppenseite und nimmt sie an oder lehnt sie ab. Bei einer neuen Anfrage
  geht ein Mail-Hinweis an die Leitung – gibt es keine, an Orga/Admin.
- „Meine Gruppen" und die Gruppen-Übersicht zeigen der Leitung bzw. Verwaltung
  die Zahl offener Anfragen.

## 1.5.0

**Gruppen: Terminfindung & Gruppenleitung (Stufe E)**

- **Terminfindung in der Gruppe:** Neben der Meinungsabstimmung können Gruppen
  jetzt auch Datumsvorschläge zur Auswahl stellen. Die Leitung legt danach den
  Termin fest; die Ergebnis-Mail geht dann automatisch raus.
- **Gruppenleitung:** Ein Mitglied lässt sich unter Verwaltung → Gruppen zur
  Leitung ernennen. Die Leitung darf die eigene Gruppe verwalten (Mitglieder,
  Beschreibung, Nachricht sperren/freigeben, Abstimmungen schließen und
  Termine festlegen) – ganz ohne globales „Gruppen verwalten"-Recht. Löschen
  bleibt der globalen Verwaltung vorbehalten.
- „Meine Gruppen" zeigt der Leitung einen direkten „Verwalten"-Zugang.

Damit ist die Gruppen-Funktion (Stufen A–E) abgeschlossen.

## 1.4.0

**Gruppen-Abstimmung (Stufe D)**

- Jedes Gruppenmitglied kann für die eigene Gruppe eine **Meinungsabstimmung**
  anlegen (`/gruppen` → Gruppe → „Abstimmungen"): Frage, Antwortmöglichkeiten,
  optional Frist und automatische Ergebnis-Mail.
- Die Abstimmung ist **nur für die Gruppe sichtbar**. Mitglieder stimmen direkt
  im eingeloggten Zustand ab (Ja / Vielleicht / Nein je Möglichkeit) und sehen
  den laufenden Stand.
- Neue Gruppenmitglieder werden automatisch in laufende Abstimmungen aufgenommen.
- **Admins** mit „Termine verwalten" sehen Gruppen-Abstimmungen zusätzlich in der
  Terminübersicht – mit dem Hinweis, dass sie zur Gruppe gehören und dort
  eigenständig laufen.
- Frist, Auto-Schließen, 48-Stunden-Erinnerung und Ergebnis-Mail nutzen dieselbe
  Automatik wie die übrigen Termine (Stufe A).

## 1.3.0

**Gruppen-Mail (Stufe C)**

- Jedes Gruppenmitglied kann der ganzen Gruppe eine Nachricht schreiben
  (`/gruppen` → „Nachricht an die Gruppe"). Betreff + Text, Antworten gehen
  direkt an die absendende Person. Team/Admin können das auch „von oben".
- **Weiche Tagesgrenze** (Standard 2 Gruppen-Mails pro Person und Tag): ab der
  dritten kommt ein deutlicher Hinweis, die Mail geht trotzdem raus – und das
  Admin-Team wird per Mail informiert. Für Admins gilt keine Grenze.
- **Bestätigungsmail** an den Absender: an wen (Namen) zugestellt wurde, welche
  fehlgeschlagen sind, wer keine Mailadresse hat. Bei Fehlern zusätzlich eine
  Info an die Admins.
- **Notbremse:** unter Verwaltung → Gruppen lässt sich der Versand pro Gruppe
  sperren (Admin darf dann weiterhin, Admin/Team hebt die Sperre auf).
- Alle Gruppen-Mails erscheinen im Versandprotokoll. Config: `groups`-Block in
  `config.example.php` (`mail_soft_limit`, `mail_max_recipients`).

## 1.2.0

**Gruppen (Stufe B)**

- Neue **Personengruppen** quer zu Kategorie und Tag – z. B. ein Kurs, ein
  Ausschuss oder eine Fahrgemeinschaft. Eine Person kann in mehreren Gruppen sein.
- **Verwaltung → Gruppen** (Recht `groups.manage`, Standard Team + Admin):
  Gruppen anlegen, beschreiben und Mitglieder aus dem Adressbuch wählen.
- **Meine Gruppen** (`/gruppen`) für alle mit verknüpftem Kontakt: eigene
  Gruppen sehen, **offenen Gruppen selbst bei- und austreten**.
- Grundlage für Gruppen-Mail und Gruppen-Abstimmung (folgende Stufen).

## 1.1.0

**Abstimmungs-Fristen & Ergebnis-Mail (Stufe A)**

- Termine und Abstimmungen können ein **Ende** bekommen. Ist die Frist erreicht,
  schließt die Abstimmung von selbst (neuer Status „Abstimmung beendet").
- **48 Stunden vor Fristende** geht automatisch eine Erinnerung an alle, die
  noch nicht abgestimmt haben – mit ihrem persönlichen Link.
- Beim Anlegen (oder später) lässt sich wählen, wer **nach dem Schließen das
  Ergebnis per Mail** bekommt: alle Abstimmenden, alle Eingeladenen, nur das
  Orga-Team, nur die Admins – oder niemand.
- **Frist verlängern:** eine neue Frist setzen reaktiviert eine bereits
  geschlossene Abstimmung und schaltet Erinnerung + Ergebnisversand wieder scharf.
- Neuer Cron-Endpunkt `/intern/cron?key=…` für den verlässlichen Betrieb; als
  Rückfallebene stößt sich die Automatik bei Seitenaufrufen höchstens einmal pro
  Stunde selbst an. Einrichtung: `docs/NEUE-INSTANZ.md`, Abschnitt 7.

## 1.0.0

Erste stabile Version. Schwerpunkt: der komplette **Security-Audit**
(`docs/SECURITY-AUDIT.md`) ist abgearbeitet – 2 hohe, 10 mittlere und 12
niedrige Befunde plus ein Bug.

**Sicherheit**

- **Backup-Wiederherstellung gehärtet:** ein manipuliertes Backup-ZIP kann
  keine `.htaccess`/`.php` mehr in den Upload-Ordner schreiben und keine
  Spaltennamen ins SQL schmuggeln.
- **Content-Security-Policy** und weitere Schutz-Header (`X-Frame-Options`,
  `nosniff`, `Referrer-Policy`, HSTS bei HTTPS). Alle Inline-Skripte laufen über
  einen Nonce; die „Wirklich löschen?"-Rückfragen liefen vorher über
  `onsubmit`-Attribute und sind jetzt JavaScript.
- **Kein CSRF-Loch mehr:** „Impressum/Datenschutz speichern" war ungeschützt.
  Der Rechtstext wird zusätzlich durch einen HTML-Allowlist-Filter geschickt
  (nur `p`, `a`, `ul`, `h2` … – keine Skripte).
- **Theme-Editor:** Farb-/Schrift-Werte werden geprüft, bevor sie ins Seiten-CSS
  wandern (kein Ausbruch aus `<style>` mehr).
- **E-Mail-Adressen** werden von Steuerzeichen befreit (verhinderte
  Header-Injection über den `mail()`-Fallback). CSV-Export neutralisiert
  Excel-Formeln (`=`, `+`, …).
- **Mailserver-Zugangsdaten** landen nicht mehr im Backup-ZIP. Die IMAP-„Kopie
  in Gesendet"-Verbindung prüft jetzt das TLS-Zertifikat.
- **Logins:** zusätzliche IP-weite Bremse gegen Credential Stuffing;
  konstantere Antwortzeit gegen Konten-Enumeration.
- **Passwort-Reset:** höchstens eine Mail pro Konto alle 5 Minuten, Eintrag im
  Änderungsprotokoll, alte Links werden beim Zurücksetzen ungültig.
- **Aufbewahrung:** alte Login-Versuche (30 Tage), abgelaufene Reset-Links und
  Abstimmungs-Quellhashes (120 Tage) werden automatisch gelöscht. IP-Hashes
  lassen sich mit `security.hash_pepper` unumkehrbar machen.
- Logo-Upload ohne SVG. XLSX-Import mit 5-MB-Grenze und gehärtetem XML-Parser.
- Kleinkram: Session-ID-Wechsel nach Timeout und Passwortänderung, CSRF-Token
  frisch nach Login, „Als Benutzer angemeldet"-Aktionen im Protokoll dem Admin
  zugeordnet, `Auth::user()` pro Request gecacht.

**Behoben**

- „Impressum/Datenschutz speichern" endete durch einen Aufruf einer nicht
  existierenden Funktion in einem 500 (der Text wurde trotzdem gespeichert).
- Die Bearbeiten-Seite für die Rechtstexte hatte kaputtes Markup und nicht
  vorhandene Button-Klassen.

**Sonstiges**

- `SECURITY.md` (Meldeweg für Sicherheitslücken, Betriebshinweise).
- Migration `2026-09-11-security-haertung` (`password_resets.created_at`).
- Neue optionale config-Schlüssel unter `security.*` (siehe
  `config.example.php`).

## 0.43.0

- **Lizenz festgelegt:** [PolyForm Noncommercial License 1.0.0](LICENSE).
  Quellcode einsehbar, jede nicht‑kommerzielle Nutzung (inkl. Selbst‑Hosten für
  Vereine, Familien, Jahrgänge) frei; kommerzielle Nutzung braucht eine
  separate Lizenz. Hinweis in der README.
- **Spendenhinweis im Admin‑Bereich:** dezente Zeile unten im Verwaltungs‑Hub
  („… entsteht in der Freizeit. Projekt auf GitHub · Entwicklung unterstützen").
  Neuer config‑Wert `branding.product_donate_url` (Standard gesetzt, `''` = aus);
  wie `product_url` bewusst nur über die config, nicht über die Oberfläche.
- Keine Migration.

## 0.42.0

- **Projektbeschreibung für den Public-Release.** README komplett neu:
  Kurzvorstellung, Screenshot-Galerie (`docs/screenshots/`, neutrale
  Demo-Instanz „Chor Nordwind"), Feature-Überblick, Systemvoraussetzungen,
  Docker-Schnellstart, verschlankte Shared-Hosting-Anleitung, dezenter
  Spendenhinweis.
- `.rsyncignore`: `docs/`, `docker/` und die reinen Doku-`*.md` (außer
  CHANGELOG.md, das die Update-Seite liest) landen nicht mehr auf dem Webspace.
- Nur Doku – keine funktionale Änderung, keine Migration.

## 0.41.1

- Blickschutz-Auge oben rechts saß nicht mittig im Kreis (der Knopf hatte
  versehentlich die Standard-Button-Innenabstände und einen Schatten) – jetzt
  zentriert und schattenlos. Gleiches für den „Mail an Auswahl"-Knopf und den
  Mobil-Hamburger.
- „Deine Kontaktdaten" im Adressbuch: der grüne Akzent-Strich links ist weg,
  der Kasten sieht aus wie jeder andere.

## 0.41.0

- **Login schlanker.** Der Kasten mit dem großen Brief-Symbol ist weg. Die
  Anmeldeseite ist jetzt: Überschrift, dann eine ruhige Karte mit den beiden
  Feldern, Passkey-Anmeldung und den Links darunter.
- **Einheitliche Seitenköpfe überall.** Jede Seite hat jetzt genau eine
  Hauptüberschrift (`<h1>`) im selben Stil – vorher hatten viele Unterseiten
  gar keine oder nur eine kleinere. Die dekorativen Icon-Kacheln neben den
  Überschriften (Branding, Passkeys, Benutzer, Versand, Import …) sind raus.
- **Lesbarkeit:** Fettschrift (`<strong>`) im Fließtext brach bisher überall auf
  eine eigene Zeile um – jetzt bleibt sie inline, nur echte Zwischen-
  Überschriften stehen als eigene Zeile.
- Feinschliff: gleiche `<h1>`-Größe auf allen Kernseiten, ein paar fehlende
  Seitentitel im Browser-Tab ergänzt, „Ersten Admin anlegen" ohne vorbelegten
  Namen.
- Keine Migration.

## 0.40.0

- **Verwaltung aufgeräumt.** Die Einstellungs-Seite hat statt vier nur noch
  **drei Gruppen: Zugänge · Erscheinungsbild · System**. „Kategorien & Tags"
  und „Grüße-Pool" sind zu Erscheinungsbild gewandert, „Vollständigkeit" zu
  System; die Gruppe „Werkzeuge" ist weg. Kachel-Überschrift jetzt als echtes
  `<h1>` („Einstellungen"), wie die übrigen Kernseiten.
- Keine Migration.

## 0.39.0

- **„Mein Eintrag" – Selbst-Service.** Die persönliche Seite (Wortmarke unten
  links „Mein Eintrag") führt jetzt mit den **eigenen Angaben**: „Das haben wir
  zu dir" zeigt Stammdaten, Adresse und Kontaktwege als bearbeitbares Formular
  mit klebender Speichern-Leiste – dieselbe Bedienung wie die Kontakt-Detailseite.
  - Änderbar sind nur eigene Felder; **Kategorie, Tags, interne Notizen und der
    Login** bleiben unberührt (die pflegt die Verwaltung). Notizen sind hier
    bewusst gar nicht sichtbar.
  - Jede Selbst-Änderung landet im **Änderungsverlauf** des Kontakts (alt → neu).
  - Ist der Selbst-Service-Schalter („Eigene Kontaktdaten sichtbar") aus, gibt es
    statt des Formulars eine Nur-Lese-Übersicht mit Verweis aufs Orga-Team; ohne
    verknüpften Kontakt ein kurzer Hinweis + „Orga-Team schreiben".
  - Darunter unverändert: **offene Abstimmungen**, dann **Zugang & Sicherheit**
    (Login-Adresse, Rolle, Passwort, Passkeys, Orga-Team schreiben).
- „Mein Konto" heißt an allen sichtbaren Stellen jetzt „Mein Eintrag".
- Keine Migration.

## 0.38.0

- **Selbst-Registrierung, Stufe 2.**
  - **Freigabe-Warteschlange:** trägt jemand bei der Selbst-Anmeldung eine
    Adresse ein, die keinem Kontakt zugeordnet ist, landet die Anfrage
    (samt optionaler kurzer Notiz „wer bin ich") in Verwaltung →
    Selbst-Registrierung. Admin/Orga geben frei (Link geht raus) oder lehnen ab.
  - **Passkey schon beim Anlegen:** auf der Registrierungsseite wählt man
    „Mit Kennwort" oder „Mit Passkey". Bei Passkey wird der Zugang angelegt
    und man landet direkt bei „Mein Konto → Passkeys", um ihn einzurichten.
  - **Rate-Limit:** max. 5 Selbst-Anmelde-Anfragen pro Quelle und Stunde.
- **Footer/Seitenleiste aufgeräumt:** der Balken unter der Seitenleiste ist
  für Angemeldete weg. Impressum und Datenschutz stehen jetzt (je in einer
  Zeile) unten in der Seitenleiste, direkt über „läuft mit GRUEZE vX.Y".
- Migration `2026-09-10-registrierung-freigabe` (`registration_invites.note`,
  `.ip_hash`).

## 0.37.0

- **Selbst-Registrierung, Stufe 1 – Einladungslinks.**
  - Auf einem Kontakt (Karte „Login & Rolle" bzw. „Zugang per Einladung"):
    **„Einladungslink erstellen & schicken"** → ein einmaliger, befristeter
    Link geht an die hinterlegte Mailadresse (und wird als Fallback im
    Hinweis angezeigt). Die Person setzt über den Link Name + Kennwort
    selbst; der Account bekommt die **Standard-Rolle** (voreingestellt:
    Mitglied) und wird mit dem Kontakt verknüpft, die Person ist direkt
    eingeloggt.
  - Neue Seite **Verwaltung → Selbst-Registrierung**: Standard-Rolle,
    Link-Gültigkeit, Schalter **„Selbst-Anmeldung erlauben"** (Standard:
    aus), Liste der offenen Einladungen zum Zurücknehmen.
  - Bei aktiver Selbst-Anmeldung: `/registrieren` (Link auf der
    Anmeldeseite) → Person trägt ihre bekannte Mailadresse ein → Link geht
    an genau diese Adresse (Klick = Bestätigung). Neutrale Antwort, egal ob
    die Adresse bekannt ist. Unbekannte Adressen (Freigabe-Weg) kommen in
    Stufe 2.
- Fix: Auf der Orga-Team-Seite steht jetzt **„Geht ans gesamte Orga-Team."**
  statt einer (falschen) Personenzahl.
- Migration `2026-09-09-registrierung` (Tabelle `registration_invites`).

## 0.36.0

- **Geburtstagsgrüße.** Neue Seite `/gruesse/geburtstage` (Verwaltung →
  Grüße-Pool → „Geburtstage"): Liste der anstehenden Geburtstage
  (heute / 3 / 7 / 14 Tage), je Person Datum und „in X Tagen". Wer eine
  Mailadresse hat, kommt in die **Vorschau** – jede:r bekommt zufällig
  einen Text aus dem Geburtstags-Pool, „neu mischen", dann einzeln senden.
  Personen ohne Mailadresse werden angezeigt, aber übersprungen.
- **Geburtsname mit „ehem.".** Der Geburtsname wird überall als
  **„(ehem. Müller)"** angezeigt (Adressbuch, Kontakt-Detail, Suche,
  Vollständigkeit).
- Keine Migration.

## 0.35.0

- **Grüße-Pool** (Verwaltung → Grüße-Pool): editierbare Standard-Wünsche,
  **getrennte Listen für Geburtstag und Weihnachten**. Anlegen, Text ändern,
  aktiv/inaktiv schalten, löschen. **40 mitgelieferte Texte** – kurz,
  persönlich, mit „du". Platzhalter `{Anrede}`/`{Vorname}`/`{Nachname}`
  werden beim Versand ersetzt.
- **Weihnachtsgrüße als gemischter Serienversand.** Empfängerkreis wählen
  (alle / Kategorie / Tags), dann **Vorschau**: jede Person bekommt zufällig
  einen Text aus dem Pool – nicht die ganze Stufe dieselbe Mail. „Neu
  mischen", bis es passt, dann senden (einzeln und personalisiert, über den
  bekannten Stapel-Versand).
- Geburtstagsgrüße (Liste der heutigen/anstehenden Geburtstage + Versand)
  folgen in v0.36.
- Migration `2026-09-08-gruesse-pool` (Tabelle `greetings` inkl. der 40 Texte).

## 0.34.0

- **„Orga-Team schreiben"-Knopf.** Für jede eingeloggte Person – in der
  Seitenleiste und unter „Mein Konto". Betreff + Nachricht, Antworten gehen
  an die eigene Login-Mailadresse. Ziel: eine fest hinterlegte Orga-Adresse
  (Verwaltung → Mail-Einstellungen) oder – ohne feste Adresse – alle aktiven
  Nutzer:innen mit der neuen Berechtigung **„Orga-Team"** (Standard:
  Team + Admin, in den Rollen-Berechtigungen anpassbar).
- Fix: Die Schnellauswahl-Knöpfe („Alle", „Keine", „+ Kategorie") im
  Teilnehmerkreis eines Termins waren durch eine globale Regel weiß auf hell
  und kaum lesbar – jetzt als klare Chips.

## 0.33.0

- **Termine: drei Typen.** Beim Anlegen wählt man jetzt zwischen
  **Datumsabstimmung** (wie bisher), **Fester Termin** (Datum steht, es
  werden nur Zusagen gesammelt – sofort „Termin steht") und **Abstimmung
  ohne Datum** (freie Ja/Vielleicht/Nein-Frage mit mehreren Antwort­
  möglichkeiten). Detailseite, Abstimm-Seite und Übersicht passen Texte
  und Aktionen an den Typ an; „Als Termin festlegen" gibt es nur bei der
  Datumsabstimmung.
- **„Mein Konto": offene Abstimmungen.** Wer mit einem Kontakt verknüpft
  ist, sieht dort die Termine, bei denen die eigene Rückmeldung fehlt oder
  noch geändert werden kann – ein Klick führt direkt zur Abstimmung.
- Migration `2026-09-07-termin-typ` (Spalte `events.kind`).

## 0.32.0

- **Termine: Links per Nachricht verschicken.** Auf der Termin-Detailseite
  neu **„Teilnehmer erreichen"**: „An alle Teilnehmer" / „Nur an Zusagen"
  (wenn ein Termin festgelegt ist) / „Nur an Offene" führen direkt in den
  Nachrichten-Screen mit vorbelegtem Empfängerkreis und Text. Der Platzhalter
  **`{Abstimmungslink}`** wird beim Versand je Person durch den persönlichen
  Token-Link ersetzt (auch in der Testmail). Die kopierbare Link-Liste bleibt,
  jetzt eingeklappt.
- **Abstimmungs-Verlauf** auf der Detailseite (nur Verwaltung): jede
  gespeicherte oder geänderte Rückmeldung mit Zeitpunkt, Person, Vorschlag
  und Antwort, neueste zuerst; Link-Stimmen sind als „(Link)" markiert.
- Migration `2026-09-06-event-response-log` (Tabelle `event_response_log`).

## 0.31.0

- **Neuer Bereich „Termine" – Terminfindung mit Datumsabstimmung.**
  - Übersicht `/termine` (Tab „Aktuell" / „Archiv") + Detailseite. Anlegen
    und Verwalten braucht die neue Berechtigung **`events.manage`**
    (Standard: Team/Admin, über die Rollen-Berechtigungen erweiterbar).
  - Ein Termin hat Titel, Beschreibung, freie Eckdaten (Ort/Uhrzeit/Kosten/
    Mitbringen), mehrere **Datumsvorschläge** (Uhrzeit optional, auch mehrere
    pro Tag) und einen **Teilnehmerkreis aus dem Adressbuch**.
  - **Abstimmen ohne Login:** jede Person bekommt einen eigenen Token-Link
    (`/abstimmen?token=…`), Name schon da, Ja/Vielleicht/Nein je Vorschlag,
    jederzeit über denselben Link änderbar. Wer über einen fremden Link
    kommt, sieht eine deutliche Warnung. Stimmabgaben werden mit pseudonymem
    Quell-Hash protokolliert – stimmen mehrere Geräte über einen Link ab,
    markiert die Verwaltung das („⚠ N Quellen").
  - **Abstimmungsstand** als Matrix (wer, wie, je Vorschlag) mit Zählern,
    „**Als Termin festlegen**" → festgelegter Termin + Zusagen-Liste.
    Termin archivieren / wieder öffnen / löschen.
  - Die Start-„Steht an"-Liste ist unverändert; Link-Versand per Nachricht,
    „an Teilnehmer schreiben" und weitere Termin-Typen folgen in v0.32/0.33.
- Migration `2026-09-05-termine` (Tabellen `events`, `event_options`,
  `event_participants`, `event_responses`, `event_token_hits`).

## 0.30.0

- **Neuer Look, Stufe 7 – Vollständigkeit** (löst die „Namensliste" ab,
  `/vollstaendigkeit`). Oben ein Überblick: Kontakte gesamt · ohne
  Mailadresse · ohne Handynummer (die Lücken-Kacheln filtern die Liste).
  Darunter die betroffenen Personen mit **„Bearbeiten"** und – sofern eine
  Mailadresse vorliegt – **„Schreiben"** direkt je Zeile. Die
  Namen-Kopiervorlage und „als Nachricht an eine Gruppe" bleiben, aber
  eingeklappt unter „Namen weitergeben".
- Menüpunkt/Verweise „Namensliste" → **Vollständigkeit**; die alte Adresse
  `/namensliste` leitet um. Die Start-To-dos verlinken jetzt hierher.
- Die reine „an einzelne Adressen"-Sendefunktion der alten Namensliste
  entfällt (nur noch über den Nachrichten-Flow).
- Keine Migration.

## 0.29.0

- **Neuer Look, Stufe 6 – Nachrichten.** Empfängerkreis und Text auf **einem**
  Screen statt in zwei Schritten. Oben der Empfängerkreis (Alle · Kategorie ·
  Tags · gespeicherte Liste · aktuelle Auswahl), **„Alle mit Mailadresse"
  vorgewählt**, mit **live aktualisierter Empfängerzahl** (auch unten an der
  Senden-Leiste). „Diesen Empfängerkreis als Liste speichern" direkt im
  Empfänger-Block. Darunter Absender, Betreff, Anrede, Nachricht, Mail-Fuß,
  Anhänge. Klebende Senden-Leiste mit „Testmail an mich" und „Versand starten".
- Aus dem Adressbuch (Auswählen → E-Mail verfassen) landet man auf demselben
  Screen, Empfängerkreis auf „Ausgewählte Kontakte" vorbelegt.
- Menüpunkte/Seitentitel: „Rundmail" → **Nachrichten**.
- Die frühere getrennte Empfänger-Seite (`mail/rundmail.php`) entfällt; die
  Einzelkontakt-Aufnahme für Mitglieder bleibt unverändert.
- Keine Migration.

## 0.28.0

- **Neuer Look, Stufe 5 – Kontakt-Detail.** Ansehen und Bearbeiten auf
  **einer** Seite, kein getrenntes Formular mehr. Kopf mit `<h1>`
  (Name + Geburtsname), Status-Chip, Kategorie, „im Adressbuch seit …".
  Alles direkt editierbar, ruhig in Karten (Stammdaten · Adresse ·
  Kontaktwege · Notizen · Login). Sobald etwas geändert wird, erscheint
  unten eine klebende **„Speichern"-Leiste**; eine Rückfrage schützt vor
  versehentlichem Verlassen.
- **Notizen** sind klar als **nur intern** gekennzeichnet.
- **Änderungsverlauf mit Altwerten** – nur für die Verwaltung
  (`audit.view`). Bei jeder Speicherung werden die geänderten Felder mit
  altem und neuem Wert protokolliert (`audit_log.changes`). Alte Einträge
  ohne Detailverlauf zeigen weiter ihren Kurztext. Rückwirkend nicht
  befüllbar.
- „Neuer Kontakt" nutzt dieselbe Seite; `contacts/form.php` entfällt.
- Migration `2026-09-04-audit-changes` (Spalte `audit_log.changes`).

## 0.27.0

- **Adressbuch: Spalten wieder anpassbar.** Neben „Auswählen" ein Menü
  **„Spalten"**: Tags, Adresse, Geburtstag, E-Mail, Telefon, Login lassen
  sich einzeln zur Tabelle hinzuschalten. **Standard bleibt schlank**
  (Name · Kategorie · Status); die Auswahl wird pro Gerät gemerkt. Nur in
  der Tabellenansicht.
- Keine Migration.

## 0.26.0

- **Neuer Look, Stufe 4 – Adressbuch.** Das dichteste Fenster wird ruhig:
  - Kopf mit echtem `<h1>` und Zeile „N Kontakte · M ohne Mailadresse".
  - Filterleiste: Suche + Kategorie sichtbar, alles Weitere (Sortierung,
    Tags, fehlende Angaben) hinter **„Filter"**.
  - Die vier Aufklapp-Bereiche (Schnellauswahl, Spalten, Sammelaktionen,
    Sammelbearbeitung) sind zu **einem „Auswählen"-Modus** zusammengefasst:
    ein Klick blendet Auswahl-Kästchen und eine Aktionsleiste ein
    (Alle/Keine, E-Mails kopieren, E-Mail verfassen bzw. Person kontaktieren,
    Sammelbearbeitung, Fertig). Beim Verlassen wird die Auswahl geleert.
    Die Spalten-ein-/ausblenden-Funktion entfällt.
  - Tabelle nur noch **Name · Kategorie · Status**. Status als Chip
    (vollständig / Mail fehlt / Tel. fehlt) statt sechs Einzelspalten;
    Adresse, Geburtstag, Login stehen in der Kartenansicht und im Detail.
  - **Tabelle ↔ Karten** jetzt für alle Rollen umschaltbar (nicht mehr
    Admin-only) und pro Gerät gemerkt; ohne gespeicherte Wahl am Handy
    Karten, sonst Tabelle.
- Keine Migration.

## 0.25.0

- **Neuer Look, Stufe 3 – Startseite.** Kein Kacheln-Dashboard mehr: oben
  Begrüßung mit Datum, großes Suchfeld, zwei Schnellaktionen. Darunter
  **„Steht an"** – dieselben Kennzahlen wie früher (ohne Mailadresse / ohne
  Handynummer), aber als verlinkte To-do-Liste mit großer Fraunces-Zahl in
  gedämpftem Amber. Sind keine Lücken offen, steht dort eine ruhige
  „alles gepflegt"-Zeile. Erste Seite mit echtem `<h1>`.
- Keine Migration.

## 0.24.0

- **Neuer Look, Stufe 2 – Seitenleiste + Kopfzeile.** Die laute grüne
  Kopfleiste weicht einer ruhigen, hellen Topbar (Suche + Blickschutz). Neue
  Seitenleiste: Wortmarke mit grünem Punkt, eine **„Mein Eintrag"-Karte** oben,
  klare Navigation mit grünem Aktiv-Streifen, Verwaltung als Gruppe.
- Menüpunkte umbenannt: „Kontakte" → **Adressbuch**, „Rundmail" → **Nachrichten**.
- Login und andere Gast-Seiten ohne Chrome, zentriert.
- Kein Screen-Umbau – die Seiteninhalte sind noch die alten. Nächste Stufen
  laut `docs/REDESIGN.md`.

## 0.23.1

- Fix: Update-Hinweisstreifen erschien auch bei Code-Updates ohne DB-Änderung,
  ohne Möglichkeit ihn zu quittieren. Erscheint jetzt nur bei offenen
  Migrationen; die Version zieht sonst still nach.

## 0.23.0

- **Neuer Look, erste Stufe:** ruhigeres Waldgrün statt Leuchtgrün, viel Weiß,
  neue Schriften. Fraunces für Überschriften, Hanken Grotesk für Oberfläche
  und Text – beide **lokal eingebettet** (`public/assets/fonts/`,
  `assets/css/fonts.css`), keine externen Requests.
- Themes „Grün" (Slug bleibt `signalfarbe`) und „Dunkel" auf die neue
  Designsprache umgestellt; `theme.css`-Fallback und `ThemeService::DEFAULTS`
  angeglichen. Bestehende Instanzen auf diesem Theme bekommen den neuen Look
  automatisch. Keine Migration.
- Grundlage für den weiteren Umbau (Seitenleiste/Kopf, dann Screen für Screen).
  Referenz: `docs/REDESIGN.md` + Hi-Fi-Entwurf „GRUEZE Oberfläche".

## 0.22.0

- **Backup zusammenführen**: dritter Wiederherstellungs-Modus. Spielt nur die
  Kontakte (+ Mailadressen, Telefonnummern, Tags, Kategorien) aus einem Backup
  ins bestehende System ein, ohne etwas zu löschen. Gleiche Personen werden
  über Name/Geburtsname erkannt und nur um fehlende Angaben und leere Felder
  ergänzt. Alles über natürliche Schlüssel aufgelöst – keine ID-Konflikte.
  Benutzer, Rollen, Einstellungen, Protokolle bleiben unberührt. Keine Migration.

## 0.21.0

- **Eigene Kontaktdaten sichtbar**: Eingeloggte Personen mit verknüpftem
  Kontakt sehen auf der Kontaktseite ein Feld „Deine Kontaktdaten" (Adresse,
  Geburtstag, Mail, Telefon, Login) – auch wenn ihre Rolle sonst nichts sieht.
  **Notizen bleiben ausgenommen** und folgen weiter der Rollen-Regel.
  Abschaltbar unter Verwaltung → Sichtbarkeit (Standard: an). Keine Migration.

## 0.20.0

- **Rollen frei verwaltbar** (Verwaltung → Rollen): Anzeigename und
  Beschreibung jeder Rolle setzen, eigene Rollen anlegen und löschen. Der
  interne Schlüssel bleibt fix (Rechte und Sichtbarkeit hängen daran). „Admin"
  ist geschützt; Löschen ist gesperrt, solange Benutzer zugeordnet sind, und
  entfernt den Rollennamen danach aus allen Rechte-/Sichtbarkeits-Listen.
- Rechte- und Sichtbarkeits-Seiten lesen jetzt alle Rollen aus der Datenbank
  statt aus einer festen Liste; überall Anzeigenamen statt interner Schlüssel.
- Migration `2026-09-03-rollen-label` (Spalte `roles.label`).

## 0.19.0

- White-Label-Feinschliff bei Mail-Texten: Platzhalter `{name}` (Instanzname)
  und `{kurzname}` in Mail-Fuß und Betreff-Präfixen, werden beim Versand
  ersetzt (z. B. `[{kurzname}]`). Standard-Texte ohne feste Team-Bezeichnung.
  „Eingeschränkte Kontaktaufnahme" wird über Berechtigungen erkannt statt über
  einen festen Rollennamen.

## 0.18.0

- Update-Ablauf für bestehende Instanzen: neue Seite **Verwaltung →
  Aktualisieren**. Ein Klick wendet alle offenen Migrationen an, legt auf
  Wunsch vorher eine Datensicherung unter `storage/backups/` ab und vermerkt
  die laufende Version. Admin-Hinweisstreifen, wenn nach einem Upload noch ein
  Update aussteht. Optional `config('app.auto_migrate')` (Standard: aus) für
  headless-Setups.
- Migrationen: die frühere Einzel-anwenden-Seite ist als Fallback in
  „Aktualisieren" eingeklappt; erste Kommentarzeile jeder `.sql` wird als
  Kurzbeschreibung angezeigt.

## 0.17.1

- Barrierefreiheit abgeschlossen: echte Fokus-Falle im Mobil-Menü,
  unterstrichene Inline-Links (WCAG 1.4.1), deckender Fokus-Ring auf
  Eingabefeldern, Platzhalter-Kontrast, Fokus auf Fehlermeldungen,
  Seitenleiste ohne irreführendes „complementary"-Landmark.

## 0.17.0

- Barrierefreiheit, Grunddurchgang WCAG 2.1 AA: Skip-Link, `:focus-visible`,
  `prefers-reduced-motion`, Live-Regionen (Toast, Meldungen, Status,
  Fortschritt), Formularfelder benannt, Tabellen mit `scope`/`aria-sort`,
  eindeutige Heading-Hierarchie. Nebenbei eine XSS-Lücke im Versand-Ergebnis
  geschlossen.

## 0.16.0

- SEO-Grundausstattung: `noindex` überall (Meta, `robots.txt`, `X-Robots-Tag`),
  sprechende Seitentitel, `rel=canonical`, eigenständige 404-/500-Seiten mit
  korrektem Statuscode.

## 0.15.0

- Distribution vorbereitet: instanz- und serverspezifische Reste aus Repo,
  Doku und Historie entfernt. Deploy-Ziel kommt aus `scripts/deploy.env`
  (nicht im Repo). Docker-Namen und `localStorage`-Schlüssel vereinheitlicht.

## 0.14.x

- GRUEZE als Produktname (Footer, Seitenleiste), `system_label` als
  config-Wert. Projektunterlagen aufgeräumt.

## 0.13.0

- Gespeicherte Empfängerlisten für Rundmails.

## 0.12.x

- White-Label: neutrale Code-Defaults, Branding/Rechtstexte/Mail-Fuß über die
  Oberfläche pflegbar, Setup-Anleitung `docs/NEUE-INSTANZ.md`.
- Startseite: kräftigere Aktions-Kacheln.

## 0.11.x

- Theme-System-Feinschliff: visueller Theme-Editor mit Live-Vorschau und
  Kontrast-Hinweisen, dunkles Theme, mitgeliefertes Theme „Signalfarbe".

## 0.8.0 – 0.10.x

- Theme-System (Farben, Schriften, Ecken als benannte Themes), automatische
  Schriftfarbe und dynamisches Favicon, mobile Navigation.

## bis 0.7.x

- Grundfunktionen: Kontakte, Kategorien/Tags, Rundmail mit gestapeltem
  Versand, Namensliste, Blickschutz, Voll-Backup/Restore, Rollen- und
  Rechtekonzept, Passkeys.

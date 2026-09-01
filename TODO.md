# Offene Punkte / Backlog

Laufende Sammlung offener Ideen und Aufgaben für die Adress-Zentrale.
Wird nach jeder abgeschlossenen Arbeitseinheit aktualisiert.

## Neu

- **UX-Umbau – nächste Stufen** (Gerüst, Startseite, Suche, Rundmail,
  Kontakte-Seite, Mobil-Navigation, Namensliste erledigt in v0.5.0–0.7.0):
  - Feinschliff: Blickschutz-Knopf auf dem Handy evtl. nur als Icon;
    leerer Raum im aufgeklappten Menü verkleinern.
  - später: gespeicherte Empfängerlisten (wenn sich das als praktisch zeigt).

- **Visueller Theme-Editor mit Live-Vorschau**: Der aktuelle Editor
  (`templates/settings/theme-edit.php`) ist ein Formular mit Farbfeldern und
  Vorschau-Kacheln. Wünschenswert: Änderungen sofort auf einer echten
  Beispielansicht sehen (Buttons, Karten, Tabelle, Kopfleiste), Farbwähler
  statt Texteingabe, evtl. Kontrast-Warnung direkt am Feld.

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

- **Voll-Import: Merge-/Zusammenführen-Modus** (v0.4.0 liefert Export +
  Wiederherstellung „Alles ersetzen" / „Nur wenn leer"). Offen: Backup in einen
  bestehenden Datenbestand einspielen, ohne ihn zu löschen – mit Dedup und
  ID-Konfliktbehandlung. Deutlich aufwändiger, daher separat.

## Aus der ursprünglichen Übergabe (ChatGPT), noch offen

1. White-Label-Vorbereitung vervollständigen, ohne die laufende Instanz
   neutral umzubiegen. (Teil erledigt in v0.3.1: `config('branding.*')`-Sektion,
   `system_label`, Template-Fallbacks. Offen: `defaultLegalText()` in
   `SettingRepository` enthält noch reale Personendaten – erst Instanz-Rechtstexte
   in `app_settings` seeden, dann Code-Default neutralisieren.)
2. Noch verbleibende hartcodierte Instanz-Texte systematisch identifizieren und
   auf konfigurierbare Defaults umstellen. (siehe Punkt 1; Rest: Standard-Mail-Fuß
   und Betreff-Präfixe in `config/config.*` sind bereits `defaults.*`-basiert,
   könnten in die `branding`-Logik integriert werden.)
3. Rollen- und Rechtekonzept weiter schärfen, vor allem für spätere
   "Kontakt kann optional Login haben"-Logik.
4. Sichtbarkeit einzelner Kontaktfelder noch granularer pro Rolle
   administrierbar machen.
5. Admin-Einstellungsbereich weiter strukturieren (Branding / Mail-Versand /
   Sichtbarkeiten-Rollen / ggf. System-Backup später).
6. Vorbereitung für neutrale Distribution: Installer-Konzept,
   Update-Konzept, konfigurierbare Startwerte. (Backup/Restore erledigt in
   v0.4.0, siehe „Datensicherung" unter /admin/backup.)
7. Saubere Migrationsstrategie überlegen, damit die Instanz später ohne
   manuelle Neueingabe auf eine neutralisierte, bessere Fassung wechseln kann.

## Erledigt

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
  `themes/` (`grueze.php`, `hell.php`) bieten sich automatisch als Vorlage an;
  Doku in `themes/README.md`. Der bisherige Look heißt jetzt **GRUEZE**, neuer
  Standard für frische Installationen ist **Hell** (viel Weiß, Orange-Akzent).
  „Design & Branding" ist in **Branding** (Name/Texte/Logo) und **Themes**
  aufgeteilt; die alten Farb-/Font-Felder auf der Branding-Seite sind weg.
  `app.css` in den sichtbaren Kern-Elementen (Buttons, Text, Links, Fokus,
  Umschalter, Kopfleiste) auf Tokens umgestellt. Laufende Instanz bleibt
  optisch unverändert (aktives Theme = `signalfarbe`).
  **Auf Prod unter Verwaltung → Migrationen `2026-08-31-themes` anwenden.**
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
  Importdaten, z. B. Vorname Nachname) ließ sich nicht speichern – das
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
  „GRUEZE") kommen jetzt über `config('branding.*')` mit Instanzwerten als
  eingebautem Fallback. Dokumentierte `branding`-Sektion in
  `config/config.example.php` und `config.production-template.php`.
  Hartcodierte `kontakt@example.org`- und `[Verteiler]`-Fallbacks in Templates entfernt.
  Instanz unverändert (ihre config hat keine `branding`-Sektion).
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

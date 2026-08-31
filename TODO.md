# Offene Punkte / Backlog

Laufende Sammlung offener Ideen und Aufgaben für die Adress-Zentrale.
Wird nach jeder abgeschlossenen Arbeitseinheit aktualisiert.

## Neu

- **UX-Umbau – nächste Stufen** (Gerüst + Startseiten-Feinschliff erledigt in
  v0.5.0–0.5.2):
  - Rundmail-Bereich: eigener Menüpunkt, Empfänger wählen
    (alle / Kategorie / Tag(s) / aktuelle Suche übernehmen), dann schreiben.
  - Kontakte-Seite inhaltlich straffen (Massen-/Bulk-Werkzeuge in
    Aufklapp-Bereiche, Progressive Disclosure).
  - Mobil weiter entschlacken: Signalbalken-Buttons und Profil/Abmelden auf
    dem Handy kompakter, weniger Chrome vor dem Inhalt.
  - Namensliste erzeugen & versenden (reine Namensliste per Mail zum
    Vollständigkeitsabgleich durch den Jahrgang).
  - später: gespeicherte Empfängerlisten (wenn sich das als praktisch zeigt).

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

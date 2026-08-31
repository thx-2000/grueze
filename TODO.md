# Offene Punkte / Backlog

Laufende Sammlung offener Ideen und Aufgaben für die Adress-Zentrale.
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

- **Voll-Export & -Import aller Daten**: Funktion, um den gesamten
  Datenbestand (Kontakte, Kategorien, Tags, Benutzer/Rollen, Einstellungen,
  Protokolle, hochgeladene Dateien wie Logo) als vollständiges Backup zu
  exportieren – maschinenlesbar und wieder importierbar. Zweck: Datensicherung,
  Umzug auf einen anderen Server, späterer Wechsel auf die neutrale Fassung.
  Import muss klar zwischen "leeres System befüllen" und "bestehende Daten
  ersetzen/zusammenführen" unterscheiden. Baut auf und ersetzt den groben
  Punkt "Backup-/Restore-Konzept" weiter unten. Verhältnis zum bestehenden
  XLSX-Kontaktimport klären (der bleibt für Teil-Importe).

## Aus der ursprünglichen Übergabe (ChatGPT), noch offen

1. White-Label-Vorbereitung vervollständigen, ohne die laufende Instanz
   neutral umzubiegen.
2. Noch verbleibende hartcodierte Instanz-Texte systematisch identifizieren und
   auf konfigurierbare Defaults umstellen.
3. Rollen- und Rechtekonzept weiter schärfen, vor allem für spätere
   "Kontakt kann optional Login haben"-Logik.
4. Sichtbarkeit einzelner Kontaktfelder noch granularer pro Rolle
   administrierbar machen.
5. Admin-Einstellungsbereich weiter strukturieren (Branding / Mail-Versand /
   Sichtbarkeiten-Rollen / ggf. System-Backup später).
6. Vorbereitung für neutrale Distribution: Installer-Konzept,
   Backup-/Restore-Konzept, Update-Konzept, konfigurierbare Startwerte.
7. Saubere Migrationsstrategie überlegen, damit die Instanz später ohne
   manuelle Neueingabe auf eine neutralisierte, bessere Fassung wechseln kann.

## Erledigt

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

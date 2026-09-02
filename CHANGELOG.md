# Changelog

Kurzüberblick je Version. Nach einem Datei-Upload bringt
**Verwaltung → Aktualisieren** die Datenbank auf den passenden Stand.

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

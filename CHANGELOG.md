# Changelog

Kurzüberblick je Version. Nach einem Datei-Upload bringt
**Verwaltung → Aktualisieren** die Datenbank auf den passenden Stand.

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

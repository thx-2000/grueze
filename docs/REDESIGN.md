# Redesign — abgestimmte Richtung

Grundlage: zwei Discovery-Gespräche (2026-09-01) + Lo-Fi-Wireframes
(„GRUEZE Grundriss", als Artifact abgestimmt und für gut befunden).
Dieses Dokument ist die Referenz für den Umbau. Hi-Fi-Look folgt separat.

## Rahmen

- White-Label-Adressbuch mit Gruppen-Organisation, skaliert 5–500 Personen.
- Zielgruppe **nicht technikaffin**. Vereine, Familien, Jahrgänge, JGA, Firmen.
- Zwei Nutzungen, **eine App mit weniger drin**:
  Admin/Orga am Rechner (voller Kasten) · alle anderen am Handy (schlank).
- Leitmotiv: **ruhiger, klarer, weniger auf einmal.** Grün-Identität bleibt,
  weniger Leuchtgrün. Referenz-Gefühl: Skyscanner / PayPal / Airbnb.
- Aktivität in Schüben rund um (jährliche) Treffen.

## Informationsarchitektur

Seitenleiste, für alle dieselbe App — die Rolle blendet aus:

| Bereich | war | für wen |
|---|---|---|
| ★ **Mein Eintrag** | (neu, Ansätze in v0.21) | alle, immer ganz oben |
| **Adressbuch** | „Kontakte" | alle (Umfang je Rolle) |
| **Nachrichten** | „Rundmail" | mail.send |
| **Termine** | *neu* | anlegen: Admin/Orga (rollenfreischaltbar) |
| **Verwaltung** | 12 Kacheln → 3 Gruppen (Zugänge · Erscheinungsbild · System) | Admin/Orga |

Handy-Ansicht für Mitglieder: nur *Mein Eintrag · Adressbuch · Termine*.

## Screen-Entscheidungen

- **Start (Admin):** „Steht an"-Block (dieselben Kennzahlen, aber als
  verlinktes To-do), großes Suchfeld zuerst, zwei Schnellaktionen.
- **Adressbuch:** eine ruhige Liste. Filter = Suche + Kategorie sichtbar,
  Rest hinter „＋ Filter". Die vier Aufklapp-Bereiche (Sammelauswahl /
  Spalten / Sammelbearbeitung / Schnell-Anlegen) → **ein „Auswählen"-Modus**
  mit Aktionsleiste. **Status-Spalte** (Mail fehlt / Tel. fehlt / vollständig)
  statt sechs Einzelspalten. **Tabelle ↔ Karten pro Konto** merkbar
  (nicht Admin-only), Standard Tabelle, am Handy Karten.
- **Kontakt-Detail:** ansehen + inline bearbeiten auf einer Seite, kein
  getrenntes Formular. Notizen klar „nur intern". **Änderungshistorie mit
  Altwerten — nur für Admins.**
- **Nachrichten:** Empfängerkreis + Text auf **einem** Screen, Empfängerzahl
  live. „Als Liste speichern" an der Senden-Zeile. „An alle" vorgewählt.
- **Vollständigkeit** (löst „Namensliste" ab): Überblick (X gesamt · Y ohne
  Mail · Z ohne Tel.), pro Lücke „bearbeiten" / „diesen schreiben" /
  „als Text kopieren" / „Liste teilen". Kopier-Funktion bleibt, nicht mehr
  Hauptdarsteller.
- **Termine — Übersicht:** drei Zeilentypen — Termin mit Datumsabstimmung,
  Termin mit festem Datum (+ Zusagen), reine Abstimmung ohne Datum. Mehrere
  gleichzeitig. **„Archiv"-Tab** (kein Löschen), Abgeschlossenes verschwindet.
- **Termin — Detail:** Eckdaten (Ort/Uhrzeit/Kosten/Mitbringen, frei) →
  Datumsabstimmung (ja/vielleicht/nein je Vorschlag) → „Ergebnis als Termin
  festlegen" → Zusagen-Liste. Teilnehmerkreis = Empfänger = Zusagen, alles
  aus dem Adressbuch. „✉ an Teilnehmer" → Nachrichten mit vorbelegtem Kreis
  (alle / nur Zusagen / nur Offene).
- **Mobil: Mein Eintrag:** „Das haben wir zu dir", „Ändern" je Zeile, nur
  eigene Daten, Notizen unsichtbar. **„Orga-Team schreiben"-Knopf** (→ alle
  mit Admin-/Orga-Rolle; feste Extra-Adresse als Ausnahme konfigurierbar).
- **Mobil: Abstimmen (ohne Login):** personalisierter **Token-Link** je
  Person, Name vorbelegt. Tool erkennt, wer geklickt hat / ob mehrfach über
  einen Link. Fremder Link → sichtbare Warnung „du änderst einen fremden
  Eintrag". Eingeloggte sehen dieselbe Abstimmung unter „Termine".

## Offene Punkte (vor Umsetzung klären)

1. **Token-Link & Datenschutz:** Link = Schlüssel zur Identität. Reicht der
   Warnhinweis, oder zusätzlich Mail-Bestätigung?
2. **Grüße-Pool** (Geburtstag/Weihnachten, geshuffelt): eigener Screen unter
   „Nachrichten" — assistiert (Admin prüft Stapel) vs. automatisch am Tag.
3. **Verwaltung** im Detail: 12 Kacheln → 3 Gruppen, eigener Durchgang.
4. **Reihenfolge:** Navigation + Optik-Grundlage zuerst → Adressbuch →
   Termine? Oder Termine vorziehen (nächstes Treffen)?

## Neue TODOs, die hier andocken

- Terminfindungs-/Abstimmungstool (→ Bereich „Termine")
- „Mail ans Orga-Team"-Knopf (→ Mein Eintrag / überall im Mitglied-Menü)
- Grüße-Pool mit Zufallsrotation (→ Nachrichten)

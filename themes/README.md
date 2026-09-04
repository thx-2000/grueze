# Themes

Ein Theme bündelt das komplette Aussehen der Adress-Zentrale – Schriften, Farben
und Eckenradien – unter einem Namen. Das aktive Theme wird als CSS-Variablen in
den `<head>` jeder Seite geschrieben und überschreibt damit die Standardwerte aus
`public/assets/css/theme.css`.

## Zwei Arten von Themes

| Art             | Ablageort              | Bearbeitbar | Zweck                                   |
|-----------------|------------------------|-------------|----------------------------------------|
| **Datei-Theme** | `themes/<slug>.php`    | nein        | Mitgelieferte Vorlagen                  |
| **Eigenes Theme** | DB-Tabelle `themes`  | ja          | Über die Oberfläche erstellt/angepasst  |

Datei-Themes lassen sich nicht direkt ändern. Um sie als Ausgangspunkt zu nutzen:
in der Oberfläche (Verwaltung → Themes) **duplizieren** – die Kopie landet als
eigenes Theme in der Datenbank und ist frei editierbar.

Der Editor für eigene Themes hat einen **Farbwähler pro Feld**, eine
**Live-Vorschau** (Kopfleiste, Karte, Buttons, Eingabefeld, Badges, Tabelle –
aktualisiert sofort, gespeichert wird erst mit „Theme speichern") und
**Kontrasthinweise** direkt am Feld (WCAG-Verhältnis, Warnung unter 4.5:1
bzw. 3:1). Ein leeres Feld lässt den Token unverändert.

Mitgeliefert:

- `signalfarbe.php` – „Grün": Waldgrün auf viel Weiß. Der Standard für neue
  Installationen und zugleich die Basis, auf die nicht gesetzte Tokens
  zurückfallen (Slug bleibt aus Kompatibilität `signalfarbe`).
- `hell.php` – „Hell": viel Weiß, warmer Orange-Akzent, Teal als Zweitfarbe.
- `terrakotta.php` – „Terrakotta": warme Erdtöne mit gebranntem Orange als
  Signalfarbe.
- `kreide.php` – „Kreide": fast weiße kühle Flächen, kräftige matte Farben
  (Petrol / Ziegel / Amber), etwas schärfere Ecken.
- `dunkel.php` – „Dunkel": dunkle Oberfläche mit hellgrüner Aktionsfarbe.
  Zugleich die Referenz dafür, wie ein dunkles Theme aussehen muss (siehe unten).

## Ein Datei-Theme anlegen

Eine neue Datei `themes/mein-theme.php` anlegen. Der Dateiname (ohne `.php`) ist
der Slug und muss aus Kleinbuchstaben, Ziffern und Bindestrichen bestehen. Die
Datei gibt ein Array zurück:

```php
<?php

return [
    'name'        => 'Mein Theme',
    'description' => 'Kurze Beschreibung, taucht in der Theme-Übersicht auf.',
    'tokens'      => [
        'color_primary' => '#1d4ed8',
        'color_accent'  => '#facc15',
        // ... weitere Tokens nach Bedarf
    ],
];
```

Das Theme wird beim nächsten Seitenaufruf automatisch in der Übersicht
angeboten – kein Cache, keine Migration nötig.

**Nicht gesetzte Tokens** fallen automatisch auf die Werte des
`signalfarbe`-Themes zurück. Man muss also nur die Tokens angeben, die abweichen.

## Verfügbare Tokens

Alle Werte sind CSS-Werte. Farben als Hex (`#ff8800`), `rgb()`/`rgba()` oder
benannte Farbe. Längen mit Einheit (`0.5rem`, `4px`).

### Schrift

| Token          | CSS-Variable     | Beispiel                                    |
|----------------|------------------|---------------------------------------------|
| `font_display` | `--font-display` | Überschriften. `"Fraunces", Georgia, serif` |
| `font_body`    | `--font-body`    | Fließtext. `system-ui, sans-serif`          |

Lokale Fonts werden bevorzugt. Web-Fonts müssten zusätzlich per `@font-face` in
`public/assets/css/` eingebunden werden – das Theme-System liefert keine Fonts
aus.

### Flächen

| Token                  | CSS-Variable             | Wirkung                                  |
|------------------------|--------------------------|-----------------------------------------|
| `color_bg`             | `--color-bg`             | Seitenhintergrund                       |
| `color_bg_alt`         | `--color-bg-alt`         | Zweiter Hintergrund (Verläufe, Ränder)  |
| `color_surface`        | `--color-surface`        | Kartenfläche (Panels, Kacheln)          |
| `color_surface_strong` | `--color-surface-strong` | Kräftigere Kartenfläche                  |
| `color_surface_soft`   | `--color-surface-soft`   | Weichere/abgesetzte Fläche              |

### Text & Rahmen

| Token          | CSS-Variable     | Wirkung                     |
|----------------|------------------|-----------------------------|
| `color_text`   | `--color-text`   | Haupt-Textfarbe             |
| `color_muted`  | `--color-muted`  | Gedämpfter Text, Hinweise   |
| `color_border` | `--color-border` | Rahmen, Trennlinien         |

### Aktionen

| Token                  | CSS-Variable             | Wirkung                              |
|------------------------|--------------------------|-------------------------------------|
| `color_primary`        | `--color-primary`        | Primär-Buttons, aktive Zustände     |
| `color_primary_strong` | `--color-primary-strong` | Primärfarbe abgedunkelt (Hover)     |
| `color_secondary`      | `--color-secondary`      | Sekundäre Akzente                   |
| `color_accent`         | `--color-accent`         | Signalfarbe (Kopfleiste, Badges)    |
| `color_highlight`      | `--color-highlight`      | Dezente Hervorhebung/Markierung     |

### Status

| Token           | CSS-Variable      | Wirkung                    |
|-----------------|-------------------|----------------------------|
| `color_danger`  | `--color-danger`  | Warnungen, Löschen         |
| `color_success` | `--color-success` | Bestätigungen, Erfolg      |

### Ecken

| Token       | CSS-Variable  | Beispiel  |
|-------------|---------------|-----------|
| `radius_sm` | `--radius-sm` | `0.35rem` |
| `radius_md` | `--radius-md` | `0.55rem` |
| `radius_lg` | `--radius-lg` | `0.8rem`  |
| `radius_xl` | `--radius-xl` | `1.1rem`  |

Der Nutzerwunsch ist: Ecken eher klein halten, Verläufe vermeiden.

## Kontrast (WCAG 2.1 AA)

Beim Zusammenstellen der Farben auf ausreichende Kontraste achten:

- `color_text` auf `color_bg` **und** auf `color_surface`: mindestens **4.5:1**.
- `color_muted` auf `color_surface`: mindestens **4.5:1** (es ist echter Text).
- Button-Text (weiß bzw. `color_surface`) auf `color_primary`: mindestens **4.5:1**.
- `color_primary_strong` auf `color_accent` (Badge): mindestens **4.5:1**.
- Rahmen/Grafik gegen Fläche: mindestens **3:1**.

Prüfen z. B. mit dem Kontrast-Checker der Browser-DevTools oder
<https://webaim.org/resources/contrastchecker/>.

## Testen

1. Datei anlegen bzw. Theme in der Oberfläche duplizieren und anpassen.
2. Verwaltung → Themes → **Aktivieren**.
3. Durch die zentralen Seiten klicken: Start, Kontakte (Tabelle + Kacheln),
   Kontaktformular, Rundmail, Verwaltung, Login (abgemeldet).
4. Responsiv gegenprüfen bei 375 / 768 / 1280 px.
5. Tab-Fokus prüfen – der Fokusring nutzt `color_accent` bzw. `color_primary`.

## Was das Theme steuert

Die gesamte Oberfläche speist sich aus den Tokens: Hintergründe, Text, Links,
alle Buttons, Eingabefelder, Tabellen (inkl. Zebrastreifen und Hover),
Fokusrahmen, die Akzent-Kopfleiste, Karten, Seitenleiste, Badges, Ecken und
Schriften. Flächen, Kanten und Schatten mischen sich per `color-mix` aus den
Basis-Tokens (`theme.css`), eine feste Ebene mehr muss man dafür nicht kennen.

Zwei Sonderfälle:

- **Tag-Pillen** (`tag_style()` in `helpers.php`) nutzen eine feste bunte
  Palette, unabhängig vom Theme – so bleiben einzelne Tags wiedererkennbar.
- Text auf farbigen Flächen (Buttons, Kopfleiste) wird automatisch schwarz
  oder weiß gesetzt (`readable_ink()`), je nach Kontrast. Man muss also nur
  `color_primary` / `color_accent` / `color_danger` wählen, nicht die
  Schriftfarbe darauf.

## Dunkle Themes

Werden unterstützt. `dunkel.php` ist die mitgelieferte Referenz. Worauf zu
achten ist:

- `color_bg` am dunkelsten, `color_surface` / `color_surface_strong` je eine
  Stufe heller (Karten heben sich ab), `color_surface_soft` wieder dunkler.
- `color_text` hell, `color_muted` klar darunter, aber noch ≥ 4.5:1 gegen
  `color_surface`.
- `color_border` als helle, halbtransparente Kante angeben
  (`rgba(255, 255, 255, 0.15)` o. ä.), nicht als dunkle.
- `color_primary` und `color_accent` so wählen, dass Schwarz **oder** Weiß
  darauf ≥ 4.5:1 erreicht – die Automatik nimmt dann die bessere.
- `color_primary_strong` (Linkfarbe) heller als `color_primary` setzen, damit
  Links auf dunklen Flächen tragen.

## Aktives Theme

Steht in `app_settings` unter dem Schlüssel `active_theme` (Wert = Slug). Ist
kein Wert gesetzt, verwendet eine frische Installation `hell`, eine bestehende
Instanz ohne gesetzten Wert fällt auf `signalfarbe` zurück.

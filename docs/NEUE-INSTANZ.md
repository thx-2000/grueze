# Neue Instanz aufsetzen (White-Label)

Die Anwendung ist markenneutral. „GRUEZE" ist nur der Name **einer** Instanz.
Eine frische Installation startet mit generischen Texten und dem Theme
`hell`; alles Instanzspezifische wird danach eingestellt, nicht im Code
geändert.

## 1. Grundinstallation

1. Webspace so einrichten, dass `public/` der Webroot ist.
2. `config/config.example.php` → `config/config.php` kopieren. Anpassen:
   `app.name`, `app.base_url`, `app.session_name`, `database.*`, `mail.*`
   (mindestens die erste `identities`-Identität und eine `reply_to_options`).
3. `database/schema.sql` in die MySQL-/MariaDB-Datenbank importieren.
4. Optional `phpmailer/phpmailer` per Composer installieren (sonst `mail()`).
5. `public/assets/uploads/` und `storage/tmp/` beschreibbar machen.
6. Seite `/setup/admin` aufrufen und das erste Admin-Konto anlegen. Dabei
   wird das Theme `hell` als aktiv gesetzt.
7. Unter `/admin/migrations` alle Migrationen anwenden.

## 2. Branding

`config/config.php` → Sektion `branding` (alle Schlüssel optional):

| Schlüssel            | Bedeutung                                            |
|----------------------|-----------------------------------------------------|
| `app_name`           | voller Name (Titel, Kopfzeile)                       |
| `short_name`         | Kurzname / Logo-Text                                 |
| `system_label`       | technisches Kürzel im Footer (leer = nur Version)    |
| `login_headline`     | Überschrift auf der Login-Seite                      |
| `login_intro`        | Fließtext darunter                                   |
| `public_site_label` / `public_site_url` | Link auf die öffentliche Seite    |
| `login_public_hint`  | Hinweistext zu diesem Link                           |
| `sidebar_copy`       | Text unter dem Logo in der Seitenleiste              |
| `support_email`      | Kontakt-/Absenderadresse für Hinweise                |

Auflösung: **Verwaltung → Branding** (`app_settings`) schlägt die config, die
config schlägt die eingebauten Defaults. Name, Kurzname, Login-Texte, Logo und
Support-Mail lassen sich also auch komplett über die Oberfläche pflegen.

## 3. Aussehen

Verwaltung → Themes. `hell` ist der Standard, `signalfarbe` und `dunkel` sind
weitere Vorlagen. „Kopieren & bearbeiten" öffnet den Editor mit Live-Vorschau,
Farbwähler und Kontrasthinweisen. Details: `themes/README.md`.

## 4. Rechtstexte (Pflicht für DE)

Verwaltung → Rechtliches. Impressum und Datenschutzerklärung sind bei
Auslieferung nur ein Platzhalter-Gerüst und **müssen** vor dem produktiven
Betrieb vollständig ausgefüllt werden.

## 5. Mail-Texte

Verwaltung → Mail-Einstellungen: Absender/Mailserver, Betreff-Präfixe und der
Mail-Fuß. Bleibt der Mail-Fuß leer, wird ein knapper, aus dem Branding
abgeleiteter Standardtext verwendet.

## Hinweis zur Instanz

Die laufende Instanz zieht ihre Werte aus `app_settings` (per Migration
`2026-09-01-white-label-seed` gesichert) bzw. aus
`config/config.production-template.php`. Sie ist damit ein normaler
White-Label-Fall – nichts am Code ist auf sie zugeschnitten.

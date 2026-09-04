# Neue Instanz aufsetzen (White-Label)

Das Produkt heißt **GRUEZE** – **GRU**ppen-**E**rreichbarkeits-**ZE**ntrale
(und klingt wie „Grüezi"). Jede Installation ist eine
**Instanz** davon und bekommt einen eigenen Namen. Eine frische Installation
startet markenneutral mit generischen Texten und dem Theme `hell`; alles
Instanzspezifische wird danach eingestellt, nicht im Code geändert.

Der Produktname GRUEZE bleibt sichtbar (Footer, Seitenleiste „läuft mit
GRUEZE"). Nur wer das Produkt selbst umlabeln will, setzt
`branding.system_label` in der config um oder leer.

## 1. Grundinstallation

1. Webspace so einrichten, dass `public/` der Webroot ist.
2. `config/config.example.php` → `config/config.php` kopieren. Anpassen:
   `app.name`, `app.base_url`, `app.session_name`, `database.*`, `mail.*`
   (mindestens die erste `identities`-Identität und eine `reply_to_options`).
   `security.store_ip` steht auf `false` – erst auf `true` setzen, wenn die IP
   angemeldeter Sitzungen wirklich gebraucht wird und die Datenschutzerklärung
   das abdeckt (Login-Versuche werden ohnehin immer nur gehasht abgelegt).
3. `database/schema.sql` in die MySQL-/MariaDB-Datenbank importieren.
4. Optional `phpmailer/phpmailer` per Composer installieren (sonst `mail()`).
5. `public/assets/uploads/`, `storage/tmp/` und `storage/` beschreibbar machen
   (`storage/` selbst, damit die App `storage/app.key` für die „at rest"-
   Verschlüsselung anlegen kann – alternativ `security.secret_key` in der
   `config.php` setzen).
6. Seite `/setup/admin` aufrufen und das erste Admin-Konto anlegen. Dabei
   wird das Theme `hell` als aktiv gesetzt.
7. Unter `/admin/migrations` alle Migrationen anwenden.

## 2. Branding

`config/config.php` → Sektion `branding` (alle Schlüssel optional):

| Schlüssel            | Bedeutung                                            |
|----------------------|-----------------------------------------------------|
| `app_name`           | voller Name DIESER Instanz (Titel, Kopfzeile)        |
| `short_name`         | Kurzname / Logo-Text                                 |
| `login_headline`     | Überschrift auf der Login-Seite                      |
| `login_intro`        | Fließtext darunter                                   |
| `public_site_label` / `public_site_url` | Link auf die öffentliche Seite    |
| `login_public_hint`  | Hinweistext zu diesem Link                           |
| `sidebar_copy`       | Text unter dem Logo in der Seitenleiste              |
| `support_email`      | Kontakt-/Absenderadresse für Hinweise                |
| `system_label`       | Produktname (Standard `GRUEZE`) – nur zum Umlabeln   |
| `product_url`        | Ziel des „läuft mit …"-Links in der Seitenleiste     |
| `product_donate_url` | „Entwicklung unterstützen" dezent im Verwaltungs-Hub; `''` = aus |

Auflösung: **Verwaltung → Branding** (`app_settings`) schlägt die config, die
config schlägt die eingebauten Defaults. Instanz-Name, Kurzname, Login-Texte,
Logo und Support-Mail lassen sich auch komplett über die Oberfläche pflegen;
`system_label`, `product_url` und `product_donate_url` nur über die config.

## 3. Aussehen

Verwaltung → Themes. `hell` ist der Standard, `signalfarbe` und `dunkel` sind
weitere Vorlagen. „Kopieren & bearbeiten" öffnet den Editor mit Live-Vorschau,
Farbwähler und Kontrasthinweisen. Details: `themes/README.md`.

## 4. Rollen, Rechte und Sichtbarkeit

Mitgeliefert sind vier Rollen (Admin, Team, Mitglied, Gast). Unter
**Verwaltung → Rollen** lassen sich Anzeigename und Beschreibung anpassen,
eigene Rollen anlegen und nicht benötigte löschen (Admin ist geschützt, eine
Rolle mit zugeordneten Benutzern erst nach dem Umziehen).

**Verwaltung → Berechtigungen** legt fest, welche Rolle welche Aktionen darf,
**Verwaltung → Sichtbarkeit**, welche Kontaktfelder sie sieht. Admin hat immer
alles.

## 5. Rechtstexte (Pflicht für DE)

Verwaltung → Rechtliches. Impressum und Datenschutzerklärung sind bei
Auslieferung nur ein Platzhalter-Gerüst und **müssen** vor dem produktiven
Betrieb vollständig ausgefüllt werden.

## 6. Mail-Texte

Verwaltung → Mail-Einstellungen: Absender/Mailserver, Betreff-Präfixe und der
Mail-Fuß. Bleibt der Mail-Fuß leer, wird ein knapper, aus dem Branding
abgeleiteter Standardtext verwendet.

## 7. Cron für die Abstimmungs-Automatik

Abstimmungen mit Frist schließen sich selbst, verschicken 48&nbsp;Stunden vorher
eine Erinnerung an alle, die noch nicht abgestimmt haben, und mailen nach dem
Schließen das Ergebnis an den gewählten Verteiler. Damit das verlässlich (auch
ohne Seitenbesuche) passiert, braucht die Instanz einen regelmäßigen Aufruf.

1. In `config/config.php` unter `app.cron_key` eine lange Zufallskette setzen,
   z.&nbsp;B. `openssl rand -hex 24`.
2. Beim Hoster einen Cron-Job anlegen, der etwa alle 15&nbsp;Minuten diese URL
   abruft (bei All-Inkl.: KAS → Cronjobs → „URL aufrufen"):

   ```
   https://DEINE-DOMAIN/intern/cron?key=DEIN_SCHLUESSEL
   ```

   Alternativ per Shell-Cron: `curl -s "https://…/intern/cron?key=…" >/dev/null`

Ohne gesetzten Schlüssel antwortet die URL mit 404. Für sehr selten genutzte
Instanzen gibt es zusätzlich eine gedrosselte Rückfallebene: bei Seitenaufrufen
wird die Automatik höchstens einmal pro Stunde nebenbei angestoßen. Ein echter
Cron bleibt aber die empfohlene Lösung – sonst hängen fällige Erinnerungen und
Ergebnis-Mails, bis jemand die Seite öffnet.

Schritt-für-Schritt für All-Inkl (KAS): Anleitung unter `/hilfe/cron`
(nur für Admins; PDF unter `/hilfe/cron.pdf`, Quelle
`resources/help/cron-allinkl.html`). Solange kein Cron läuft, weist die
Einstellungen-Seite oben mit einem Hinweisstreifen darauf hin und verlinkt
die Anleitung; sobald die Automatik regelmäßig läuft, verschwindet er.

## Bestehende Instanz aktualisieren

Ein neuer Upload darf vorhandene Daten nie überschreiben. Instanzspezifische
Werte liegen in `app_settings` (Branding, Rechtstexte, Mail-Vorlagen) und in
`config/config.php` – beide werden vom Deploy nicht angefasst.

Ablauf:

1. Neue Dateien hochladen (`bash scripts/deploy.sh` oder per FTP; `config/config.php`
   und `storage/` bleiben unberührt).
2. In der App **Verwaltung → Aktualisieren** öffnen. Steht ein Update aus,
   erscheint dort (und als Hinweisstreifen oben) die Liste der offenen
   Migrationen.
3. **Jetzt aktualisieren** klicken. Die Checkbox „Vorher eine Datensicherung
   anlegen" ist standardmäßig an – das ZIP landet unter `storage/backups/`
   (die letzten drei bleiben liegen). Alle Migrationen laufen der Reihe nach,
   danach wird die Version vermerkt und ein Protokolleintrag geschrieben.

Migrationen sind additiv und idempotent. Wer den Schritt ganz automatisieren
will, setzt `app.auto_migrate` in der config auf `true` – dann laufen offene
Migrationen beim ersten Request nach dem Upload selbst (ohne Sicherung).

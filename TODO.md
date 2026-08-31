# Offene Punkte / Backlog

Laufende Sammlung offener Ideen und Aufgaben für die Adress-Zentrale.
Wird nach jeder abgeschlossenen Arbeitseinheit aktualisiert.

## Neu

- **Blickschutz-Knopf ("Datenschutz-Button")**: Als eingeloggter Admin/Orga
  soll man mit einem Klick E-Mail-Adressen und Telefonnummern in der
  Oberfläche ausblenden/unleserlich machen können (z. B. Weichzeichner oder
  Maskierung), falls jemand am Bildschirm mitliest. Mit erneutem Klick sollen
  sie genauso schnell wieder eingeblendet werden. Gedacht als rein
  clientseitiger Toggle (kein Server-Roundtrip nötig), der über alle
  Kontaktlisten- und Detailansichten hinweg konsistent wirkt.

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

- Footer-Klasse `privacy-note` → `site-footer` umbenannt und als `<footer>`
  ausgezeichnet (v0.2.8). Grund: Inhalts-/Cookie-Blocker (Filterlisten) haben
  den alten Klassennamen erkannt und Teile des Footers – inkl. Versionsanzeige –
  bei manchen Besuchern ausgeblendet. Mouse-over an der Versionsanzeige erklärt
  jetzt den Namen GRUEZE (Grüezi / „Gruß-Zentrale").
- Lokale Docker-Testumgebung eingerichtet (PHP 8.2 + Apache + MariaDB 10.11,
  siehe `docker/README.md`), angenähert an den all-inkl-KAS-Produktivstand.

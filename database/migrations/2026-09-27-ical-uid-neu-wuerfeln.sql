-- Sicherheits-Härtung: Kalender-Schlüssel (events.ical_uid) neu würfeln.
--
-- Frühe Bestände wurden per UUID() befüllt – das ist in MariaDB zeit- und
-- MAC-Adress-basiert und damit in Grenzen vorhersagbar. Der .ics-Download ist
-- nur über diesen Schlüssel geschützt, deshalb bekommen alle noch aktiven
-- Termine einen echten Zufallswert. Archivierte Termine bleiben unberührt
-- (deren Kalender-Links spielen keine Rolle mehr).
--
-- Folge: Wer den Kalender-Link eines laufenden Termins abonniert oder
-- gespeichert hat, muss ihn einmalig neu kopieren.
UPDATE events
SET ical_uid = LOWER(HEX(RANDOM_BYTES(16)))
WHERE status <> 'archived';

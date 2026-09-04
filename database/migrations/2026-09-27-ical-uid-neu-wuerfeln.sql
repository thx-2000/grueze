-- Sicherheits-Härtung: Kalender-Schlüssel (events.ical_uid) neu würfeln.
--
-- Frühe Bestände wurden per UUID() befüllt – das ist in MariaDB zeit- und
-- MAC-Adress-basiert und damit in Grenzen vorhersagbar. Der .ics-Download ist
-- nur über diesen Schlüssel geschützt, deshalb bekommen alle noch aktiven
-- Termine einen neuen, nicht ableitbaren Wert: SHA-256 über UUID() + RAND() +
-- id, auf 32 Hex-Zeichen gekürzt. SHA2 gibt es in jeder MariaDB-/MySQL-Version;
-- RANDOM_BYTES nicht (erst MariaDB 10.10).
--
-- Archivierte Termine bleiben unberührt (deren Kalender-Links spielen keine
-- Rolle mehr). Folge: Wer den Kalender-Link eines laufenden Termins abonniert
-- oder gespeichert hat, muss ihn einmalig neu kopieren.
UPDATE events
SET ical_uid = SUBSTRING(SHA2(CONCAT(UUID(), RAND(), id), 256), 1, 32)
WHERE status <> 'archived';

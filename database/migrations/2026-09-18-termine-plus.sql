-- Termine-Erweiterungen:
--  * events.ical_uid          – stabiler, nicht erratbarer Schlüssel für den
--    Kalender-Download (.ics) und das UID-Feld darin.
--  * events.remind_days_before – optionale Erinnerung X Tage vor dem
--    festgelegten Termin an alle Zusagen (0/NULL = aus).
--  * events.event_reminder_sent_at – einmalig gesetzt, wenn die
--    Vorab-Erinnerung raus ist.
--  * event_participants.note  – freie Anmerkung beim Abstimmen
--    („kann erst ab 20 Uhr").
ALTER TABLE events
    ADD COLUMN IF NOT EXISTS ical_uid CHAR(32) NULL AFTER id,
    ADD COLUMN IF NOT EXISTS remind_days_before TINYINT UNSIGNED NULL AFTER result_mail_sent_at,
    ADD COLUMN IF NOT EXISTS event_reminder_sent_at DATETIME NULL AFTER remind_days_before;

ALTER TABLE events
    ADD KEY IF NOT EXISTS idx_events_ical_uid (ical_uid);

UPDATE events SET ical_uid = REPLACE(UUID(), '-', '') WHERE ical_uid IS NULL;

ALTER TABLE event_participants
    ADD COLUMN IF NOT EXISTS note VARCHAR(500) NULL AFTER token;

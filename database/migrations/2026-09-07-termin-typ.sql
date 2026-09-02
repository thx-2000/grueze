-- Termin-Typen: Datumsabstimmung (Standard, wie bisher), fester Termin mit
-- reinen Zusagen, oder freie Ja/Nein-Abstimmung ohne Datum. Die
-- Antwortoptionen liegen weiter in event_options – bei „poll" tragen sie
-- statt eines Datums einen Text (Spalte label existiert bereits).
ALTER TABLE events
    ADD COLUMN IF NOT EXISTS kind ENUM('date_poll', 'fixed_date', 'poll') NOT NULL DEFAULT 'date_poll' AFTER title;

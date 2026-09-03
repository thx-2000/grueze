-- Gruppen-Abstimmung (Stufe D): eine Abstimmung kann zu einer Gruppe gehören.
-- Dann ist der Teilnehmerkreis der Gruppe zugeordnet, jedes Mitglied darf die
-- Abstimmung sehen und abstimmen, und sie taucht bei Nicht-Mitgliedern nicht in
-- der Terminübersicht auf (Admins mit events.manage sehen sie mit Hinweis).
--
-- Kein benannter Fremdschlüssel (nicht idempotent nachrüstbar): das Feld wird
-- beim Löschen einer Gruppe in GroupRepository::delete() auf NULL gesetzt.
-- Frische Installationen bekommen den FK über schema.sql.
ALTER TABLE events
    ADD COLUMN IF NOT EXISTS group_id INT UNSIGNED NULL AFTER kind;

ALTER TABLE events
    ADD KEY IF NOT EXISTS idx_events_group (group_id);

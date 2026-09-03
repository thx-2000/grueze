-- Kontakt-Papierkorb & Archiv:
--  * archived_at – Kontakt ruht dauerhaft im Archiv (jederzeit
--    wiederherstellbar, wird nie automatisch gelöscht).
--  * deleted_at  – Kontakt liegt im Papierkorb; 30 Tage nach diesem
--    Zeitpunkt räumt die Automatik ihn endgültig weg.
--  * retired_by  – wer archiviert / in den Papierkorb gelegt hat.
-- „Lebende" Kontakte haben beide Zeitstempel NULL.
ALTER TABLE contacts
    ADD COLUMN IF NOT EXISTS archived_at DATETIME NULL AFTER updated_by,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL AFTER archived_at,
    ADD COLUMN IF NOT EXISTS retired_by INT UNSIGNED NULL AFTER deleted_at;

ALTER TABLE contacts
    ADD KEY IF NOT EXISTS idx_contacts_archived (archived_at),
    ADD KEY IF NOT EXISTS idx_contacts_deleted (deleted_at);

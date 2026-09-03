-- Abstimmungs-Fristen & Ergebnis-Mail (Stufe A):
--  * events.closes_at        – optionales Ende, danach schließt die Abstimmung
--    selbstständig (Status 'closed').
--  * events.result_recipients – wer nach dem Schließen automatisch das Ergebnis
--    per Mail bekommt: 'voted' | 'invited' | 'orga' | 'admin' | NULL (niemand).
--  * events.reminder_sent_at  – Zeitpunkt der 48-Stunden-Erinnerung an alle,
--    die noch nicht abgestimmt haben (einmalig).
--  * events.result_mail_sent_at – Zeitpunkt des automatischen Ergebnis-Versands.
--  * Status-ENUM um 'closed' erweitert (Abstimmung beendet, aber noch nicht
--    archiviert / als Termin festgelegt).
ALTER TABLE events
    MODIFY COLUMN status ENUM('open', 'closed', 'decided', 'archived') NOT NULL DEFAULT 'open';

ALTER TABLE events
    ADD COLUMN IF NOT EXISTS closes_at DATETIME NULL AFTER status,
    ADD COLUMN IF NOT EXISTS result_recipients VARCHAR(16) NULL AFTER closes_at,
    ADD COLUMN IF NOT EXISTS reminder_sent_at DATETIME NULL AFTER result_recipients,
    ADD COLUMN IF NOT EXISTS result_mail_sent_at DATETIME NULL AFTER reminder_sent_at;

ALTER TABLE events
    ADD KEY IF NOT EXISTS idx_events_closes_at (closes_at);

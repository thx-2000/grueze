-- Galerien und Dokumente-Ordner können jetzt zusätzlich zu einer Abstimmung
-- (galleries.event_id) auch an eine Termine-Ankündigung verlinkt werden.
-- Kein DB-FK auf announcements (Tabellenreihenfolge in schema.sql, wie bei
-- events.group_id) – AnnouncementRepository::delete() räumt vor dem Löschen auf.
ALTER TABLE galleries
    ADD COLUMN IF NOT EXISTS announcement_id INT UNSIGNED NULL AFTER event_id,
    ADD KEY IF NOT EXISTS idx_galleries_announcement (announcement_id);

ALTER TABLE document_folders
    ADD COLUMN IF NOT EXISTS announcement_id INT UNSIGNED NULL AFTER visible_group_id,
    ADD KEY IF NOT EXISTS idx_document_folders_announcement (announcement_id);

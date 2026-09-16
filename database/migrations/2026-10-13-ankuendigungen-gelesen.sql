-- Ankündigungen: Gelesen/Ungelesen pro Person. Ungelesene, für die eigene
-- Person sichtbare Ankündigungen erscheinen aufgeklappt auf der Startseite,
-- bis sie bestätigt werden – danach bleiben sie ganz normal unter /termine
-- nachlesbar.
CREATE TABLE IF NOT EXISTS announcement_reads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NOT NULL,
    read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_announcement_reads (announcement_id, contact_id),
    KEY idx_announcement_reads_contact (contact_id),
    CONSTRAINT fk_announcement_reads_announcement FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
    CONSTRAINT fk_announcement_reads_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

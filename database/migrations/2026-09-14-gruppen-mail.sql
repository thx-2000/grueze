-- Gruppen-Mail (Stufe C): jedes Mitglied darf der ganzen Gruppe schreiben.
--  * contact_groups.mail_locked – „Notbremse": gesetzt = kein Gruppen-Versand
--    mehr (Admin darf weiterhin, Admin/Orga hebt die Sperre wieder auf).
--  * group_mail_log – ein Eintrag je Versand, für die weiche Tagesgrenze
--    (Standard 2/Person) und die Nachvollziehbarkeit.
ALTER TABLE contact_groups
    ADD COLUMN IF NOT EXISTS mail_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER is_open;

CREATE TABLE IF NOT EXISTS group_mail_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id INT UNSIGNED NOT NULL,
    sender_user_id INT UNSIGNED NULL,
    sender_name VARCHAR(190) NOT NULL,
    subject VARCHAR(190) NOT NULL,
    recipient_count INT NOT NULL DEFAULT 0,
    error_count INT NOT NULL DEFAULT 0,
    soft_limit_hit TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_group_mail_log_sender (sender_user_id, created_at),
    KEY idx_group_mail_log_group (group_id, created_at),
    CONSTRAINT fk_gml_group FOREIGN KEY (group_id) REFERENCES contact_groups(id) ON DELETE CASCADE,
    CONSTRAINT fk_gml_user FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

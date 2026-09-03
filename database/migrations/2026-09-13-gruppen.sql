-- Gruppen (Stufe B): frei definierbare Personengruppen quer zu Kategorie/Tag.
-- Eine Person kann in mehreren Gruppen sein. `is_open` = jede:r darf selbst
-- beitreten. Mitgliedschaft hängt am Adressbuch-Kontakt (nicht am Login),
-- damit Gruppen-Mail und Gruppen-Abstimmung (Stufe C/D) darauf aufsetzen können.
CREATE TABLE IF NOT EXISTS contact_groups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(500) NULL,
    is_open TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_contact_groups_name (name),
    CONSTRAINT fk_contact_groups_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_group_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NOT NULL,
    role ENUM('member', 'lead') NOT NULL DEFAULT 'member',
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_group_member (group_id, contact_id),
    KEY idx_group_member_contact (contact_id),
    CONSTRAINT fk_cgm_group FOREIGN KEY (group_id) REFERENCES contact_groups(id) ON DELETE CASCADE,
    CONSTRAINT fk_cgm_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Beitrittsanfragen (Stufe E-Nachtrag): Bei nicht-offenen Gruppen kann eine
-- Person um Aufnahme bitten; die Gruppenleitung oder die globale Verwaltung
-- nimmt an oder lehnt ab.
CREATE TABLE IF NOT EXISTS contact_group_join_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NOT NULL,
    message VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_group_join_request (group_id, contact_id),
    KEY idx_group_join_request_group (group_id),
    CONSTRAINT fk_gjr_group FOREIGN KEY (group_id) REFERENCES contact_groups(id) ON DELETE CASCADE,
    CONSTRAINT fk_gjr_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

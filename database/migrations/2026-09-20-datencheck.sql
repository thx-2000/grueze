-- Daten-Check-Link: eine Person kann ihre eigenen Kontaktdaten ohne Login
-- prüfen und korrigieren. Der Token wird nur als SHA-256-Hash gespeichert,
-- ist zeitlich begrenzt (config contacts.data_check_days) und gilt für genau
-- einen Kontakt und dessen selbst pflegbare Felder.
CREATE TABLE IF NOT EXISTS contact_data_checks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contact_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    UNIQUE KEY uq_contact_data_checks_token (token_hash),
    KEY idx_contact_data_checks_contact (contact_id),
    CONSTRAINT fk_cdc_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
    CONSTRAINT fk_cdc_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

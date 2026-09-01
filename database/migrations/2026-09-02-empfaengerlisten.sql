-- Gespeicherte Empfängerlisten für die Rundmail: eine benannte Momentaufnahme
-- einer Kontaktauswahl (Kontakt-IDs). Beim Versand werden nur die Kontakte
-- angeschrieben, die weiterhin eine Mailadresse haben.
CREATE TABLE IF NOT EXISTS mail_recipient_lists (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    contact_ids MEDIUMTEXT NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_recipient_list_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

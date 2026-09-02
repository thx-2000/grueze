-- Selbst-Registrierung / Account-Anlage über Einladungslink. Eine berechtigte
-- Person (oder – falls freigeschaltet – die Person selbst mit bekannter
-- Adresse) erzeugt einen einmaligen, befristeten Link. Über den Link legt die
-- Person Name + Kennwort fest; der Account wird mit einer niedrigen
-- Standardrolle angelegt und mit dem Kontakt verknüpft.
CREATE TABLE IF NOT EXISTS registration_invites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    contact_id INT UNSIGNED NULL,
    token_hash VARCHAR(255) NOT NULL,
    created_by INT UNSIGNED NULL,
    status ENUM('pending', 'awaiting_approval', 'used', 'revoked') NOT NULL DEFAULT 'pending',
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_registration_invites_email (email),
    KEY idx_registration_invites_status (status),
    CONSTRAINT fk_registration_invites_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
    CONSTRAINT fk_registration_invites_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

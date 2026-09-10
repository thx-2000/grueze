-- „In Memoriam": Verstorbene aus dem aktiven Bestand nehmen (contacts.deceased_at)
-- und eine Gedenkseite füllen. memorials-Zeilen sind entweder mit einem Kontakt
-- verknüpft oder frei (z. B. Lehrkräfte, die nie im Adressbuch standen).
ALTER TABLE contacts
    ADD COLUMN IF NOT EXISTS deceased_at DATE NULL AFTER retired_by;

CREATE TABLE IF NOT EXISTS memorials (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contact_id INT UNSIGNED NULL,
    display_name VARCHAR(190) NOT NULL,
    role_label VARCHAR(120) NULL,
    born_year SMALLINT UNSIGNED NULL,
    died_year SMALLINT UNSIGNED NULL,
    died_on DATE NULL,
    note VARCHAR(500) NULL,
    photo_path VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_memorials_contact (contact_id),
    KEY idx_memorials_year (died_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

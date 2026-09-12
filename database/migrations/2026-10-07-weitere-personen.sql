-- „Weitere Personen": eine zusätzliche Personenliste außerhalb des
-- Adressbuchs (bei dieser Instanz als „Lehrkräfte" beschriftet, siehe
-- config('branding.roster_label')). Mit eigenen Kontakt-/Lebensdaten und
-- einem freien Rollenfeld (Fach/Kurs, Position, o. Ä.).
CREATE TABLE IF NOT EXISTS roster_people (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    role_label VARCHAR(160) NULL,
    email VARCHAR(190) NULL,
    mobile VARCHAR(60) NULL,
    born_on DATE NULL,
    died_on DATE NULL,
    strasse VARCHAR(190) NULL,
    plz VARCHAR(20) NULL,
    ort VARCHAR(120) NULL,
    land VARCHAR(80) NULL,
    note VARCHAR(500) NULL,
    photo_path VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_roster_people_name (name),
    CONSTRAINT fk_roster_people_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verknüpfung zur Gedenkseite: ein Todesdatum bei „Weitere Personen" spiegelt
-- sich automatisch als Gedenk-Eintrag (analog zur contact_id-Verknüpfung).
ALTER TABLE memorials
    ADD COLUMN IF NOT EXISTS roster_person_id INT UNSIGNED NULL AFTER contact_id,
    ADD KEY IF NOT EXISTS idx_memorials_roster (roster_person_id);

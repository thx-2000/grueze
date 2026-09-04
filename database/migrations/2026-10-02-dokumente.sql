-- Dokumente-Bereich: Ordner mit Dateien (PDF, Word, Excel, …) fürs Orga-Team
-- und für Gruppenleitung – analog zum Rechte-/Gruppen-Modell der Galerien.
--
-- Die Dateien selbst liegen NICHT in der Datenbank, sondern unter
-- storage/documents/ (außerhalb des Webroots) und werden über einen
-- PHP-Endpunkt mit Rechteprüfung ausgeliefert. Hier stehen nur die Metadaten.
-- Kein Papierkorb (wie bei Gruppen) – Löschen ist hier bewusst endgültig.

CREATE TABLE IF NOT EXISTS document_folders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    description TEXT NULL,
    owner_group_id INT UNSIGNED NULL,
    visible_group_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_document_folders_owner_group (owner_group_id),
    KEY idx_document_folders_visible_group (visible_group_id),
    CONSTRAINT fk_document_folders_owner_group FOREIGN KEY (owner_group_id) REFERENCES contact_groups(id) ON DELETE SET NULL,
    CONSTRAINT fk_document_folders_visible_group FOREIGN KEY (visible_group_id) REFERENCES contact_groups(id) ON DELETE SET NULL,
    CONSTRAINT fk_document_folders_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    folder_id INT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    description TEXT NULL,
    original_name VARCHAR(255) NULL,
    stored_path VARCHAR(255) NOT NULL,
    mime VARCHAR(150) NOT NULL,
    byte_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_documents_folder (folder_id),
    CONSTRAINT fk_documents_folder FOREIGN KEY (folder_id) REFERENCES document_folders(id) ON DELETE CASCADE,
    CONSTRAINT fk_documents_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

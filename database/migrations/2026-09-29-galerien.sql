-- Galerien: Foto-/Video-Sammlungen (z. B. pro Stufentreffen).
--
-- Die Dateien selbst liegen NICHT in der Datenbank, sondern unter
-- storage/media/ (außerhalb des Webroots) und werden über einen PHP-Endpunkt
-- mit Rechteprüfung ausgeliefert. Hier stehen nur die Metadaten.

CREATE TABLE IF NOT EXISTS galleries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    description TEXT NULL,
    gallery_date DATE NULL,
    event_id INT UNSIGNED NULL,
    sort_mode ENUM('captured', 'uploaded', 'manual') NOT NULL DEFAULT 'captured',
    cover_media_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    KEY idx_galleries_event (event_id),
    KEY idx_galleries_deleted (deleted_at),
    CONSTRAINT fk_galleries_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
    CONSTRAINT fk_galleries_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gallery_media (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gallery_id INT UNSIGNED NULL,
    kind ENUM('image', 'video') NOT NULL,
    original_name VARCHAR(255) NULL,
    stored_path VARCHAR(255) NOT NULL,
    thumb_path VARCHAR(255) NULL,
    web_path VARCHAR(255) NULL,
    mime VARCHAR(100) NOT NULL,
    byte_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
    width INT UNSIGNED NULL,
    height INT UNSIGNED NULL,
    duration_seconds INT UNSIGNED NULL,
    captured_at DATETIME NULL,
    caption VARCHAR(500) NULL,
    position INT NOT NULL DEFAULT 0,
    uploaded_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    KEY idx_gallery_media_gallery (gallery_id, deleted_at),
    KEY idx_gallery_media_captured (gallery_id, captured_at),
    KEY idx_gallery_media_position (gallery_id, position),
    CONSTRAINT fk_gallery_media_gallery FOREIGN KEY (gallery_id) REFERENCES galleries(id) ON DELETE CASCADE,
    CONSTRAINT fk_gallery_media_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

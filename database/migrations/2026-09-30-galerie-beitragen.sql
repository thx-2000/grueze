-- Galerien Stufe 2: Weitergabe-Link zum Hochladen ohne Login.
--
-- Eine berechtigte Person erzeugt einen Token-Link (per Messenger/Mail/QR
-- teilbar). Wer den Link öffnet, kann Fotos/Videos beisteuern – in eine
-- bestimmte Galerie oder in den Auffangraum (gallery_id = NULL), aus dem die
-- Verwaltung sie später einer Galerie zuordnet.

CREATE TABLE IF NOT EXISTS gallery_upload_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gallery_id INT UNSIGNED NULL,
    token_hash VARCHAR(255) NOT NULL,
    token_sha CHAR(64) NOT NULL,
    label VARCHAR(120) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL,
    revoked_at DATETIME NULL,
    max_uploads INT UNSIGNED NULL,
    upload_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_upload_at DATETIME NULL,
    KEY idx_gul_token_sha (token_sha),
    KEY idx_gul_gallery (gallery_id),
    CONSTRAINT fk_gul_gallery FOREIGN KEY (gallery_id) REFERENCES galleries(id) ON DELETE CASCADE,
    CONSTRAINT fk_gul_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE gallery_media
    ADD COLUMN IF NOT EXISTS via_link TINYINT(1) NOT NULL DEFAULT 0 AFTER uploaded_by;

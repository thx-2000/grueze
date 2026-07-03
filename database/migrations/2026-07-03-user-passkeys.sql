CREATE TABLE user_passkeys (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    credential_id VARBINARY(255) NOT NULL,
    public_key_pem TEXT NOT NULL,
    algorithm INT NOT NULL DEFAULT -7,
    sign_count INT UNSIGNED NOT NULL DEFAULT 0,
    transports TEXT NULL,
    label VARCHAR(190) NOT NULL,
    aaguid CHAR(36) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    last_used_ip VARCHAR(64) NULL,
    UNIQUE KEY uniq_user_passkeys_credential (credential_id),
    KEY idx_user_passkeys_user (user_id),
    CONSTRAINT fk_user_passkeys_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Anmelde-Übersicht: aktive Sitzungen + Verlauf für die Verwaltung.
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_hash CHAR(64) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    ip_address VARCHAR(64) NOT NULL DEFAULT '',
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME NULL,
    revoked_at DATETIME NULL,
    UNIQUE KEY uniq_user_sessions_hash (session_hash),
    INDEX idx_user_sessions_user (user_id),
    INDEX idx_user_sessions_seen (last_seen_at),
    CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

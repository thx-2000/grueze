-- Persönlich angepinnte Kacheln auf der Startseite (pro Zugang). Aus dem
-- Einstellungen-Hub oder generisch von jeder anderen Seite aus anpinnbar,
-- Reihenfolge per Ziehen änderbar.
CREATE TABLE IF NOT EXISTS dashboard_pins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    path VARCHAR(255) NOT NULL,
    label VARCHAR(190) NOT NULL,
    icon VARCHAR(40) NULL,
    description VARCHAR(255) NULL,
    position INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_dashboard_pins (user_id, path),
    KEY idx_dashboard_pins_user (user_id, position),
    CONSTRAINT fk_dashboard_pins_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

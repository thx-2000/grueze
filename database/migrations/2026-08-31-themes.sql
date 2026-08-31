-- Theme-System: eigene Themes werden hier gespeichert. Datei-Themes liegen
-- unter /themes und brauchen keine Tabelle.

CREATE TABLE IF NOT EXISTS themes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    tokens MEDIUMTEXT NOT NULL,
    based_on VARCHAR(80) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Laufende Instanz auf dem bisherigen Look halten. Neue Installationen ohne
-- diesen Wert starten mit dem Standard-Theme "hell".
INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('active_theme', 'signalfarbe');

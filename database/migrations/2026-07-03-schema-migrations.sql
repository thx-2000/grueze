CREATE TABLE IF NOT EXISTS schema_migrations (
    migration VARCHAR(190) NOT NULL PRIMARY KEY,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (migration) VALUES
('2026-07-02-app-settings'),
('2026-07-02-kontakt-geschlecht-und-anrede'),
('2026-07-02-tags-und-kontakt-logins'),
('2026-07-03-user-passkeys'),
('2026-07-03-schema-migrations');

-- Anmeldungen: zeigt jetzt auch, auf welcher Seite eine Sitzung zuletzt war
-- (aktualisiert sich bei jedem Request mit, wie last_seen_at).
ALTER TABLE user_sessions
    ADD COLUMN IF NOT EXISTS last_path VARCHAR(255) NULL AFTER user_agent;

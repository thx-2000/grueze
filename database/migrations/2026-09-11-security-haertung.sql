-- Security-Härtung (v1.0.0):
--  * password_resets.created_at für die Anti-Spam-Sperre (max. 1 Reset-Mail
--    je Konto pro 5 Minuten).
ALTER TABLE password_resets
    ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER token_hash;

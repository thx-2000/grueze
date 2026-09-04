-- Passwort-Reset-Link: Token wandert vom Query-String ins Pfad-Segment
-- (/passwort-neu/<token>). Damit der Link ohne mitgeschickte E-Mail-Adresse
-- funktioniert, wird der Datensatz über einen schnellen SHA-256-Index des
-- Tokens gefunden; die Gültigkeit prüft weiterhin der bcrypt-`token_hash`.
ALTER TABLE password_resets
    ADD COLUMN IF NOT EXISTS token_sha CHAR(64) NULL AFTER token_hash;

ALTER TABLE password_resets
    ADD KEY IF NOT EXISTS idx_password_resets_sha (token_sha);

-- Sicherheits-Härtung: schneller, konstantzeitiger Zugriff auf Einladungs-Tokens.
--
-- Bisher lief `findValidByToken` über alle offenen Einladungen und rechnete pro
-- Zeile einen bcrypt-Vergleich – bis zu 50 Stück je (unangemeldetem) Request.
-- Das ist ein billiger Rechen-DoS. Mit einem SHA-256-Index wird zuerst die eine
-- passende Zeile geholt; der bcrypt-Vergleich läuft dann genau einmal.
--
-- Vor der Migration ausgestellte Einladungen haben noch kein token_sha und
-- laufen über einen eng begrenzten Rückfallpfad, bis sie ablaufen.
ALTER TABLE registration_invites
    ADD COLUMN IF NOT EXISTS token_sha CHAR(64) NULL AFTER token_hash,
    ADD KEY IF NOT EXISTS idx_registration_invites_token_sha (token_sha);

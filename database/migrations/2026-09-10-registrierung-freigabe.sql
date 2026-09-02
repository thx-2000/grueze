-- Selbst-Registrierung Stufe 2: Freigabe-Warteschlange für unbekannte
-- Adressen (optionale Notiz „wer bin ich") + pseudonymer Quell-Hash fürs
-- Rate-Limit.
ALTER TABLE registration_invites
    ADD COLUMN IF NOT EXISTS note VARCHAR(500) NULL AFTER contact_id,
    ADD COLUMN IF NOT EXISTS ip_hash CHAR(64) NULL AFTER created_by;

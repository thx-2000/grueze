-- Zwei zusätzliche Kontaktfelder: Beruf/Tätigkeit und Webseite.
ALTER TABLE contacts
    ADD COLUMN IF NOT EXISTS beruf VARCHAR(160) NULL AFTER geburtstag,
    ADD COLUMN IF NOT EXISTS webseite VARCHAR(255) NULL AFTER beruf;

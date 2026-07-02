ALTER TABLE contacts
    ADD COLUMN IF NOT EXISTS geschlecht CHAR(1) NULL AFTER geburtsname;

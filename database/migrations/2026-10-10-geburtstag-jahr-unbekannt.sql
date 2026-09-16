-- Geburtstag: Tag & Monat lassen sich jetzt auch eintragen, wenn das Jahr
-- nicht bekannt ist. Das Datum landet weiterhin in `geburtstag` (mit einem
-- Platzhalterjahr, 1600 – ein Schaltjahr, damit auch der 29.2. geht); die
-- Markierung sorgt dafür, dass nirgends ein falsches Alter/Jahr auftaucht.
ALTER TABLE contacts
    ADD COLUMN IF NOT EXISTS geburtstag_jahr_unbekannt TINYINT(1) NOT NULL DEFAULT 0 AFTER geburtstag;

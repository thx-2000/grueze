-- „Weitere Personen": drei Nachbesserungen.
--  * Todesjahr (statt nur genaues Datum) und ein reiner „verstorben,
--    Datum/Jahr unbekannt"-Haken – bisher zählte nur ein exaktes Todesdatum
--    als verstorben, das reicht bei alten Lehrkräften oft nicht.
--  * Fächer als eigenes Feld (mehrere Begriffe, kommagetrennt), getrennt von
--    der freien Rolle/Gruppierung (role_label).
ALTER TABLE roster_people
    ADD COLUMN IF NOT EXISTS died_year SMALLINT UNSIGNED NULL AFTER born_on,
    ADD COLUMN IF NOT EXISTS deceased_unknown TINYINT(1) NOT NULL DEFAULT 0 AFTER died_on,
    ADD COLUMN IF NOT EXISTS subjects VARCHAR(255) NULL AFTER role_label;

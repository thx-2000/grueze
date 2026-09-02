-- Rollen bekommen einen frei editierbaren Anzeigenamen (label). Der interne
-- Schlüssel (name) bleibt fix, weil Berechtigungen und Feld-Sichtbarkeit
-- über den Rollennamen laufen.
ALTER TABLE roles ADD COLUMN IF NOT EXISTS label VARCHAR(80) NOT NULL DEFAULT '' AFTER name;

UPDATE roles SET label = CASE name
    WHEN 'admin'          THEN 'Admin'
    WHEN 'orga'           THEN 'Orga'
    WHEN 'stufenmitglied' THEN 'Stufenmitglied'
    WHEN 'betrachter'     THEN 'Betrachter'
    ELSE CONCAT(UPPER(SUBSTRING(name, 1, 1)), SUBSTRING(name, 2))
END
WHERE label = '';

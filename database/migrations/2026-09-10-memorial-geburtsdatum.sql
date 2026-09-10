-- Volles Geburtsdatum für Gedenk-Einträge (bisher nur das Jahr). Wenn gesetzt,
-- lässt sich damit das Lebensalter berechnen und anzeigen.
ALTER TABLE memorials
    ADD COLUMN IF NOT EXISTS born_on DATE NULL AFTER born_year;

-- Das mitgelieferte Datei-Theme "signalfarbe" heißt jetzt "signalfarbe" (neutrale
-- Distribution – "GRUEZE" ist ein instanzspezifischer Name). Instanzen, die es
-- explizit aktiv haben, mitziehen; der Look bleibt identisch, weil die
-- Token-Werte dieselben sind.
UPDATE app_settings
SET setting_value = 'signalfarbe'
WHERE setting_key = 'active_theme' AND setting_value = 'signalfarbe';

-- Ebenso, falls jemand ein eigenes Theme auf dem alten Slug basiert hatte.
UPDATE themes SET based_on = 'signalfarbe' WHERE based_on = 'signalfarbe';

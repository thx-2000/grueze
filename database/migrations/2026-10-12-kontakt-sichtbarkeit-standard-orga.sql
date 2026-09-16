-- Korrektur: „Wer darf die Kontaktdaten sehen?" (contact_visibility) sollte
-- von Anfang an datenschutzfreundlich auf „nur Orga-Team" stehen, nicht auf
-- „ganze Stufe". Betrifft auch alle Kontakte, die mit v1.70.0 bereits den
-- (falschen) Standard „stufe" bekommen haben – rückwirkend auf „orga"
-- gesetzt, damit niemand ungewollt für die ganze Stufe sichtbar bleibt.
ALTER TABLE contacts
    MODIFY COLUMN contact_visibility ENUM('stufe','orga') NOT NULL DEFAULT 'orga';

UPDATE contacts SET contact_visibility = 'orga';

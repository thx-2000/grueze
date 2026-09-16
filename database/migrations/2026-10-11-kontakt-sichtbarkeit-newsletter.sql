-- Drei neue, voneinander unabhängige Kontakt-Einstellungen:
--  * Newsletter/Rundmail-Abmeldung (newsletter_opt_out_at) – Kontakt bleibt
--    ganz normal im Adressbuch, bekommt aber keine Rundmails/automatischen
--    Grüße mehr. Selbst oder durchs Orga-Team setzbar.
--  * Verstecken (hidden_at/hidden_by, admin-only) – Kontakt verschwindet aus
--    Adressbuch, Suche, Mailings und Geburtstagen für alle außer Admin;
--    Daten bleiben vollständig erhalten. Eigenständig neben Archiv/Papierkorb.
--  * Sichtbarkeit der eigenen Kontaktdaten (contact_visibility) – Selbst-
--    Service-Wahl "nur Orga-Team" statt "ganze Stufe" für Adresse/Geburtstag/
--    Mail/Telefon, schränkt die global konfigurierte Rollen-Sichtbarkeit pro
--    Person weiter ein (erweitert sie nie).
ALTER TABLE contacts
    ADD COLUMN IF NOT EXISTS newsletter_opt_out_at DATETIME NULL AFTER deceased_at,
    ADD COLUMN IF NOT EXISTS hidden_at DATETIME NULL AFTER newsletter_opt_out_at,
    ADD COLUMN IF NOT EXISTS hidden_by INT UNSIGNED NULL AFTER hidden_at,
    ADD COLUMN IF NOT EXISTS contact_visibility ENUM('stufe','orga') NOT NULL DEFAULT 'stufe' AFTER hidden_by,
    ADD KEY IF NOT EXISTS idx_contacts_hidden (hidden_at);

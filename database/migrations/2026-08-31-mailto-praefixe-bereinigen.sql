-- Alt-Importdaten: einige Kontaktadressen wurden mit "mailto:"- bzw.
-- "tel:"-Praefix gespeichert. Das macht die Werte ungueltig (Formular laesst
-- sich dann nicht speichern) und erzeugt kaputte mailto:/tel:-Links.

UPDATE contact_emails
SET email = TRIM(SUBSTRING(email FROM 8))
WHERE email LIKE 'mailto:%';

UPDATE contact_phones
SET phone = TRIM(SUBSTRING(phone FROM 5))
WHERE phone LIKE 'tel:%';

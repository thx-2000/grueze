-- Spalte contacts.geschlecht in contacts.anrede umbenennen. Der interne Code
-- (m/w/leer) bleibt unverändert; das Feld steuert nur die Brief-Anrede und
-- heißt in der Oberfläche längst „Anrede". IF EXISTS, damit die Migration auch
-- dann durchläuft, wenn der ensureSchema-Fallback schon umbenannt hat.
ALTER TABLE contacts CHANGE COLUMN IF EXISTS geschlecht anrede CHAR(1) NULL;

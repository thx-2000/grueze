-- Dokumente: optionale PDF-Vorschau für Office-Formate (Word/Excel/
-- PowerPoint/ODF), wenn auf dem Server LibreOffice verfügbar ist – auf
-- Shared Hosting meist nicht der Fall, dann bleibt alles wie bisher
-- (Browser entscheidet selbst, meist Direkt-Download).

ALTER TABLE documents
    ADD COLUMN IF NOT EXISTS preview_path VARCHAR(255) NULL AFTER stored_path;

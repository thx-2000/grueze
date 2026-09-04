-- Dokumente: Unterordner (Verschachtelung). Berechtigungen bleiben pro
-- Ordner unabhängig – ein Unterordner erbt owner_group_id/visible_group_id
-- NICHT automatisch vom Elternordner, das bleibt bewusst einfach.

ALTER TABLE document_folders
    ADD COLUMN IF NOT EXISTS parent_id INT UNSIGNED NULL AFTER id,
    ADD KEY IF NOT EXISTS idx_document_folders_parent (parent_id);

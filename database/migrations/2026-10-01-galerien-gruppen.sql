-- Galerien für Gruppenleitung: eine Galerie kann einer Gruppe „gehören"
-- (owner_group_id – die Gruppenleitung darf sie verwalten, ohne globales
-- galleries.manage) und ihre Sichtbarkeit auf genau diese Gruppe einschränken
-- (visible_group_id – NULL bedeutet weiterhin „alle mit Ansehen-Recht").
ALTER TABLE galleries
    ADD COLUMN IF NOT EXISTS owner_group_id INT UNSIGNED NULL AFTER event_id,
    ADD COLUMN IF NOT EXISTS visible_group_id INT UNSIGNED NULL AFTER owner_group_id,
    ADD KEY IF NOT EXISTS idx_galleries_owner_group (owner_group_id),
    ADD KEY IF NOT EXISTS idx_galleries_visible_group (visible_group_id);

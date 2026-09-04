-- „Termine" als reine Ankündigungsseite (Teil B, Umbau Termine/Abstimmungen):
-- Titel, Zeitraum, Freitext-Info vom Orga-Team, Links (extern/Dokument/
-- Abstimmung), Sichtbarkeit für alle oder auf Personen/Gruppen/Tags
-- eingeschränkt. Getrennt vom bisherigen Abstimmungstool (Tabelle `events`,
-- jetzt unter /abstimmungen statt /termine).

CREATE TABLE IF NOT EXISTS announcements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    info TEXT NULL,
    location VARCHAR(190) NULL,
    starts_at DATE NULL,
    ends_at DATE NULL,
    audience_mode ENUM('all', 'restricted') NOT NULL DEFAULT 'all',
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_announcements_starts (starts_at),
    CONSTRAINT fk_announcements_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcement_audience (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT UNSIGNED NOT NULL,
    kind ENUM('contact', 'group', 'tag') NOT NULL,
    ref_id INT UNSIGNED NOT NULL,
    KEY idx_announcement_audience_announcement (announcement_id),
    CONSTRAINT fk_announcement_audience_announcement FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcement_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT UNSIGNED NOT NULL,
    label VARCHAR(190) NOT NULL,
    kind ENUM('extern', 'dokument', 'abstimmung') NOT NULL DEFAULT 'extern',
    url VARCHAR(500) NOT NULL,
    position INT NOT NULL DEFAULT 0,
    KEY idx_announcement_links_announcement (announcement_id),
    CONSTRAINT fk_announcement_links_announcement FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bestehende „Fester Termin"-Einträge automatisch als Ankündigung übernehmen
-- (TH-Entscheidung 2026-09-04). Freitext-Notizen (Uhrzeit/Kosten/Mitbringen)
-- wandern zusammen mit der Beschreibung in ein Info-Feld; Datum kommt vom
-- ersten (einzigen) Termin-Vorschlag. Sichtbarkeit „alle" (Standard).
INSERT INTO announcements (title, info, location, starts_at, audience_mode, created_by, created_at, updated_at)
SELECT
    e.title,
    NULLIF(TRIM(CONCAT_WS('\n\n',
        NULLIF(TRIM(e.description), ''),
        CASE WHEN NULLIF(TRIM(e.time_note), '') IS NOT NULL THEN CONCAT('Uhrzeit: ', TRIM(e.time_note)) END,
        CASE WHEN NULLIF(TRIM(e.cost_note), '') IS NOT NULL THEN CONCAT('Kosten: ', TRIM(e.cost_note)) END,
        CASE WHEN NULLIF(TRIM(e.bring_note), '') IS NOT NULL THEN CONCAT('Mitbringen: ', TRIM(e.bring_note)) END
    )), '') AS info,
    e.location,
    (SELECT eo.option_date FROM event_options eo WHERE eo.event_id = e.id ORDER BY eo.option_date LIMIT 1) AS starts_at,
    'all',
    e.created_by,
    e.created_at,
    e.updated_at
FROM events e
WHERE e.kind = 'fixed_date'
  AND NOT EXISTS (
      SELECT 1 FROM announcements a2
      WHERE a2.title = e.title AND a2.created_by = e.created_by AND a2.created_at = e.created_at
  );

-- Quell-Termine aus der aktiven Abstimmungs-Liste nehmen (Daten bleiben
-- erhalten, im Archiv weiter einsehbar).
UPDATE events SET status = 'archived' WHERE kind = 'fixed_date' AND status <> 'archived';

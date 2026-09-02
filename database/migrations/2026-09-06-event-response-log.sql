-- Abstimmungs-Verlauf: jede gespeicherte (neue oder geänderte) Rückmeldung
-- wird angehängt, damit Admins nachvollziehen können, wer wann wie abgestimmt
-- hat. Nur für Admins sichtbar.
CREATE TABLE IF NOT EXISTS event_response_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    participant_id INT UNSIGNED NOT NULL,
    option_id INT UNSIGNED NOT NULL,
    answer ENUM('yes', 'maybe', 'no') NOT NULL,
    via ENUM('token', 'login') NOT NULL DEFAULT 'token',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_event_response_log_participant (participant_id),
    CONSTRAINT fk_event_response_log_participant FOREIGN KEY (participant_id) REFERENCES event_participants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

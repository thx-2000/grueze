-- Bereich „Termine": Terminfindung mit Datumsabstimmung. Teilnehmerkreis kommt
-- aus dem Adressbuch, Abstimmen geht per personengebundenem Token auch ohne
-- Login. `event_token_hits` protokolliert Stimmabgaben (pseudonymer
-- Quell-Hash), damit sichtbar wird, wenn über einen Link mehrere Geräte
-- abstimmen.

CREATE TABLE IF NOT EXISTS events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    description TEXT NULL,
    location VARCHAR(190) NULL,
    time_note VARCHAR(190) NULL,
    cost_note VARCHAR(190) NULL,
    bring_note VARCHAR(190) NULL,
    status ENUM('open', 'decided', 'archived') NOT NULL DEFAULT 'open',
    decided_option_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_events_status (status),
    CONSTRAINT fk_events_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_options (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    option_date DATE NULL,
    option_time VARCHAR(40) NULL,
    label VARCHAR(190) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_event_options_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_participants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NOT NULL,
    token CHAR(32) NOT NULL,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_event_participant_token (token),
    UNIQUE KEY uq_event_participant (event_id, contact_id),
    CONSTRAINT fk_event_participants_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_event_participants_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_responses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    participant_id INT UNSIGNED NOT NULL,
    option_id INT UNSIGNED NOT NULL,
    answer ENUM('yes', 'maybe', 'no') NOT NULL,
    via ENUM('token', 'login') NOT NULL DEFAULT 'token',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_response (participant_id, option_id),
    CONSTRAINT fk_event_responses_participant FOREIGN KEY (participant_id) REFERENCES event_participants(id) ON DELETE CASCADE,
    CONSTRAINT fk_event_responses_option FOREIGN KEY (option_id) REFERENCES event_options(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_token_hits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    participant_id INT UNSIGNED NOT NULL,
    source_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_event_token_hits_participant (participant_id),
    CONSTRAINT fk_event_token_hits_participant FOREIGN KEY (participant_id) REFERENCES event_participants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

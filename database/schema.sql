CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(80) NOT NULL DEFAULT '',
    description VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at DATETIME NULL,
    UNIQUE KEY uniq_users_contact (contact_id),
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contacts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vorname VARCHAR(120) NOT NULL,
    nachname VARCHAR(120) NOT NULL,
    geburtsname VARCHAR(120) NULL,
    geschlecht CHAR(1) NULL,
    category_id INT UNSIGNED NULL,
    geburtstag DATE NULL,
    strasse VARCHAR(190) NOT NULL,
    plz VARCHAR(30) NOT NULL,
    ort VARCHAR(120) NOT NULL,
    land VARCHAR(120) NULL,
    notizen TEXT NULL,
    photo_path VARCHAR(255) NULL,
    created_by INT UNSIGNED NOT NULL,
    updated_by INT UNSIGNED NOT NULL,
    archived_at DATETIME NULL,
    deleted_at DATETIME NULL,
    retired_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_contacts_archived (archived_at),
    KEY idx_contacts_deleted (deleted_at),
    CONSTRAINT fk_contacts_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_contacts_created_by FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT fk_contacts_updated_by FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_emails (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contact_id INT UNSIGNED NOT NULL,
    email VARCHAR(190) NOT NULL,
    label VARCHAR(80) NULL,
    CONSTRAINT fk_contact_emails_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_phones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contact_id INT UNSIGNED NOT NULL,
    phone VARCHAR(80) NOT NULL,
    label VARCHAR(80) NOT NULL,
    CONSTRAINT fk_contact_phones_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_tags (
    contact_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (contact_id, tag_id),
    CONSTRAINT fk_contact_tags_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
    CONSTRAINT fk_contact_tags_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users
    ADD CONSTRAINT fk_users_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS contact_data_checks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contact_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    UNIQUE KEY uq_contact_data_checks_token (token_hash),
    KEY idx_contact_data_checks_contact (contact_id),
    CONSTRAINT fk_cdc_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
    CONSTRAINT fk_cdc_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    token_sha CHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    KEY idx_password_resets_sha (token_sha),
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_passkeys (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    credential_id VARBINARY(255) NOT NULL,
    public_key_pem TEXT NOT NULL,
    algorithm INT NOT NULL DEFAULT -7,
    sign_count INT UNSIGNED NOT NULL DEFAULT 0,
    transports TEXT NULL,
    label VARCHAR(190) NOT NULL,
    aaguid CHAR(36) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    last_used_ip VARCHAR(64) NULL,
    UNIQUE KEY uniq_user_passkeys_credential (credential_id),
    KEY idx_user_passkeys_user (user_id),
    CONSTRAINT fk_user_passkeys_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    ip_address VARCHAR(64) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    successful TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_login_attempts_lookup (email, ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NULL,
    action ENUM('created', 'updated', 'deleted', 'impersonation_started', 'impersonation_stopped') NOT NULL,
    details TEXT NOT NULL,
    changes LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_log_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_audit_log_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mail_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NULL,
    empfaenger_email VARCHAR(190) NOT NULL,
    betreff VARCHAR(190) NOT NULL,
    status ENUM('gesendet', 'fehlgeschlagen') NOT NULL,
    fehlermeldung TEXT NULL,
    gesendet_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mail_log_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_mail_log_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE app_settings (
    setting_key VARCHAR(120) NOT NULL PRIMARY KEY,
    setting_value MEDIUMTEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ical_uid CHAR(32) NULL,
    title VARCHAR(190) NOT NULL,
    kind ENUM('date_poll', 'fixed_date', 'poll') NOT NULL DEFAULT 'date_poll',
    group_id INT UNSIGNED NULL,
    description TEXT NULL,
    location VARCHAR(190) NULL,
    time_note VARCHAR(190) NULL,
    cost_note VARCHAR(190) NULL,
    bring_note VARCHAR(190) NULL,
    status ENUM('open', 'closed', 'decided', 'archived') NOT NULL DEFAULT 'open',
    closes_at DATETIME NULL,
    result_recipients VARCHAR(16) NULL,
    reminder_sent_at DATETIME NULL,
    result_mail_sent_at DATETIME NULL,
    remind_days_before TINYINT UNSIGNED NULL,
    event_reminder_sent_at DATETIME NULL,
    decided_option_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_events_status (status),
    KEY idx_events_closes_at (closes_at),
    KEY idx_events_group (group_id),
    KEY idx_events_ical_uid (ical_uid),
    CONSTRAINT fk_events_user FOREIGN KEY (created_by) REFERENCES users(id)
    -- events.group_id: kein DB-Fremdschlüssel (Tabellenreihenfolge); wird in
    -- GroupRepository::delete() beim Löschen der Gruppe auf NULL gesetzt.
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_options (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    option_date DATE NULL,
    option_time VARCHAR(40) NULL,
    label VARCHAR(190) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_event_options_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_participants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NOT NULL,
    token CHAR(32) NOT NULL,
    note VARCHAR(500) NULL,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_event_participant_token (token),
    UNIQUE KEY uq_event_participant (event_id, contact_id),
    CONSTRAINT fk_event_participants_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_event_participants_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_responses (
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

CREATE TABLE event_token_hits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    participant_id INT UNSIGNED NOT NULL,
    source_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_event_token_hits_participant (participant_id),
    CONSTRAINT fk_event_token_hits_participant FOREIGN KEY (participant_id) REFERENCES event_participants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_response_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    participant_id INT UNSIGNED NOT NULL,
    option_id INT UNSIGNED NOT NULL,
    answer ENUM('yes', 'maybe', 'no') NOT NULL,
    via ENUM('token', 'login') NOT NULL DEFAULT 'token',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_event_response_log_participant (participant_id),
    CONSTRAINT fk_event_response_log_participant FOREIGN KEY (participant_id) REFERENCES event_participants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_groups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(500) NULL,
    is_open TINYINT(1) NOT NULL DEFAULT 0,
    mail_locked TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_contact_groups_name (name),
    CONSTRAINT fk_contact_groups_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_group_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NOT NULL,
    role ENUM('member', 'lead') NOT NULL DEFAULT 'member',
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_group_member (group_id, contact_id),
    KEY idx_group_member_contact (contact_id),
    CONSTRAINT fk_cgm_group FOREIGN KEY (group_id) REFERENCES contact_groups(id) ON DELETE CASCADE,
    CONSTRAINT fk_cgm_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_group_join_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NOT NULL,
    message VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_group_join_request (group_id, contact_id),
    KEY idx_group_join_request_group (group_id),
    CONSTRAINT fk_gjr_group FOREIGN KEY (group_id) REFERENCES contact_groups(id) ON DELETE CASCADE,
    CONSTRAINT fk_gjr_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS group_mail_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id INT UNSIGNED NOT NULL,
    sender_user_id INT UNSIGNED NULL,
    sender_name VARCHAR(190) NOT NULL,
    subject VARCHAR(190) NOT NULL,
    recipient_count INT NOT NULL DEFAULT 0,
    error_count INT NOT NULL DEFAULT 0,
    soft_limit_hit TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_group_mail_log_sender (sender_user_id, created_at),
    KEY idx_group_mail_log_group (group_id, created_at),
    CONSTRAINT fk_gml_group FOREIGN KEY (group_id) REFERENCES contact_groups(id) ON DELETE CASCADE,
    CONSTRAINT fk_gml_user FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS greetings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    occasion ENUM('birthday', 'christmas') NOT NULL,
    text TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_greetings_occasion (occasion, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO greetings (occasion, text, sort_order)
SELECT occasion, text, sort_order FROM (
    SELECT 'birthday' AS occasion, 'Alles Gute zum Geburtstag, {Vorname}! Ich denk an dich und hoffe, dein Tag wird richtig schön.' AS text, 1 AS sort_order UNION ALL
    SELECT 'birthday', 'Hey {Vorname}, herzlichen Glückwunsch! Lass dich heute feiern und ordentlich verwöhnen.', 2 UNION ALL
    SELECT 'birthday', 'Happy Birthday, {Vorname}! Ich wünsch dir ein Jahr voller guter Momente und Menschen, die dir wichtig sind.', 3 UNION ALL
    SELECT 'birthday', 'Alles Liebe zum Geburtstag, {Vorname}. Schön, dass es dich gibt.', 4 UNION ALL
    SELECT 'birthday', '{Vorname}, ich wünsch dir von Herzen alles Gute – Gesundheit, Lachen und Zeit für die Dinge, die dir guttun.', 5 UNION ALL
    SELECT 'birthday', 'Herzlichen Glückwunsch, {Vorname}! Ich hoffe, du hast heute jemanden um dich, der dich hochleben lässt.', 6 UNION ALL
    SELECT 'birthday', 'Alles Gute, {Vorname}! Mögen dir im neuen Lebensjahr mehr Türen aufgehen als zufallen.', 7 UNION ALL
    SELECT 'birthday', '{Vorname}, feier schön! Du hast einen richtig guten Tag verdient.', 8 UNION ALL
    SELECT 'birthday', 'Zum Geburtstag wünsch ich dir alles Gute, {Vorname} – und dass du dir heute selbst etwas Gutes tust.', 9 UNION ALL
    SELECT 'birthday', 'Hey {Vorname}, ich denk an dich zum Geburtstag. Bleib, wie du bist.', 10 UNION ALL
    SELECT 'birthday', 'Alles Liebe, {Vorname}! Ich hoffe, das neue Jahr bringt dir viel, worüber du dich freuen kannst.', 11 UNION ALL
    SELECT 'birthday', 'Herzlichen Glückwunsch, {Vorname}! Auf ein Jahr mit weniger Stress und mehr von dem, was dir Freude macht.', 12 UNION ALL
    SELECT 'birthday', '{Vorname}, alles Gute zum Geburtstag! Ich freu mich, wenn wir uns bald mal wiedersehen.', 13 UNION ALL
    SELECT 'birthday', 'Zum Geburtstag alles Gute, {Vorname}. Lass es dir heute richtig gutgehen.', 14 UNION ALL
    SELECT 'birthday', 'Happy Birthday, {Vorname}! Ich wünsch dir ein Lächeln im Gesicht und nette Menschen an deiner Seite.', 15 UNION ALL
    SELECT 'birthday', '{Vorname}, herzlichen Glückwunsch! Nimm dir heute Zeit für dich – du hast sie dir verdient.', 16 UNION ALL
    SELECT 'birthday', 'Alles Gute zum Ehrentag, {Vorname}! Ich hoff, du wirst heute ordentlich gefeiert.', 17 UNION ALL
    SELECT 'birthday', '{Vorname}, ich schick dir die herzlichsten Geburtstagsgrüße. Mach was Schönes draus.', 18 UNION ALL
    SELECT 'birthday', 'Zum Geburtstag wünsch ich dir Gesundheit, gute Laune und ein paar richtig schöne Überraschungen, {Vorname}.', 19 UNION ALL
    SELECT 'birthday', 'Hey {Vorname}, alles Liebe zum Geburtstag! Schön, dich zu kennen.', 20 UNION ALL
    SELECT 'birthday', 'Herzlichen Glückwunsch, {Vorname}! Ich hoffe, das kommende Jahr ist gut zu dir.', 21 UNION ALL
    SELECT 'birthday', 'Alles Gute, {Vorname} – und danke, dass du so bist, wie du bist. Feier schön!', 22 UNION ALL
    SELECT 'christmas', 'Frohe Weihnachten, {Vorname}! Ich wünsch dir ruhige Tage und einen guten Start ins neue Jahr.', 1 UNION ALL
    SELECT 'christmas', 'Hey {Vorname}, schöne Feiertage! Ich hoffe, du kommst zur Ruhe und hast nette Menschen um dich.', 2 UNION ALL
    SELECT 'christmas', '{Vorname}, ich denk an dich zum Fest. Hab eine warme, entspannte Weihnachtszeit.', 3 UNION ALL
    SELECT 'christmas', 'Frohe Weihnachten, {Vorname}! Lass es dir gutgehen und genieß die freien Tage.', 4 UNION ALL
    SELECT 'christmas', 'Dir und deinen Liebsten frohe Weihnachten, {Vorname}. Kommt gut und gesund ins neue Jahr.', 5 UNION ALL
    SELECT 'christmas', '{Vorname}, schöne Feiertage! Ich hoffe, zwischen allem Trubel bleibt Zeit für dich.', 6 UNION ALL
    SELECT 'christmas', 'Frohes Fest, {Vorname}! Ich wünsch dir gemütliche Stunden und ein bisschen Pause vom Alltag.', 7 UNION ALL
    SELECT 'christmas', 'Hey {Vorname}, ich schick dir herzliche Weihnachtsgrüße. Hab eine schöne Zeit.', 8 UNION ALL
    SELECT 'christmas', 'Frohe Weihnachten, {Vorname}! Auf ein neues Jahr mit guten Begegnungen – vielleicht auch mal wieder mit dir.', 9 UNION ALL
    SELECT 'christmas', '{Vorname}, ich wünsch dir besinnliche Feiertage und einen entspannten Rutsch ins neue Jahr.', 10 UNION ALL
    SELECT 'christmas', 'Schöne Weihnachten, {Vorname}! Ich hoffe, das Jahr klingt für dich versöhnlich aus.', 11 UNION ALL
    SELECT 'christmas', 'Frohes Fest und alles Gute fürs neue Jahr, {Vorname}. Pass auf dich auf.', 12 UNION ALL
    SELECT 'christmas', 'Hey {Vorname}, frohe Weihnachten! Mach es dir gemütlich und lass den Stress mal liegen.', 13 UNION ALL
    SELECT 'christmas', '{Vorname}, ich denk an dich zu den Feiertagen. Hab es schön und komm gut ins neue Jahr.', 14 UNION ALL
    SELECT 'christmas', 'Frohe Weihnachten, {Vorname}! Ich wünsch dir warmes Licht, gutes Essen und Zeit mit den richtigen Leuten.', 15 UNION ALL
    SELECT 'christmas', 'Dir eine friedliche Weihnachtszeit, {Vorname}, und ein neues Jahr, das gut zu dir ist.', 16 UNION ALL
    SELECT 'christmas', '{Vorname}, schöne Feiertage! Danke, dass du das Jahr über dabei warst.', 17 UNION ALL
    SELECT 'christmas', 'Frohe Weihnachten, {Vorname} – und für das neue Jahr wünsch ich dir Gesundheit und viel Grund zur Freude.', 18
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM greetings);


CREATE TABLE registration_invites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    contact_id INT UNSIGNED NULL,
    note VARCHAR(500) NULL,
    token_hash VARCHAR(255) NOT NULL,
    created_by INT UNSIGNED NULL,
    ip_hash CHAR(64) NULL,
    status ENUM('pending', 'awaiting_approval', 'used', 'revoked') NOT NULL DEFAULT 'pending',
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_registration_invites_email (email),
    KEY idx_registration_invites_status (status),
    CONSTRAINT fk_registration_invites_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
    CONSTRAINT fk_registration_invites_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (name, label, description) VALUES
('admin', 'Admin', 'Vollzugriff inklusive Benutzerverwaltung'),
('orga', 'Team', 'Verwaltet Kontakte, Mailings und Einstellungen'),
('stufenmitglied', 'Mitglied', 'Kann Namen und Gruppen sehen und einzelne Kontaktanfragen senden');

-- Verlauf der gesendeten Serien-Mails: Inhalt + Empfängerliste je Versand,
-- damit Sende-Berechtigte frühere Nachrichten ansehen und erneut verschicken
-- können.
CREATE TABLE IF NOT EXISTS sent_mails (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    sender_name VARCHAR(190) NOT NULL DEFAULT '',
    kind VARCHAR(20) NOT NULL DEFAULT 'rundmail',
    subject VARCHAR(255) NOT NULL,
    subject_prefix VARCHAR(120) NOT NULL DEFAULT '',
    body MEDIUMTEXT NOT NULL,
    salutation_mode VARCHAR(20) NOT NULL DEFAULT 'auto',
    sender_key VARCHAR(64) NOT NULL DEFAULT '',
    reply_to_key VARCHAR(64) NOT NULL DEFAULT '',
    recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
    sent_count INT UNSIGNED NOT NULL DEFAULT 0,
    failed_count INT UNSIGNED NOT NULL DEFAULT 0,
    recipients LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sent_mails_user (user_id, created_at),
    INDEX idx_sent_mails_created (created_at),
    CONSTRAINT fk_sent_mails_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin-Benachrichtigungen per Mail: Abos (wer will was wie oft wissen)
-- und eine Warteschlange dafür aufgelaufener Ereignisse bis zum Versand.

CREATE TABLE IF NOT EXISTS notification_subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    event_type ENUM('login', 'change') NOT NULL,
    scope ENUM('all', 'contact', 'group') NOT NULL,
    target_id INT UNSIGNED NULL,
    frequency ENUM('sofort', 'alle_5_min', 'alle_10_min', 'stuendlich', 'alle_6_stunden', 'taeglich', 'woechentlich', 'monatlich') NOT NULL DEFAULT 'sofort',
    last_sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notification_subscriptions_user (user_id),
    KEY idx_notification_subscriptions_match (event_type, scope, target_id),
    CONSTRAINT fk_notification_subscriptions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_queue (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subscription_id INT UNSIGNED NOT NULL,
    occurred_at DATETIME NOT NULL,
    summary VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notification_queue_subscription (subscription_id),
    CONSTRAINT fk_notification_queue_subscription FOREIGN KEY (subscription_id) REFERENCES notification_subscriptions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

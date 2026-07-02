<?php

return [
    'app' => [
        'name' => 'Adress-Zentrale',
        'base_url' => 'https://example.org',
        'session_name' => 'abi_adress_zentrale',
        'session_timeout' => 1800,
        'force_https' => true,
        'debug' => true,
    ],
    'database' => [
        'dsn' => 'mysql:host=localhost;dbname=abi_adress_zentrale;charset=utf8mb4',
        'username' => 'db_user',
        'password' => 'db_password',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    ],
    'mail' => [
        'driver' => 'smtp',
        'batch_size' => 3,
        'send_delay_seconds' => 1,
        'max_attachment_size_total' => 10485760,
        'default_sender_key' => 'orga',
        'default_reply_to_key' => 'orga_reply',
        'allowed_attachment_types' => [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
        'identities' => [
            [
                'key' => 'orga',
                'name' => 'Mailer',
                'email' => 'mailer@example.org',
                'smtp_host' => 'smtp.example.org',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
                'smtp_username' => 'smtp_username',
                'smtp_password' => 'smtp_password',
            ],
        ],
        'reply_to_options' => [
            [
                'key' => 'orga_reply',
                'name' => 'Orga-Team',
                'email' => 'kontakt@example.org',
            ],
            [
                'key' => 'orga',
                'name' => 'Mailer',
                'email' => 'mailer@example.org',
            ],
        ],
    ],
    'security' => [
        'login_max_attempts' => 5,
        'login_lock_minutes' => 10,
        'password_reset_expires_minutes' => 60,
        'private_contact_detail_roles' => ['admin', 'orga', 'stufenmitglied'],
        'contact_detail_visibility' => [
            'address' => ['admin', 'orga', 'stufenmitglied'],
            'birthday' => ['admin', 'orga', 'stufenmitglied'],
            'emails' => ['admin', 'orga', 'stufenmitglied'],
            'phones' => ['admin', 'orga', 'stufenmitglied'],
            'notes' => ['admin', 'orga', 'stufenmitglied'],
            'login' => ['admin', 'orga', 'stufenmitglied'],
        ],
        'photo_max_size' => 2097152,
        'allowed_photo_types' => [
            'image/jpeg',
            'image/png',
            'image/webp',
        ],
    ],
    'defaults' => [
        'country' => 'Deutschland',
        'mail_footer' => "Du erhältst diese Nachricht, weil du auf dem Verteiler eingetragen bist.\nWir möchten den Mailverkehr möglichst gering halten und schreiben daher nur, wenn es wirklich etwas Relevantes gibt.\nAntworten auf diese Nachricht gehen an das Orga-Team.\nFalls unsere Nachrichten fälschlich als Spam erkannt werden, nimm bitte kontakt@example.org und mailer@example.org in dein Adressbuch auf.\nWenn du keine weiteren Nachrichten erhalten möchtest, schreibe bitte an kontakt@example.org. Wir nehmen dich dann aus dem Verteiler.",
        'phone_labels' => [
            'Mobil',
            'Mobil 2',
            'Festnetz privat',
            'Festnetz geschäftlich',
            'Sonstige',
        ],
        'role_descriptions' => [
            'admin' => 'Vollzugriff inklusive Benutzerverwaltung',
            'orga' => 'Organisationsteam mit Verwaltungsrechten',
            'stufenmitglied' => 'Kann Kontakte pflegen und E-Mails kopieren',
            'betrachter' => 'Kann Kontakte in reduzierter Ansicht sehen',
        ],
    ],
];

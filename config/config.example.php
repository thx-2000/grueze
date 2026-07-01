<?php

return [
    'app' => [
        'name' => 'Abi Adress Zentrale',
        'base_url' => 'https://example.com',
        'session_name' => 'abi_adress_zentrale',
        'session_timeout' => 1800,
        'force_https' => true,
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
                'name' => 'Abi Orga',
                'email' => 'orga@example.com',
                'smtp_host' => 'smtp.example.com',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
                'smtp_username' => 'orga@example.com',
                'smtp_password' => 'smtp_password',
            ],
        ],
    ],
    'security' => [
        'login_max_attempts' => 5,
        'login_lock_minutes' => 10,
        'password_reset_expires_minutes' => 60,
        'photo_max_size' => 2097152,
        'allowed_photo_types' => [
            'image/jpeg',
            'image/png',
            'image/webp',
        ],
    ],
    'defaults' => [
        'country' => 'Deutschland',
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
            'betrachter' => 'Kann Kontakte ansehen und E-Mails kopieren',
        ],
    ],
];


<?php

// Vorlage für eine neue Instanz. Nach config/config.php kopieren und anpassen.
// Instanzspezifische Texte (Name, Rechtstexte, Mail-Fuß) lassen sich nach dem
// ersten Start auch über die Oberfläche pflegen (Verwaltung → Branding /
// Rechtliches / Mail-Einstellungen). Siehe docs/NEUE-INSTANZ.md.

return [
    'app' => [
        'name' => 'Adress-Zentrale',
        'base_url' => 'https://example.org',
        'session_name' => 'adress_zentrale',
        'session_timeout' => 1800,
        'force_https' => true,
        'debug' => false,
        // Offene DB-Migrationen nach einem Upload automatisch anwenden. Standard
        // aus: im Normalfall macht das ein Admin bewusst über
        // "Verwaltung → Aktualisieren" (dort mit optionaler Vorab-Sicherung).
        'auto_migrate' => false,
    ],

    // Startwerte für Name, Links und Texte. Auflösung: Admin-Oberfläche
    // (app_settings) > diese Sektion > eingebaute Defaults. Einzelne Schlüssel
    // dürfen fehlen; leere Strings zählen als "nicht gesetzt".
    // - app_name / short_name: der Name DIESER Instanz.
    // - system_label / product_url: der Produktname (GRUEZE). Nur für eine
    //   vollständig eigene Marke überschreiben bzw. leeren.
    // Farben, Schriften und Ecken stecken im Theme-System (Ordner themes/).
    'branding' => [
        'app_name' => 'Adress-Zentrale',
        'short_name' => 'Adress-Zentrale',
        'system_label' => 'GRUEZE',
        'product_url' => 'https://github.com/thx-2000/grueze',
        'public_site_label' => '',
        'public_site_url' => '',
        'support_email' => '',
        'login_headline' => 'Interner Bereich',
        'login_intro' => 'Hier pflegt ihr Kontakte, Mailings und interne Organisationsdaten an einem Ort.',
        'login_public_hint' => 'Infos zur Gruppe und die öffentliche Startseite findet ihr hier.',
        'sidebar_copy' => 'Kontakte, Mailings und Organisation an einem Ort.',
    ],

    'database' => [
        'dsn' => 'mysql:host=localhost;dbname=adress_zentrale;charset=utf8mb4',
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
        'default_sender_key' => 'default',
        'default_reply_to_key' => 'default_reply',
        'allowed_attachment_types' => [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
        // Nur der erste Eintrag wird als Start-Identität übernommen; danach
        // über Verwaltung → Mail-Einstellungen pflegbar.
        'identities' => [
            [
                'key' => 'default',
                'name' => 'Mailer',
                'email' => 'mailer@example.org',
                'smtp_host' => 'smtp.example.org',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
                'smtp_username' => 'smtp_username',
                'smtp_password' => 'smtp_password',
                'imap_host' => 'imap.example.org',
                'imap_port' => 993,
                'imap_encryption' => 'ssl',
                'imap_username' => 'imap_username',
                'imap_password' => 'imap_password',
                'imap_sent_mailboxes' => [
                    'INBOX.Sent',
                    'Sent',
                    'INBOX.Gesendet',
                    'Gesendet',
                ],
            ],
        ],
        'reply_to_options' => [
            [
                'key' => 'default_reply',
                'name' => 'Team',
                'email' => 'team@example.org',
            ],
        ],
    ],
    'security' => [
        'login_max_attempts' => 5,
        'login_lock_minutes' => 10,
        'password_reset_expires_minutes' => 60,
        'private_contact_detail_roles' => ['admin', 'orga'],
        'contact_detail_visibility' => [
            'address' => ['admin', 'orga'],
            'birthday' => ['admin', 'orga'],
            'emails' => ['admin', 'orga'],
            'phones' => ['admin', 'orga'],
            'notes' => ['admin', 'orga'],
            'login' => ['admin', 'orga'],
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
        // Leer lassen = die eingebauten (branding-abhängigen) Vorgaben nutzen.
        // Über Verwaltung → Mail-Einstellungen / Rechtliches pflegbar.
        // In mail_footer und subject_prefixes werden {name} (Instanzname) und
        // {kurzname} beim Versand ersetzt.
        'mail_footer' => '',
        'subject_prefixes' => ['[{kurzname}]'],
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
            'stufenmitglied' => 'Kann Namen und Gruppen sehen und einzelne Kontaktanfragen senden',
            'betrachter' => 'Kann Kontakte in reduzierter Ansicht sehen',
        ],
    ],
];

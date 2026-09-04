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
        // Geheimer Schlüssel für den Cron-Endpunkt /intern/cron. Nur mit diesem
        // Schlüssel läuft die Abstimmungs-Automatik (Fristen schließen,
        // Erinnerungen, Ergebnis-Mails) verlässlich. Beliebige lange
        // Zufallskette; leer lassen deaktiviert die URL (dann greift nur die
        // gedrosselte Rückfallebene bei Seitenaufrufen). Siehe docs/NEUE-INSTANZ.md.
        'cron_key' => '',
    ],

    // Startwerte für Name, Links und Texte. Auflösung: Admin-Oberfläche
    // (app_settings) > diese Sektion > eingebaute Defaults. Einzelne Schlüssel
    // dürfen fehlen; leere Strings zählen als "nicht gesetzt".
    // - app_name / short_name: der Name DIESER Instanz.
    // - system_label / product_url / product_donate_url: alles zum Produkt
    //   (GRUEZE) selbst. product_donate_url erscheint dezent unten im
    //   Verwaltungs-Hub (nur Admins). Für eine eigene Marke überschreiben,
    //   für kein Spendenlink product_donate_url auf '' setzen.
    // Farben, Schriften und Ecken stecken im Theme-System (Ordner themes/).
    'branding' => [
        'app_name' => 'Adress-Zentrale',
        'short_name' => 'Adress-Zentrale',
        'system_label' => 'GRUEZE',
        'product_url' => 'https://github.com/thx-2000/grueze',
        'product_donate_url' => 'https://buymeacoffee.com/thomashageleit',
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
        // Wie lange der Verlauf gesendeter Serien-Mails aufgehoben wird (Tage).
        'sent_retention_days' => 365,
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
        'login_max_attempts_ip' => 20,
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
        'import_max_size' => 5242880,
        // Aufbewahrung (Tage): Login-Versuche und pseudonyme Abstimmungs-
        // Quellhashes werden nach dieser Zeit automatisch gelöscht.
        'login_attempts_retention_days' => 30,
        'token_hit_retention_days' => 120,
        // Anmelde-Sitzungen (Verwaltung → Anmeldungen) nach dieser Zeit löschen.
        'session_retention_days' => 90,
        // IP-Adressen angemeldeter Sitzungen speichern und in „Verwaltung →
        // Anmeldungen" anzeigen? Standard: aus (datenschutzfreundlich). Auf
        // `true` setzen, wenn die IP fürs Nachvollziehen gebraucht wird und die
        // Datenschutzerklärung das abdeckt. Login-Versuche werden unabhängig
        // davon immer nur pseudonym (gehasht) abgelegt.
        'store_ip' => false,
        // Beliebige zufällige Zeichenkette. Macht die pseudonymen IP-Hashes
        // (Rate-Limits, „mehrere Geräte"-Erkennung) unumkehrbar. Leer lassen
        // heißt: schwächere, aber funktionierende Hashes.
        'hash_pepper' => '',
        // Schlüssel für die „at rest"-Verschlüsselung sensibler Einstellungen
        // (Mailserver-Passwörter). Leer lassen ist ok: dann erzeugt die App
        // beim ersten Start automatisch `storage/app.key` (0600, nicht im
        // Deployment/Backup). Ein hier gesetzter Wert hat Vorrang – praktisch,
        // wenn mehrere App-Server dieselbe DB teilen.
        'secret_key' => '',
        'allowed_photo_types' => [
            'image/jpeg',
            'image/png',
            'image/webp',
        ],
    ],
    // Gruppen-Mail (Stufe C): weiche Tagesgrenze je Person – darüber geht die
    // Mail trotzdem raus, aber das Admin-Team wird informiert. Für Admins gilt
    // keine Grenze. `mail_max_recipients` schützt vor Timeouts auf Shared
    // Hosting – größere Gruppen bitte über „Nachrichten" (Rundmail) anschreiben.
    'groups' => [
        'mail_soft_limit' => 2,
        'mail_max_recipients' => 250,
    ],

    'contacts' => [
        // Wie lange ein „Daten-Check"-Link (ohne Login, /meine-daten/<token>)
        // gültig ist. Danach muss die Verwaltung einen neuen erzeugen.
        'data_check_days' => 30,
    ],

    // Galerien: Foto-/Video-Sammlungen. Die Dateien liegen unter storage/media/
    // (außerhalb des Webroots) und sind NICHT im ZIP-Backup – separat sichern.
    'media' => [
        // Obergrenzen je Datei. Videos brauchen auf Shared Hosting oft eine
        // eigene php.ini mit passendem upload_max_filesize / post_max_size.
        'max_image_bytes' => 25165824,   // 24 MiB
        'max_video_bytes' => 524288000,  // 500 MiB
        // Kantenlänge (längste Seite) der erzeugten Varianten.
        'thumb_max_edge' => 400,
        'web_max_edge' => 1600,
        // Papierkorb: gelöschte Galerien/Medien nach so vielen Tagen endgültig
        // entfernen (Dateien inklusive). 0 = nie automatisch.
        'trash_days' => 30,
        // Obergrenze für die Gesamt-Sicherung „Alle Medien sichern" (und den
        // Import). Darüber bittet die Oberfläche, einzelne Galerien zu sichern.
        'backup_max_bytes' => 2147483648, // 2 GiB
        // Pfad zum ImageMagick-Binary – nur nötig, wenn die PHP-Erweiterung
        // imagick fehlt UND HEIC-Bilder umgewandelt werden sollen (all-inkl).
        'convert_bin' => '/usr/bin/convert',
        'allowed_image_types' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'image/heic',
            'image/heif',
        ],
        'allowed_video_types' => [
            'video/mp4',
            'video/webm',
            'video/quicktime',
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

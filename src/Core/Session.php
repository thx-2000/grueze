<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name((string) Config::get('app.session_name', 'abi_app'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => (bool) Config::get('app.force_https', true),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        session_start();
        self::enforceTimeout();
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
        $_SESSION['_last_activity'] = time();
    }

    private static function enforceTimeout(): void
    {
        $timeout = (int) Config::get('app.session_timeout', 1800);
        $lastActivity = (int) ($_SESSION['_last_activity'] ?? time());

        if (time() - $lastActivity > $timeout) {
            $_SESSION = [];
            session_destroy();
            session_start();
        }

        $_SESSION['_last_activity'] = time();
    }
}


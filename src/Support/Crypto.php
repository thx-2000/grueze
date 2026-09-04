<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Verschlüsselung „at rest" für einzelne, sensible Werte (aktuell die
 * Mailserver-Passwörter). libsodium-`secretbox` (XSalsa20-Poly1305).
 *
 * Schlüssel-Quelle in dieser Reihenfolge:
 *   1. config('security.secret_key')  – bewusst gesetzt (empfohlen)
 *   2. Datei storage/app.key          – wird beim ersten Bedarf erzeugt (0600)
 *   3. keiner  → Klartext (Rückfall, damit nichts bricht)
 *
 * Die Schlüsseldatei liegt außerhalb des DocumentRoot und ist von Deploy
 * (`.rsyncignore`) und Git (`.gitignore`) ausgenommen – jede Instanz hat also
 * ihren eigenen Schlüssel, der nie mitreist und nicht in Backups landet.
 */
final class Crypto
{
    private const PREFIX = 'enc.v1.';

    private static ?string $key = null;
    private static bool $resolved = false;

    public static function isActive(): bool
    {
        return self::key() !== null;
    }

    public static function encrypt(string $plain): string
    {
        $key = self::key();
        if ($key === null || $plain === '') {
            return $plain;
        }

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plain, $nonce, $key);

        return self::PREFIX . base64_encode($nonce . $cipher);
    }

    public static function decrypt(string $value): string
    {
        if (!str_starts_with($value, self::PREFIX)) {
            return $value; // Klartext (noch nicht verschlüsselt) – unverändert.
        }

        $key = self::key();
        $raw = base64_decode(substr($value, strlen(self::PREFIX)), true);
        if ($key === null || $raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return '';
        }

        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);

        return $plain === false ? '' : $plain;
    }

    public static function looksEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    private static function key(): ?string
    {
        if (self::$resolved) {
            return self::$key;
        }
        self::$resolved = true;

        $configured = trim((string) config('security.secret_key', ''));
        if ($configured !== '') {
            self::$key = sodium_crypto_generichash($configured, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);

            return self::$key;
        }

        self::$key = self::keyFromFile();

        return self::$key;
    }

    private static function keyFromFile(): ?string
    {
        if (!function_exists('sodium_crypto_secretbox')) {
            return null;
        }

        $path = dirname(__DIR__, 2) . '/storage/app.key';

        if (is_file($path)) {
            $raw = (string) @file_get_contents($path);
            $decoded = base64_decode(trim($raw), true);

            return ($decoded !== false && strlen($decoded) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) ? $decoded : null;
        }

        // Erstbedarf: Schlüssel erzeugen und möglichst restriktiv ablegen.
        $dir = dirname($path);
        if (!is_dir($dir) || !is_writable($dir)) {
            return null;
        }

        $key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, base64_encode($key), LOCK_EX) === false) {
            return null;
        }
        @chmod($tmp, 0600);
        if (!@rename($tmp, $path)) {
            @unlink($tmp);

            return null;
        }

        return $key;
    }
}

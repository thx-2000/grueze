<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Kleiner Helfer für die fetch()-Endpunkte: JSON ausgeben und den Request
 * beenden.
 */
final class JsonResponse
{
    /**
     * @param array<string,mixed> $payload
     */
    public static function send(array $payload, int $status = 200): never
    {
        // Etwaige Ausgabe (z. B. eine PHP-Warnung bei aktivem display_errors)
        // verwerfen, damit die Antwort reines JSON bleibt und im Browser
        // parsebar ist.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }
}

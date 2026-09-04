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
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }
}

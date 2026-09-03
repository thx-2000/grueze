<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Redirect;

/**
 * Interne, nur für Admins bestimmte Anleitungen (z. B. Cronjob-Einrichtung).
 * Die übrigen Anleitungen liegen als statische Seiten unter `public/hilfe/` und
 * sind bewusst auch ohne Login erreichbar – sie enthalten keine sensiblen Daten.
 */
final class HelpController extends BaseController
{
    private const DIR = __DIR__ . '/../../resources/help/';

    public function cron(): void
    {
        $this->requirePermission('users.manage');
        $this->stream(self::DIR . 'cron-allinkl.html', 'text/html; charset=utf-8');
    }

    public function cronPdf(): void
    {
        $this->requirePermission('users.manage');
        $this->stream(self::DIR . 'cron-allinkl.pdf', 'application/pdf', 'cron-allinkl.pdf');
    }

    private function stream(string $path, string $contentType, ?string $downloadName = null): void
    {
        $real = realpath($path);
        if ($real === false || !is_file($real) || !str_starts_with($real, realpath(self::DIR) ?: self::DIR)) {
            flash('error', 'Anleitung nicht gefunden.');
            Redirect::to('/verwaltung');
        }

        header('Content-Type: ' . $contentType);
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: private, max-age=0');
        if ($downloadName !== null) {
            header('Content-Disposition: inline; filename="' . $downloadName . '"');
        }
        readfile($real);
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Services\ThemeService;

/**
 * „Zum Home-Bildschirm hinzufügen": Web-App-Manifest und ein aus dem aktiven
 * Theme abgeleitetes App-Icon. Der Service Worker selbst liegt als statische
 * Datei unter `public/sw.js` (cacht nur Assets, nie Seiten mit Kontaktdaten).
 *
 * Beide Routen sind ohne Login erreichbar; sie enthalten keine Daten.
 */
final class PwaController
{
    public function manifest(): void
    {
        $branding = app_branding();
        $name = trim((string) ($branding['branding_app_name'] ?? 'Adress-Zentrale'));
        $short = trim((string) ($branding['branding_short_name'] ?? '')) ?: mb_substr($name, 0, 12);
        [$primary, $background] = $this->colors();
        $scope = url('/') . '/';

        $manifest = [
            'name' => $name,
            'short_name' => $short,
            'description' => trim((string) ($branding['branding_login_intro'] ?? '')) ?: $name,
            'lang' => 'de',
            'start_url' => $scope . '?source=pwa',
            'scope' => $scope,
            'display' => 'standalone',
            'orientation' => 'portrait-primary',
            'background_color' => $background,
            'theme_color' => $primary,
            'icons' => [
                [
                    'src' => url('/app-icon.svg'),
                    'sizes' => 'any',
                    'type' => 'image/svg+xml',
                    'purpose' => 'any',
                ],
                [
                    'src' => url('/app-icon.svg') . '?maskable=1',
                    'sizes' => 'any',
                    'type' => 'image/svg+xml',
                    'purpose' => 'maskable',
                ],
            ],
        ];

        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public function icon(): void
    {
        $maskable = ($_GET['maskable'] ?? '') === '1';
        [$primary] = $this->colors();
        $ink = readable_ink($primary);

        $branding = app_branding();
        $short = trim((string) ($branding['branding_short_name'] ?? ''));
        $glyph = htmlspecialchars(
            mb_strtoupper($short !== '' ? mb_substr($short, 0, 1) : 'A'),
            ENT_QUOTES | ENT_XML1,
            'UTF-8'
        );

        // Maskable braucht ~10% „safe zone" rundum; sonst randlos mit Radius.
        $radius = $maskable ? 0 : 96;
        $fontSize = $maskable ? 240 : 300;

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">'
            . '<rect width="512" height="512" rx="' . $radius . '" fill="' . $primary . '"/>'
            . '<text x="256" y="256" text-anchor="middle" dominant-baseline="central" fill="' . $ink . '"'
            . ' font-family="-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif"'
            . ' font-size="' . $fontSize . '" font-weight="700">' . $glyph . '</text>'
            . '</svg>';

        header('Content-Type: image/svg+xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo $svg;
    }

    /** @return array{0:string,1:string} [primary, background] */
    private function colors(): array
    {
        $primary = '#2e6b41';
        $background = '#ffffff';
        try {
            $tokens = Container::get(ThemeService::class)->activeTokens();
            $primary = (string) ($tokens['color_primary'] ?? $primary);
            $background = (string) ($tokens['color_bg'] ?? $tokens['color_background'] ?? $background);
        } catch (\Throwable) {
            // Fallback behalten.
        }

        return [$this->safeColor($primary, '#2e6b41'), $this->safeColor($background, '#ffffff')];
    }

    private function safeColor(string $value, string $fallback): string
    {
        return preg_match('/^#[0-9a-fA-F]{3,8}$/', trim($value)) ? trim($value) : $fallback;
    }
}

<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Repositories\SettingRepository;

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = rtrim((string) config('app.base_url', ''), '/');
    $path = '/' . ltrim($path, '/');

    return $base . ($path === '/' ? '' : $path);
}

function asset_url(string $path): string
{
    $normalizedPath = '/' . ltrim($path, '/');
    $absolutePath = dirname(__DIR__, 2) . '/public' . $normalizedPath;
    $version = is_file($absolutePath) ? (string) filemtime($absolutePath) : '1';

    return url($normalizedPath . '?v=' . rawurlencode($version));
}

function config(string $key, mixed $default = null): mixed
{
    return App\Core\Config::get($key, $default);
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;

        return null;
    }

    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);

    return $value;
}

function errors(): array
{
    $errors = $_SESSION['_errors'] ?? [];
    unset($_SESSION['_errors']);

    return $errors;
}

function auth(): Auth
{
    return App\Core\Container::get(Auth::class);
}

function can(string $permission): bool
{
    return auth()->can($permission);
}

function can_view_contact_field(string $field): bool
{
    return auth()->canViewContactField($field);
}

function app_branding(): array
{
    static $branding = null;

    if ($branding !== null) {
        return $branding;
    }

    try {
        $branding = App\Core\Container::get(SettingRepository::class)->branding();
    } catch (Throwable) {
        // Notfall-Werte, falls die Datenbank nicht erreichbar ist. Gleiche
        // Standardwerte und config('branding.*')-Overlay wie in
        // SettingRepository::brandingDefaults().
        $branding = [
            'branding_app_name' => branding_default('app_name', 'Adress-Zentrale'),
            'branding_short_name' => branding_default('short_name', 'Adress-Zentrale'),
            'branding_system_label' => branding_default('system_label', ''),
            'branding_version' => '0.1.0',
            'branding_public_site_label' => branding_default('public_site_label', ''),
            'branding_public_site_url' => branding_default('public_site_url', ''),
            'branding_login_headline' => branding_default('login_headline', 'Interner Bereich'),
            'branding_login_intro' => branding_default('login_intro', 'Hier pflegt ihr Kontakte, Mailings und interne Organisationsdaten an einem Ort.'),
            'branding_login_public_hint' => branding_default('login_public_hint', 'Infos zur Gruppe und die öffentliche Startseite findet ihr hier.'),
            'branding_sidebar_copy' => branding_default('sidebar_copy', ''),
            'branding_support_email' => branding_default('support_email', ''),
            'branding_logo_path' => '',
        ];
    }

    return $branding;
}

/**
 * Standardwert für ein Branding-Feld: bevorzugt config('branding.<key>'),
 * sonst den mitgegebenen (neutralen) Fallback. app_settings hat weiterhin
 * Vorrang vor beidem (siehe SettingRepository::branding()).
 */
function branding_default(string $key, string $fallback): string
{
    $value = config('branding.' . $key);

    return is_string($value) && trim($value) !== '' ? $value : $fallback;
}

function branding_value(string $key, mixed $default = null): mixed
{
    return app_branding()[$key] ?? $default;
}

function branding_theme_style(): string
{
    try {
        $variables = App\Core\Container::get(App\Services\ThemeService::class)->cssVariables();
    } catch (Throwable) {
        return '';
    }

    $declarations = [];
    foreach ($variables as $property => $value) {
        $declarations[] = sprintf('%s: %s;', $property, $value);
    }

    return $declarations === [] ? '' : ':root {' . implode(' ', $declarations) . '}';
}

/**
 * Zerlegt einen CSS-Farbwert (#rgb, #rrggbb, rgb(), rgba()) in [r, g, b].
 * Teiltransparente Werte werden über Weiß zusammengerechnet, da alle Flächen
 * auf hellem Grund liegen. Gibt null zurück, wenn nichts erkannt wird.
 *
 * @return array{0:int,1:int,2:int}|null
 */
function css_color_to_rgb(string $value): ?array
{
    $value = trim($value);

    if (preg_match('/^#([0-9a-f]{3})$/i', $value, $m)) {
        return [
            (int) hexdec($m[1][0] . $m[1][0]),
            (int) hexdec($m[1][1] . $m[1][1]),
            (int) hexdec($m[1][2] . $m[1][2]),
        ];
    }

    if (preg_match('/^#([0-9a-f]{6})$/i', $value, $m)) {
        return [
            (int) hexdec(substr($m[1], 0, 2)),
            (int) hexdec(substr($m[1], 2, 2)),
            (int) hexdec(substr($m[1], 4, 2)),
        ];
    }

    if (preg_match('/^rgba?\(\s*([0-9.]+)[\s,]+([0-9.]+)[\s,]+([0-9.]+)(?:[\s,\/]+([0-9.]+%?))?\s*\)$/i', $value, $m)) {
        $r = (float) $m[1];
        $g = (float) $m[2];
        $b = (float) $m[3];
        $a = 1.0;
        if (isset($m[4]) && $m[4] !== '') {
            $a = str_ends_with($m[4], '%') ? ((float) rtrim($m[4], '%') / 100) : (float) $m[4];
        }
        $a = max(0.0, min(1.0, $a));

        return [
            (int) round($r * $a + 255 * (1 - $a)),
            (int) round($g * $a + 255 * (1 - $a)),
            (int) round($b * $a + 255 * (1 - $a)),
        ];
    }

    return null;
}

/**
 * Wählt zwischen dunkler und heller Schrift, je nachdem was auf der
 * angegebenen Hintergrundfarbe den höheren WCAG-Kontrast liefert.
 */
function readable_ink(string $background, string $dark = '#181a15', string $light = '#ffffff'): string
{
    $rgb = css_color_to_rgb($background);
    if ($rgb === null) {
        return $dark;
    }

    $channel = static function (int $value): float {
        $c = $value / 255;

        return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    };

    $luminance = 0.2126 * $channel($rgb[0]) + 0.7152 * $channel($rgb[1]) + 0.0722 * $channel($rgb[2]);

    $contrastWithLight = 1.05 / ($luminance + 0.05);
    $contrastWithDark = ($luminance + 0.05) / 0.05;

    return $contrastWithDark >= $contrastWithLight ? $dark : $light;
}

/**
 * Favicon als SVG-Data-URI: abgerundete Kachel in der Akzentfarbe des
 * aktiven Themes, Initiale in automatisch kontrastierender Schrift.
 */
function theme_favicon(): string
{
    $accent = '#d8ef54';
    $shortName = 'A';
    try {
        $tokens = App\Core\Container::get(App\Services\ThemeService::class)->activeTokens();
        $accent = (string) ($tokens['color_accent'] ?? $accent);
    } catch (Throwable) {
        // Fallback-Werte behalten.
    }

    $branding = app_branding();
    $name = trim((string) ($branding['branding_short_name'] ?? ''));
    if ($name !== '') {
        $shortName = mb_substr($name, 0, 1);
    }

    $glyph = htmlspecialchars(mb_strtoupper($shortName), ENT_QUOTES | ENT_XML1, 'UTF-8');
    $ink = readable_ink($accent);

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">'
        . '<rect width="32" height="32" rx="7" fill="' . $accent . '"/>'
        . '<text x="16" y="16" text-anchor="middle" dominant-baseline="central" fill="' . $ink . '"'
        . ' font-family="-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif"'
        . ' font-size="19" font-weight="700">' . $glyph . '</text>'
        . '</svg>';

    return 'data:image/svg+xml,' . rawurlencode($svg);
}

function system_version(): string
{
    return '0.12.0';
}

function system_label(): string
{
    // Technische Kennzeichnung vor der Versionsnummer im Footer. Leer = nur
    // "v.x.y.z". Pflegbar über Verwaltung → Branding oder config('branding.system_label').
    return trim((string) branding_value('branding_system_label', ''));
}

function format_date(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($value))->format('d.m.Y');
    } catch (Throwable) {
        return (string) $value;
    }
}

function format_datetime(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($value))->format('d.m.Y H:i');
    } catch (Throwable) {
        return (string) $value;
    }
}

function contact_value_label(int $count, string $none, string $one, string $many): string
{
    return match (true) {
        $count <= 0 => $none,
        $count === 1 => $one,
        default => $many,
    };
}

function contact_address_line(array $contact): string
{
    $parts = [];
    $street = trim((string) ($contact['strasse'] ?? ''));
    $cityLine = trim(trim((string) ($contact['plz'] ?? '')) . ' ' . trim((string) ($contact['ort'] ?? '')));

    if ($street !== '') {
        $parts[] = $street;
    }

    if ($cityLine !== '') {
        $parts[] = $cityLine;
    }

    return $parts === [] ? '–' : implode(', ', $parts);
}

function contact_country_label(array $contact): string
{
    $country = trim((string) ($contact['land'] ?? ''));

    return $country !== '' ? $country : '–';
}

function tag_style(string $seed): string
{
    $palette = [
        ['bg' => '#e5ff3a', 'text' => '#181a15'],
        ['bg' => '#ffb300', 'text' => '#18140a'],
        ['bg' => '#ff8c1a', 'text' => '#22170b'],
        ['bg' => '#d9dde1', 'text' => '#161816'],
        ['bg' => '#b9c0b4', 'text' => '#151710'],
        ['bg' => '#ffc84a', 'text' => '#1a1407'],
        ['bg' => '#c6ff56', 'text' => '#171a10'],
        ['bg' => '#aab1bb', 'text' => '#111315'],
    ];

    $index = abs(crc32($seed)) % count($palette);
    $colors = $palette[$index];

    return sprintf(
        'background:%s;color:%s;border-color:%s;',
        $colors['bg'],
        $colors['text'],
        $colors['bg']
    );
}

function icon(string $name): string
{
    $icons = [
        'contacts' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7.5a3.5 3.5 0 1 1 7 0a3.5 3.5 0 0 1-7 0Zm-3 11a5.5 5.5 0 0 1 11 0v.5H4v-.5Zm12-8.75a2.75 2.75 0 1 1 5.5 0A2.75 2.75 0 0 1 16 9.75Zm-.1 8.25a4.9 4.9 0 0 1 3.56-4.7A4.5 4.5 0 0 1 22 17.5v1h-6.1v-.5Z"/></svg>',
        'user' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 1 0-8a4 4 0 0 1 0 8Zm-7 8a7 7 0 1 1 14 0v1H5v-1Z"/></svg>',
        'history' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5a7 7 0 1 1-6.32 4H3V7h2v2.1A9 9 0 1 0 12 3v2Zm-.75 3h1.5v4.25l3 1.8l-.75 1.23l-3.75-2.28V8Z"/></svg>',
        'mail' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6.5A2.5 2.5 0 0 1 5.5 4h13A2.5 2.5 0 0 1 21 6.5v11a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 17.5v-11Zm2 .1v.2l6.74 5.05a.5.5 0 0 0 .52 0L19 6.8v-.2a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5Zm14 2.7l-5.54 4.16a2.5 2.5 0 0 1-2.92 0L5 9.3v8.2c0 .28.22.5.5.5h13a.5.5 0 0 0 .5-.5V9.3Z"/></svg>',
        'sparkles' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3l1.6 4.4L18 9l-4.4 1.6L12 15l-1.6-4.4L6 9l4.4-1.6L12 3Zm6.5 9l.8 2.2l2.2.8l-2.2.8l-.8 2.2l-.8-2.2l-2.2-.8l2.2-.8l.8-2.2ZM5.5 14l1 2.75L9.25 18l-2.75 1.25L5.5 22l-1.25-2.75L1.5 18l2.75-1.25L5.5 14Z"/></svg>',
        'mail-open' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3l9 5v10.5A2.5 2.5 0 0 1 18.5 21h-13A2.5 2.5 0 0 1 3 18.5V8l9-5Zm0 2.3L6.3 8.5L12 12l5.7-3.5L12 5.3ZM5 10.3v8.2c0 .28.22.5.5.5h13a.5.5 0 0 0 .5-.5v-8.2l-6.48 3.98a1 1 0 0 1-1.04 0L5 10.3Z"/></svg>',
        'message-send' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H9.8L5 20v-4H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm2 4v2h7V8H7Zm0 4v2h5v-2H7Zm10.2 7.2l-1.4-1.4l1.8-1.8H14v-2h3.6l-1.8-1.8l1.4-1.4L21.4 14l-4.2 4.2Z"/></svg>',
        'login' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 4h7a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-7v-2h7V6h-7V4Zm-1.3 4.3L12.4 12l-3.7 3.7l-1.4-1.4l1.3-1.3H3v-2h5.6L7.3 9.7l1.4-1.4Z"/></svg>',
        'plus' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 5h2v6h6v2h-6v6h-2v-6H5v-2h6V5Z"/></svg>',
        'check-double' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m1.8 12.7l1.4-1.4l3 3L12.8 7.7l1.4 1.4l-8 8l-4.4-4.4Zm7 0l1.4-1.4l3 3L19.8 7.7l1.4 1.4l-8 8l-4.4-4.4Z"/></svg>',
        'reset' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5a7 7 0 1 1-6.32 4H3V7h2v2.1A9 9 0 1 0 12 3v2Z"/></svg>',
        'copy' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 7a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-8a2 2 0 0 1-2-2V7Zm-4 4h2v6a2 2 0 0 0 2 2h6v2H8a2 2 0 0 1-2-2v-8Z"/></svg>',
        'upload' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l4 4h-3v7h-2V7H8l4-4Zm-7 12h2v4h10v-4h2v4.5A1.5 1.5 0 0 1 17.5 21h-11A1.5 1.5 0 0 1 5 19.5V15Z"/></svg>',
        'edit' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15.1 5.5l3.4 3.4l-9.9 9.9H5.2v-3.4l9.9-9.9Zm1.4-1.4l1.1-1.1a2 2 0 0 1 2.8 2.8l-1.1 1.1l-2.8-2.8Z"/></svg>',
        'sliders' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 6a2 2 0 1 1 4 0a2 2 0 0 1-4 0Zm0 12a2 2 0 1 1 4 0a2 2 0 0 1-4 0ZM15 12a2 2 0 1 1 4 0a2 2 0 0 1-4 0ZM9 6h10v2H9V6ZM5 12h10v2H5v-2Zm4 6h10v2H9v-2Z"/></svg>',
        'search' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.5 4a6.5 6.5 0 1 1 0 13a6.5 6.5 0 0 1 0-13Zm0 2a4.5 4.5 0 1 0 0 9a4.5 4.5 0 0 0 0-9Zm8.9 10.5l1.4 1.4l-3.3 3.3l-1.4-1.4l3.3-3.3Z"/></svg>',
        'mail-off' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.27 4.68l16.05 16.05l1.41-1.41l-1.89-1.89A2.48 2.48 0 0 1 18.5 20h-13A2.5 2.5 0 0 1 3 17.5v-11c0-.5.15-.97.41-1.35L1.86 3.6l1.41-1.41ZM5 8.18v9.32c0 .28.22.5.5.5h11.09l-2.97-2.97l-.4.3a2 2 0 0 1-2.44 0L5 11.08Zm14-1.68a.5.5 0 0 0-.5-.5H7.91l8.25 8.25L19 12.1V6.5Zm2 0v6.56l-2-2V8.3l-2.79 2.09l-1.43-1.43l4.22-3.16A2.48 2.48 0 0 1 21 6.5Z"/></svg>',
        'location' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12Zm0-9a3 3 0 1 1 0-6a3 3 0 0 1 0 6Z"/></svg>',
        'globe' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 1 0 20a10 10 0 0 1 0-20Zm6.9 9h-3.1a15 15 0 0 0-1.4-5.1A8.03 8.03 0 0 1 18.9 11ZM12 4.1c-.8 1-1.9 3.3-2.2 6h4.4c-.3-2.7-1.4-5-2.2-6ZM9.6 5.9A15 15 0 0 0 8.2 11H5.1a8.03 8.03 0 0 1 4.5-5.1ZM5.1 13h3.1a15 15 0 0 0 1.4 5.1A8.03 8.03 0 0 1 5.1 13Zm6.9 6c.8-1 1.9-3.3 2.2-6H9.8c.3 2.7 1.4 5 2.2 6Zm2.4-.9a15 15 0 0 0 1.4-5.1h3.1a8.03 8.03 0 0 1-4.5 5.1Z"/></svg>',
        'cake' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4.5c.7-.8 1-1.5 1-2.2c0-.8-.4-1.5-1-2.3c-.6.8-1 1.5-1 2.3c0 .7.3 1.4 1 2.2ZM6 10h12v10a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V10Zm13-2H5V6h4l1 1h4l1-1h4v2Z"/></svg>',
        'trash' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3h6l1 2h4v2H4V5h4l1-2Zm1 7h2v8h-2v-8Zm4 0h2v8h-2v-8ZM7 10h2v8H7v-8Z"/></svg>',
        'lock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10V8a5 5 0 0 1 10 0v2h1a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h1Zm2 0h6V8a3 3 0 1 0-6 0v2Zm3 4a2 2 0 0 1 1 3.73V19h-2v-1.27A2 2 0 0 1 12 14Z"/></svg>',
        'unlock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17 10h1a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h9V8a3 3 0 0 0-5.8-1H7.13A5 5 0 0 1 17 8v2Zm-5 4a2 2 0 0 1 1 3.73V19h-2v-1.27A2 2 0 0 1 12 14Z"/></svg>',
        'key' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3a7 7 0 1 1-5.92 10.73L3 18.81V21h2.19l1.5-1.5h2.12l1.5-1.5h2.12l1.52-1.52A6.97 6.97 0 0 1 14 3Zm0 2a5 5 0 1 0 0 10a5 5 0 0 0 0-10Zm1 3a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3Z"/></svg>',
        'passkey' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a6 6 0 0 1 6 6c0 2.3-1.3 4.3-3.3 5.3l-.7.3V16h-2v2h-2v2H8v-2.6l3.1-3.1A6 6 0 1 1 12 2Zm0 2a4 4 0 1 0 0 8a4 4 0 0 0 0-8Zm0 2a2 2 0 1 1 0 4a2 2 0 0 1 0-4Z"/></svg>',
        'more' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 10.5a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3Zm5.5 0a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3Zm5.5 0a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3Z"/></svg>',
        'phone-off' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.72 3.31L3.31 4.72l3.04 3.04v1.02c0 5.02 4.07 9.09 9.09 9.09h1.02l3.82 3.82l1.41-1.41L4.72 3.31Zm3.63 6.46l7.56 7.56h-.47a7.1 7.1 0 0 1-7.09-7.09v-.47Zm8.24 1.17l-1.55-1.55c.45-.25.95-.39 1.49-.39h.91l2.07-2.07A1 1 0 0 0 18.8 5h-2.27a2 2 0 0 0-1.82 1.17l-.62 1.39l-1.55-1.55l.35-.77A4 4 0 0 1 16.53 3H18.8a3 3 0 0 1 2.71 4.28l-.76 1.52a3 3 0 0 1-2.69 1.67h-1.47Z"/></svg>',
        'eye' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c5 0 9.27 3.11 11 7.5C21.27 16.89 17 20 12 20S2.73 16.89 1 12.5C2.73 8.11 7 5 12 5Zm0 2C8.24 7 4.83 9.2 3.18 12.5C4.83 15.8 8.24 18 12 18s7.17-2.2 8.82-5.5C18.17 9.2 14.76 7 12 7Zm0 1.5a4 4 0 1 1 0 8a4 4 0 0 1 0-8Zm0 2a2 2 0 1 0 0 4a2 2 0 0 0 0-4Z"/></svg>',
        'archive' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5.5A1.5 1.5 0 0 1 4.5 4h15A1.5 1.5 0 0 1 21 5.5v2A1.5 1.5 0 0 1 19.5 9H19v9a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V9h-.5A1.5 1.5 0 0 1 3 7.5v-2ZM5 6v1.5h14V6H5Zm2 3v9h10V9H7Zm3 2h4v2h-4v-2Z"/></svg>',
        'home' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.2l8 6.4V20a1 1 0 0 1-1 1h-4.5v-6h-5v6H5a1 1 0 0 1-1-1V9.6l8-6.4Z"/></svg>',
        'menu' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v2H4V6Zm0 5h16v2H4v-2Zm0 5h16v2H4v-2Z"/></svg>',
        'close' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.4 5L5 6.4L10.6 12L5 17.6L6.4 19L12 13.4L17.6 19L19 17.6L13.4 12L19 6.4L17.6 5L12 10.6L6.4 5Z"/></svg>',
        'eye-off' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.28 2.22L2.22 3.28l3.2 3.2C3.5 7.9 1.98 9.98 1 12.5C2.73 16.89 7 20 12 20c2 0 3.88-.5 5.54-1.38l3.18 3.18l1.06-1.06L3.28 2.22ZM12 18c-3.76 0-7.17-2.2-8.82-5.5c.83-1.66 2.06-3.05 3.54-4.02l2.02 2.02A4 4 0 0 0 14.5 15.9l1.66 1.66A9.6 9.6 0 0 1 12 18Zm0-11c3.76 0 7.17 2.2 8.82 5.5a11.4 11.4 0 0 1-2.2 2.94l1.42 1.42A13.3 13.3 0 0 0 23 12.5C21.27 8.11 17 5 12 5c-1.2 0-2.36.18-3.45.5l1.6 1.6C10.79 7.04 11.38 7 12 7Z"/></svg>',
    ];

    $svg = $icons[$name] ?? '';

    return $svg === '' ? '' : '<span class="icon" aria-hidden="true">' . $svg . '</span>';
}

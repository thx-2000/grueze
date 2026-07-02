<?php

declare(strict_types=1);

use App\Core\Auth;

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
        'mail-off' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.7 19.3L4.7 3.3L3.3 4.7l2.21 2.21A2.48 2.48 0 0 0 3 9v8.5A2.5 2.5 0 0 0 5.5 20h12.67l2.53 2.53l1.4-1.4ZM5 9.2l5.78 4.34a2 2 0 0 0 2.44 0l.86-.65L16.91 15H5V9.2Zm14-3.7a2 2 0 0 1 2 2V9l-4.13 3.1l-1.43-1.43L19 8V7.5H8.5L6.64 5.64A2.53 2.53 0 0 1 7.5 5h11.5Z"/></svg>',
        'location' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12Zm0-9a3 3 0 1 1 0-6a3 3 0 0 1 0 6Z"/></svg>',
        'globe' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 1 0 20a10 10 0 0 1 0-20Zm6.9 9h-3.1a15 15 0 0 0-1.4-5.1A8.03 8.03 0 0 1 18.9 11ZM12 4.1c-.8 1-1.9 3.3-2.2 6h4.4c-.3-2.7-1.4-5-2.2-6ZM9.6 5.9A15 15 0 0 0 8.2 11H5.1a8.03 8.03 0 0 1 4.5-5.1ZM5.1 13h3.1a15 15 0 0 0 1.4 5.1A8.03 8.03 0 0 1 5.1 13Zm6.9 6c.8-1 1.9-3.3 2.2-6H9.8c.3 2.7 1.4 5 2.2 6Zm2.4-.9a15 15 0 0 0 1.4-5.1h3.1a8.03 8.03 0 0 1-4.5 5.1Z"/></svg>',
        'cake' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4.5c.7-.8 1-1.5 1-2.2c0-.8-.4-1.5-1-2.3c-.6.8-1 1.5-1 2.3c0 .7.3 1.4 1 2.2ZM6 10h12v10a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V10Zm13-2H5V6h4l1 1h4l1-1h4v2Z"/></svg>',
        'trash' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3h6l1 2h4v2H4V5h4l1-2Zm1 7h2v8h-2v-8Zm4 0h2v8h-2v-8ZM7 10h2v8H7v-8Z"/></svg>',
    ];

    $svg = $icons[$name] ?? '';

    return $svg === '' ? '' : '<span class="icon" aria-hidden="true">' . $svg . '</span>';
}

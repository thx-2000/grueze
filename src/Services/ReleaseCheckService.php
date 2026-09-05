<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SettingRepository;

/**
 * Prüft dezent gegen die GitHub-API, ob eine neuere GRUEZE-Version
 * veröffentlicht wurde, und merkt sich das Ergebnis in `app_settings`.
 *
 * Grundsätze:
 *  - GitHub schonen: gecacht (Standard 12 h → höchstens ~2 Abrufe/Tag).
 *  - Nie den Seitenaufbau stören: kurzer Timeout, alle Fehler geschluckt,
 *    bei einem Fehlschlag greift der zuletzt bekannte Stand.
 *  - `status()` liest nur den Cache (schnell, für den Hinweisstreifen),
 *    `refresh()` holt bei abgelaufenem Cache neu (nur auf der
 *    Aktualisieren-Seite und im Verwaltungs-Hub aufgerufen).
 *  - Abschaltbar über `config('app.release_check')` – dann kein ausgehender
 *    Verbindungsaufbau.
 */
final class ReleaseCheckService
{
    private const CACHE_KEY = 'release_check';
    private const API_URL = 'https://api.github.com/repos/thx-2000/grueze/releases/latest';
    private const RELEASES_URL = 'https://github.com/thx-2000/grueze/releases';

    public function __construct(private SettingRepository $settings)
    {
    }

    public function enabled(): bool
    {
        return (bool) config('app.release_check', true);
    }

    private function ttlSeconds(): int
    {
        return max(1, (int) config('app.release_check_ttl_hours', 12)) * 3600;
    }

    /**
     * Zuletzt bekannter Stand – reiner Cache-Zugriff, kein Netzwerk.
     *
     * @return array{
     *   enabled: bool, available: bool, current: string, latest: ?string,
     *   url: string, published_at: ?string, checked_at: ?string, stale: bool
     * }
     */
    public function status(): array
    {
        $current = system_version();
        $base = [
            'enabled' => $this->enabled(),
            'available' => false,
            'current' => $current,
            'latest' => null,
            'url' => self::RELEASES_URL,
            'published_at' => null,
            'checked_at' => null,
            'stale' => true,
        ];

        if (!$base['enabled']) {
            return $base;
        }

        $cache = $this->readCache();
        if ($cache === null) {
            return $base;
        }

        $latest = (string) $cache['tag'];

        return array_merge($base, [
            'available' => $latest !== '' && version_compare($latest, $current, '>'),
            'latest' => $latest !== '' ? $latest : null,
            'url' => (string) ($cache['url'] ?? self::RELEASES_URL),
            'published_at' => ($cache['published_at'] ?? '') !== '' ? (string) $cache['published_at'] : null,
            'checked_at' => date('c', (int) $cache['ts']),
            'stale' => (time() - (int) $cache['ts']) >= $this->ttlSeconds(),
        ]);
    }

    /** Cache erneuern, falls abgelaufen. Fehler bleiben ohne Folgen. */
    public function refresh(): void
    {
        if (!$this->enabled()) {
            return;
        }

        $cache = $this->readCache();
        if ($cache !== null && (time() - (int) $cache['ts']) < $this->ttlSeconds()) {
            return;
        }

        $fetched = $this->fetch();
        if ($fetched !== null) {
            $this->writeCache($fetched);
        }
    }

    /** @return array{tag: string, url: string, published_at: string, ts: int}|null */
    private function fetch(): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: GRUEZE-Update-Check\r\nAccept: application/vnd.github+json\r\n",
                'timeout' => 3,
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        $raw = @file_get_contents(self::API_URL, false, $context);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }

        $tag = ltrim(trim((string) ($data['tag_name'] ?? '')), 'vV');
        if ($tag === '' || !preg_match('/^\d+(\.\d+){1,3}$/', $tag)) {
            return null;
        }

        return [
            'tag' => $tag,
            'url' => (string) ($data['html_url'] ?? self::RELEASES_URL),
            'published_at' => (string) ($data['published_at'] ?? ''),
            'ts' => time(),
        ];
    }

    /** @return array{tag: string, url?: string, published_at?: string, ts: int}|null */
    private function readCache(): ?array
    {
        $raw = $this->settings->get(self::CACHE_KEY);
        if ($raw === null || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);

        return is_array($data) && isset($data['tag'], $data['ts']) ? $data : null;
    }

    private function writeCache(array $data): void
    {
        try {
            $this->settings->set(self::CACHE_KEY, (string) json_encode($data, JSON_UNESCAPED_SLASHES));
        } catch (\Throwable) {
            // Cache ist Kür – ein Schreibfehler darf nichts auslösen.
        }
    }
}

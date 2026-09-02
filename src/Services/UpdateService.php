<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SettingRepository;

/**
 * Bringt eine laufende Instanz nach einem Datei-Upload sauber auf den neuen
 * Stand: offene Datenbank-Migrationen anwenden, Version vermerken, optional
 * vorher ein Sicherungs-ZIP ablegen.
 *
 * Grundsatz (siehe ARCHITECTURE.md): Bestandsdaten dürfen dabei nie kaputt
 * gehen. Migrationen sind additiv/idempotent, das Vor-Update-Backup ist die
 * zusätzliche Rückfalllinie.
 */
final class UpdateService
{
    private const VERSION_KEY = 'app_version';
    private const UPDATED_AT_KEY = 'app_version_updated_at';
    private const KEEP_BACKUPS = 3;

    private string $lockFile;
    private string $backupDir;

    public function __construct(
        private SettingRepository $settings,
        private MigrationService $migrations,
        private BackupService $backups,
    ) {
        $this->lockFile = dirname(__DIR__, 2) . '/storage/tmp/update.lock';
        $this->backupDir = dirname(__DIR__, 2) . '/storage/backups';
    }

    public function codeVersion(): string
    {
        return system_version();
    }

    /** Zuletzt bestätigte Version (Erstinstallation oder letztes Update). */
    public function installedVersion(): ?string
    {
        $value = trim((string) ($this->settings->get(self::VERSION_KEY) ?? ''));

        return $value !== '' ? $value : null;
    }

    /**
     * Wenn keine Migrationen offen sind, gibt es nach einem Upload nichts
     * anzuwenden – dann wird die Version stillschweigend nachgezogen (auch bei
     * einer reinen Code-Aktualisierung ohne DB-Änderung). Solange Migrationen
     * offen sind, bleibt die Version stehen, bis der Update-Lauf sie setzt.
     */
    public function syncVersionIfClean(): void
    {
        if ($this->migrations->pending() !== []) {
            return;
        }

        if ($this->installedVersion() !== $this->codeVersion()) {
            $this->markInstalled();
        }
    }

    public function markInstalled(): void
    {
        $this->settings->set(self::VERSION_KEY, $this->codeVersion());
        $this->settings->set(self::UPDATED_AT_KEY, date('c'));
    }

    public function lastUpdatedAt(): ?string
    {
        $value = trim((string) ($this->settings->get(self::UPDATED_AT_KEY) ?? ''));

        return $value !== '' ? $value : null;
    }

    /**
     * „Update aussteht" heißt: es gibt offene Migrationen. Eine reine
     * Code-Aktualisierung ohne DB-Änderung braucht keinen Klick – die Version
     * zieht `syncVersionIfClean()` still nach.
     */
    public function updatePending(): bool
    {
        return $this->migrations->pending() !== [];
    }

    /**
     * @return list<array{name: string, description: string}>
     */
    public function pendingMigrations(): array
    {
        $result = [];
        foreach ($this->migrations->pending() as $name => $path) {
            $result[] = [
                'name' => $name,
                'description' => $this->describe($path),
            ];
        }

        return $result;
    }

    public function locked(): bool
    {
        if (!is_file($this->lockFile)) {
            return false;
        }

        // Verwaiste Sperre (abgebrochener Lauf) nach 10 Minuten ignorieren.
        if (time() - (int) @filemtime($this->lockFile) > 600) {
            @unlink($this->lockFile);

            return false;
        }

        return true;
    }

    /**
     * Wendet alle offenen Migrationen der Reihe nach an.
     *
     * @return array{ok: bool, applied: list<string>, failed: ?string, error: ?string, backup: ?string, from: ?string, to: string}
     */
    public function run(bool $withBackup): array
    {
        $from = $this->installedVersion();
        $to = $this->codeVersion();
        $result = ['ok' => true, 'applied' => [], 'failed' => null, 'error' => null, 'backup' => null, 'from' => $from, 'to' => $to];

        if ($this->locked()) {
            $result['ok'] = false;
            $result['error'] = 'Es läuft bereits ein Update. Bitte kurz warten und die Seite neu laden.';

            return $result;
        }

        $lockHandle = @fopen($this->lockFile, 'w');
        if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
            $result['ok'] = false;
            $result['error'] = 'Update-Sperre konnte nicht gesetzt werden.';

            return $result;
        }
        fwrite($lockHandle, (string) time());

        try {
            if ($withBackup) {
                $result['backup'] = $this->createSafetyBackup();
            }

            foreach (array_keys($this->migrations->pending()) as $name) {
                $outcome = $this->migrations->applyOne($name);
                if ($outcome !== 'OK') {
                    $result['ok'] = false;
                    $result['failed'] = $name;
                    $result['error'] = $outcome;
                    break;
                }
                $result['applied'][] = $name;
            }

            if ($result['ok']) {
                $this->markInstalled();
            }
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            @unlink($this->lockFile);
        }

        return $result;
    }

    /**
     * Passenden Changelog-Ausschnitt (von der installierten bis zur aktuellen
     * Version) als Roh-Markdown. Leerer String, wenn keine Datei vorhanden.
     */
    public function changelogExcerpt(): string
    {
        $file = dirname(__DIR__, 2) . '/CHANGELOG.md';
        if (!is_file($file)) {
            return '';
        }

        $content = (string) file_get_contents($file);
        $from = $this->installedVersion();
        if ($from === null || $from === $this->codeVersion()) {
            return $content;
        }

        // Alles bis zum Abschnitt der bereits installierten Version behalten.
        $marker = '## ' . $from;
        $pos = strpos($content, $marker);

        return $pos === false ? $content : rtrim(substr($content, 0, $pos));
    }

    private function createSafetyBackup(): string
    {
        if (!is_dir($this->backupDir)) {
            @mkdir($this->backupDir, 0775, true);
        }

        $tmpPath = $this->backups->createArchive(true);
        $target = $this->backupDir . '/vor-update-' . date('Y-m-d-His') . '.zip';

        if (!@rename($tmpPath, $target)) {
            @copy($tmpPath, $target);
            @unlink($tmpPath);
        }

        $this->pruneBackups();

        return basename($target);
    }

    private function pruneBackups(): void
    {
        $files = glob($this->backupDir . '/vor-update-*.zip') ?: [];
        if (count($files) <= self::KEEP_BACKUPS) {
            return;
        }

        sort($files);
        foreach (array_slice($files, 0, count($files) - self::KEEP_BACKUPS) as $old) {
            @unlink($old);
        }
    }

    /** Erste Kommentarzeile (-- …) einer Migrationsdatei als Kurzbeschreibung. */
    private function describe(string $path): string
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            return '';
        }

        $parts = [];
        while (($line = fgets($handle)) !== false && count($parts) < 3) {
            $line = trim($line);
            if ($line === '') {
                if ($parts !== []) {
                    break;
                }
                continue;
            }
            if (!str_starts_with($line, '--')) {
                break;
            }
            $parts[] = trim(ltrim($line, '-'));
        }
        fclose($handle);

        return trim(implode(' ', $parts));
    }
}

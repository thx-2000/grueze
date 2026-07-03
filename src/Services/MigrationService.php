<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class MigrationService
{
    private string $migrationsPath;

    public function __construct(private PDO $pdo)
    {
        $this->migrationsPath = dirname(__DIR__, 2) . '/database/migrations';
    }

    public function ensureTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                migration VARCHAR(190) NOT NULL PRIMARY KEY,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function applied(): array
    {
        try {
            $this->ensureTable();
            $stmt = $this->pdo->query('SELECT migration, applied_at FROM schema_migrations ORDER BY migration');

            return $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    public function pending(): array
    {
        $applied = array_column($this->applied(), 'migration');
        $files = $this->allMigrationFiles();
        $pending = [];

        foreach ($files as $name => $path) {
            if (!in_array($name, $applied, true)) {
                $pending[$name] = $path;
            }
        }

        return $pending;
    }

    public function applyOne(string $name): string
    {
        $files = $this->allMigrationFiles();
        if (!isset($files[$name])) {
            return 'Migration nicht gefunden: ' . $name;
        }

        $sql = file_get_contents($files[$name]);
        if ($sql === false || trim($sql) === '') {
            return 'Migration-Datei ist leer oder nicht lesbar: ' . $name;
        }

        try {
            $this->pdo->exec($sql);
            $stmt = $this->pdo->prepare(
                'INSERT IGNORE INTO schema_migrations (migration) VALUES (:migration)'
            );
            $stmt->execute(['migration' => $name]);

            return 'OK';
        } catch (\Throwable $e) {
            return 'Fehler: ' . $e->getMessage();
        }
    }

    public function allMigrationFiles(): array
    {
        $files = glob($this->migrationsPath . '/*.sql') ?: [];
        sort($files);
        $result = [];

        foreach ($files as $path) {
            $name = basename($path, '.sql');
            $result[$name] = $path;
        }

        return $result;
    }
}

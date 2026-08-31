<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Voll-Backup und Wiederherstellung des gesamten Datenbestands
 * (alle Tabellen + hochgeladene Dateien) als ZIP mit JSON.
 *
 * Format 1:
 *   manifest.json   – App-Version, Zeitstempel, Tabellen + Zeilenzahlen
 *   database.json    – { "<tabelle>": [ { spalte: wert, ... }, ... ], ... }
 *   uploads/<datei>  – alle Dateien aus public/assets/uploads/
 */
final class BackupService
{
    private const FORMAT = 1;

    /** Reihenfolge ist für die Wiederherstellung bewusst FK-freundlich gewählt. */
    private const CORE_TABLES = [
        'roles',
        'categories',
        'tags',
        'users',
        'contacts',
        'contact_emails',
        'contact_phones',
        'contact_tags',
        'password_resets',
        'user_passkeys',
        'app_settings',
        'schema_migrations',
    ];

    /** Über ein Häkchen abwählbar. */
    private const LOG_TABLES = [
        'login_attempts',
        'audit_log',
        'mail_log',
    ];

    /** Spalten, die binär sind und im JSON base64-kodiert abgelegt werden. */
    private const BINARY_COLUMNS = [
        'user_passkeys' => ['credential_id'],
    ];

    private string $uploadsDir;
    private string $tmpDir;

    public function __construct(private PDO $pdo)
    {
        $this->uploadsDir = dirname(__DIR__, 2) . '/public/assets/uploads';
        $this->tmpDir = dirname(__DIR__, 2) . '/storage/tmp';
    }

    public function tableRowCounts(bool $includeLogs = true): array
    {
        $counts = [];
        foreach ($this->tables($includeLogs) as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }
            $counts[$table] = (int) $this->pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
        }

        return $counts;
    }

    /**
     * Erzeugt das Backup-ZIP und gibt den Pfad zur temporären Datei zurück.
     * Der Aufrufer ist für den Versand und das Aufräumen zuständig.
     */
    public function createArchive(bool $includeLogs): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Die PHP-Erweiterung "zip" ist nicht verfügbar.');
        }

        $database = [];
        $counts = [];
        foreach ($this->tables($includeLogs) as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }
            $rows = $this->exportTable($table);
            $database[$table] = $rows;
            $counts[$table] = count($rows);
        }

        $uploads = $this->collectUploadFiles();

        $manifest = [
            'format' => self::FORMAT,
            'app' => [
                'name' => (string) config('app.name', 'Adress-Zentrale'),
                'version' => system_version(),
            ],
            'created_at' => date('c'),
            'includes_logs' => $includeLogs,
            'binary_columns' => self::BINARY_COLUMNS,
            'tables' => $counts,
            'uploads' => count($uploads),
        ];

        $databaseJson = json_encode($database, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($databaseJson === false) {
            throw new RuntimeException('Der Datenbestand konnte nicht als JSON kodiert werden: ' . json_last_error_msg());
        }

        $path = $this->tmpDir . '/backup_' . bin2hex(random_bytes(8)) . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Die Backup-Datei konnte nicht angelegt werden.');
        }

        $zip->addFromString('manifest.json', (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('database.json', $databaseJson);
        foreach ($uploads as $absolute => $name) {
            $zip->addFile($absolute, 'uploads/' . $name);
        }
        $zip->close();

        return $path;
    }

    public function suggestedFileName(): string
    {
        $short = preg_replace('/[^a-z0-9]+/i', '-', (string) (app_branding()['branding_short_name'] ?? 'backup'));
        $short = trim((string) $short, '-') ?: 'backup';

        return strtolower($short) . '-backup-' . date('Y-m-d-Hi') . '.zip';
    }

    /**
     * Wiederherstellung aus einem Backup-ZIP.
     *
     * @param string $mode 'replace' = alles ersetzen, 'fill' = nur wenn leer
     * @return array{tables: array<string,int>, uploads: int}
     */
    public function restoreArchive(string $zipPath, string $mode): array
    {
        if (!in_array($mode, ['replace', 'fill'], true)) {
            throw new RuntimeException('Unbekannter Wiederherstellungs-Modus.');
        }
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Die PHP-Erweiterung "zip" ist nicht verfügbar.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Das Backup konnte nicht geöffnet werden. Ist es eine gültige ZIP-Datei?');
        }

        $manifestRaw = $zip->getFromName('manifest.json');
        $databaseRaw = $zip->getFromName('database.json');
        if ($manifestRaw === false || $databaseRaw === false) {
            $zip->close();
            throw new RuntimeException('Im Backup fehlen manifest.json oder database.json.');
        }

        $manifest = json_decode($manifestRaw, true);
        $database = json_decode($databaseRaw, true);
        if (!is_array($manifest) || !is_array($database)) {
            $zip->close();
            throw new RuntimeException('manifest.json oder database.json ist beschädigt.');
        }
        if ((int) ($manifest['format'] ?? 0) !== self::FORMAT) {
            $zip->close();
            throw new RuntimeException('Nicht unterstütztes Backup-Format (erwartet: ' . self::FORMAT . ').');
        }

        $binaryColumns = is_array($manifest['binary_columns'] ?? null) ? $manifest['binary_columns'] : self::BINARY_COLUMNS;

        // "fill" ist wie "replace", läuft aber nur auf einer noch leeren Instanz.
        // So bleiben FK-Beziehungen im wiederhergestellten Stand konsistent.
        if ($mode === 'fill' && !$this->isEmptyEnoughForFill()) {
            $zip->close();
            throw new RuntimeException('Das System enthält bereits Kontakte oder mehrere Benutzer. "Nur wenn leer" ist nur für eine frische Instanz gedacht – bitte "Alles ersetzen" verwenden.');
        }

        // Reihenfolge: erst alle Tabellen aus dem Backup in der bekannten
        // Sortierung, dann evtl. zusätzliche Tabellen aus dem Backup hintendran.
        $ordered = array_values(array_filter(
            array_merge(self::CORE_TABLES, self::LOG_TABLES),
            static fn (string $t): bool => array_key_exists($t, $database)
        ));
        foreach (array_keys($database) as $table) {
            if (!in_array($table, $ordered, true)) {
                $ordered[] = $table;
            }
        }

        $written = [];
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ($ordered as $table) {
                if (!$this->tableExists($table)) {
                    continue;
                }
                $rows = is_array($database[$table] ?? null) ? $database[$table] : [];

                $this->pdo->exec('DELETE FROM `' . $table . '`');
                $written[$table] = $this->importTable($table, $rows, (array) ($binaryColumns[$table] ?? []));
                $this->resyncAutoIncrement($table);
            }
        } catch (Throwable $e) {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            $zip->close();
            throw new RuntimeException('Wiederherstellung abgebrochen bei einer Tabelle: ' . $e->getMessage());
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $uploadCount = $this->restoreUploads($zip);
        $zip->close();

        return ['tables' => $written, 'uploads' => $uploadCount];
    }

    // ---------------------------------------------------------------- intern

    private function tables(bool $includeLogs): array
    {
        return $includeLogs
            ? array_merge(self::CORE_TABLES, self::LOG_TABLES)
            : self::CORE_TABLES;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = :name'
        );
        $stmt->execute(['name' => $table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function exportTable(string $table): array
    {
        $binary = self::BINARY_COLUMNS[$table] ?? [];
        $rows = $this->pdo->query('SELECT * FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            foreach ($row as $column => $value) {
                if ($value === null) {
                    continue;
                }
                if (in_array($column, $binary, true)) {
                    $row[$column] = base64_encode((string) $value);
                    continue;
                }
                if (is_string($value) && $value !== '' && preg_match('//u', $value) !== 1) {
                    throw new RuntimeException(
                        "Spalte {$table}.{$column} enthält unerwartete Binärdaten. "
                        . 'Bitte den Entwicklerstand erweitern.'
                    );
                }
            }
        }
        unset($row);

        return $rows;
    }

    private function importTable(string $table, array $rows, array $binary): int
    {
        if ($rows === []) {
            return 0;
        }

        $count = 0;
        $stmt = null;
        $lastColumns = null;

        foreach ($rows as $row) {
            if (!is_array($row) || $row === []) {
                continue;
            }
            $columns = array_keys($row);
            if ($columns !== $lastColumns) {
                $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                $columnList = '`' . implode('`, `', $columns) . '`';
                $stmt = $this->pdo->prepare(
                    'INSERT INTO `' . $table . '` (' . $columnList . ') VALUES (' . $placeholders . ')'
                );
                $lastColumns = $columns;
            }

            $values = [];
            foreach ($row as $column => $value) {
                if ($value !== null && in_array($column, $binary, true)) {
                    $decoded = base64_decode((string) $value, true);
                    $value = $decoded === false ? $value : $decoded;
                }
                $values[] = $value;
            }

            $stmt->execute($values);
            $count++;
        }

        return $count;
    }

    private function resyncAutoIncrement(string $table): void
    {
        try {
            $hasAutoId = $this->pdo->query(
                "SHOW COLUMNS FROM `" . $table . "` WHERE Extra LIKE '%auto_increment%'"
            )->fetchColumn();
            if ($hasAutoId === false) {
                return;
            }
            $max = (int) $this->pdo->query('SELECT COALESCE(MAX(`id`), 0) FROM `' . $table . '`')->fetchColumn();
            $this->pdo->exec('ALTER TABLE `' . $table . '` AUTO_INCREMENT = ' . ($max + 1));
        } catch (Throwable) {
            // Nicht kritisch – nächster Insert korrigiert sich notfalls selbst.
        }
    }

    private function isEmptyEnoughForFill(): bool
    {
        $contacts = (int) $this->pdo->query('SELECT COUNT(*) FROM contacts')->fetchColumn();
        $users = (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

        return $contacts === 0 && $users <= 1;
    }

    /** @return array<string,string> absoluter Pfad => Dateiname */
    private function collectUploadFiles(): array
    {
        if (!is_dir($this->uploadsDir)) {
            return [];
        }

        $files = [];
        foreach (scandir($this->uploadsDir) ?: [] as $name) {
            if ($name === '.' || $name === '..' || str_starts_with($name, '.')) {
                continue;
            }
            $absolute = $this->uploadsDir . '/' . $name;
            if (is_file($absolute)) {
                $files[$absolute] = $name;
            }
        }

        return $files;
    }

    private function restoreUploads(ZipArchive $zip): int
    {
        if (!is_dir($this->uploadsDir) && !mkdir($this->uploadsDir, 0775, true) && !is_dir($this->uploadsDir)) {
            throw new RuntimeException('Der Upload-Ordner konnte nicht angelegt werden.');
        }

        foreach ($this->collectUploadFiles() as $absolute => $_name) {
            @unlink($absolute);
        }

        $restored = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if ($entry === false || !str_starts_with($entry, 'uploads/') || str_ends_with($entry, '/')) {
                continue;
            }
            $name = basename($entry);
            if ($name === '' || str_contains($name, '..')) {
                continue;
            }
            $contents = $zip->getFromIndex($i);
            if ($contents === false) {
                continue;
            }
            if (file_put_contents($this->uploadsDir . '/' . $name, $contents) !== false) {
                $restored++;
            }
        }

        return $restored;
    }
}

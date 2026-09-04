<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ContactRepository;
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

    public function __construct(private PDO $pdo, private ContactRepository $contacts)
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
    public function createArchive(bool $includeLogs, ?string $password = null): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Die PHP-Erweiterung "zip" ist nicht verfügbar.');
        }
        $password = $password !== null && trim($password) !== '' ? $password : null;
        if ($password !== null && !$this->zipEncryptionAvailable()) {
            throw new RuntimeException('Verschlüsselte Backups werden von dieser PHP-Version nicht unterstützt. Bitte ohne Passwort exportieren.');
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

        if ($password !== null) {
            $zip->setPassword($password);
        }

        $entries = ['manifest.json', 'database.json'];
        $zip->addFromString('manifest.json', (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('database.json', $databaseJson);
        foreach ($uploads as $absolute => $name) {
            $zip->addFile($absolute, 'uploads/' . $name);
            $entries[] = 'uploads/' . $name;
        }

        if ($password !== null) {
            foreach ($entries as $entry) {
                $zip->setEncryptionName($entry, ZipArchive::EM_AES_256);
            }
        }

        $zip->close();

        return $path;
    }

    public function zipEncryptionAvailable(): bool
    {
        return class_exists(ZipArchive::class)
            && defined('ZipArchive::EM_AES_256')
            && method_exists(ZipArchive::class, 'setEncryptionName');
    }

    private function firstEntryIsEncrypted(ZipArchive $zip): bool
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (is_array($stat) && (int) ($stat['encryption_method'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
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
     * @param string $mode 'replace' = alles ersetzen, 'fill' = nur wenn leer,
     *                      'merge' = Kontakte ins bestehende System einspielen
     * @return array{tables: array<string,int>, uploads: int, merge?: array<string,int>}
     */
    public function restoreArchive(string $zipPath, string $mode, ?string $password = null): array
    {
        if (!in_array($mode, ['replace', 'fill', 'merge'], true)) {
            throw new RuntimeException('Unbekannter Wiederherstellungs-Modus.');
        }
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Die PHP-Erweiterung "zip" ist nicht verfügbar.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Das Backup konnte nicht geöffnet werden. Ist es eine gültige ZIP-Datei?');
        }
        if ($password !== null && trim($password) !== '') {
            $zip->setPassword($password);
        }

        $manifestRaw = $zip->getFromName('manifest.json');
        $databaseRaw = $zip->getFromName('database.json');
        if ($manifestRaw === false || $databaseRaw === false) {
            $encrypted = $this->firstEntryIsEncrypted($zip);
            $zip->close();
            if ($encrypted) {
                throw new RuntimeException($password !== null && trim($password) !== ''
                    ? 'Das Backup ist verschlüsselt und das Passwort passt nicht.'
                    : 'Das Backup ist verschlüsselt. Bitte das beim Export vergebene Passwort eingeben.');
            }
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

        // "merge": nur Kontakte (+ Mails, Telefone, Tags, Kategorien) ins
        // bestehende System einspielen, nichts löschen. Benutzer, Rollen,
        // Einstellungen und Protokolle bleiben unberührt.
        if ($mode === 'merge') {
            $merge = $this->mergeContacts($database, $zip);
            $zip->close();

            return ['tables' => [], 'uploads' => $merge['restored_photos'], 'merge' => $merge];
        }

        // "fill" ist wie "replace", läuft aber nur auf einer noch leeren Instanz.
        // So bleiben FK-Beziehungen im wiederhergestellten Stand konsistent.
        if ($mode === 'fill' && !$this->isEmptyEnoughForFill()) {
            $zip->close();
            throw new RuntimeException('Das System enthält bereits Kontakte oder mehrere Zugänge. "Nur wenn leer" ist nur für eine frische Instanz gedacht – bitte "Alles ersetzen" verwenden.');
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

    // ------------------------------------------------------------- Zusammenführen

    /**
     * Spielt die Kontakte aus dem Backup ins bestehende System ein, ohne etwas
     * zu löschen. Alles wird über natürliche Schlüssel aufgelöst (Kontaktname,
     * Kategorie-/Tag-Name, Mailadresse, Rufnummer) – die IDs aus dem Backup
     * werden ignoriert, dadurch keine ID-Konflikte.
     *
     * @return array<string,int>
     */
    private function mergeContacts(array $database, ZipArchive $zip): array
    {
        $contacts = is_array($database['contacts'] ?? null) ? $database['contacts'] : [];
        if ($contacts === []) {
            throw new RuntimeException('Das Backup enthält keine Kontakte zum Zusammenführen.');
        }

        $categoryNames = $this->indexByIdColumn($database['categories'] ?? [], 'name');
        $tagNames = $this->indexByIdColumn($database['tags'] ?? [], 'name');
        $emailsByContact = $this->groupByColumn($database['contact_emails'] ?? [], 'contact_id');
        $phonesByContact = $this->groupByColumn($database['contact_phones'] ?? [], 'contact_id');
        $tagLinksByContact = $this->groupByColumn($database['contact_tags'] ?? [], 'contact_id');

        $stats = [
            'new_contacts' => 0, 'updated_contacts' => 0,
            'added_emails' => 0, 'added_phones' => 0, 'added_tags' => 0,
            'new_categories' => 0, 'new_tags' => 0, 'restored_photos' => 0,
        ];

        $actorId = (int) ($this->pdo->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
        if ($actorId === 0) {
            throw new RuntimeException('Zusammenführen braucht mindestens einen Zugang im System.');
        }

        $this->pdo->beginTransaction();
        try {
            foreach ($contacts as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $vorname = trim((string) ($row['vorname'] ?? ''));
                $nachname = trim((string) ($row['nachname'] ?? ''));
                if ($vorname === '' || $nachname === '') {
                    continue;
                }
                $geburtsname = trim((string) ($row['geburtsname'] ?? ''));
                $backupId = (int) ($row['id'] ?? 0);

                $emails = $this->normalizeEmailRows($emailsByContact[$backupId] ?? []);
                $phones = $this->normalizePhoneRows($phonesByContact[$backupId] ?? []);
                $tags = [];
                foreach ($tagLinksByContact[$backupId] ?? [] as $link) {
                    $name = $tagNames[(int) ($link['tag_id'] ?? 0)] ?? null;
                    if ($name !== null && trim($name) !== '') {
                        $tags[] = trim($name);
                    }
                }
                $categoryName = $categoryNames[(int) ($row['category_id'] ?? 0)] ?? null;

                $existing = $this->contacts->findImportMatch($vorname, $nachname, $geburtsname);

                $tagIds = [];
                foreach ($tags as $tagName) {
                    $tagId = $this->resolveTagId($tagName, $stats);
                    if ($tagId > 0) {
                        $tagIds[] = $tagId;
                    }
                }

                if ($existing === null) {
                    $this->contacts->create([
                        'vorname' => $vorname,
                        'nachname' => $nachname,
                        'geburtsname' => $geburtsname,
                        'geschlecht' => (string) ($row['geschlecht'] ?? ''),
                        'category_id' => $categoryName !== null ? $this->resolveCategoryId($categoryName, $stats) : null,
                        'geburtstag' => (string) ($row['geburtstag'] ?? ''),
                        'strasse' => (string) ($row['strasse'] ?? ''),
                        'plz' => (string) ($row['plz'] ?? ''),
                        'ort' => (string) ($row['ort'] ?? ''),
                        'land' => (string) ($row['land'] ?? ''),
                        'notizen' => (string) ($row['notizen'] ?? ''),
                        'photo_path' => $this->restoreMergePhoto($zip, (string) ($row['photo_path'] ?? ''), $stats),
                        'emails' => $emails,
                        'phones' => $phones,
                        'tag_ids' => $tagIds,
                    ], $actorId);
                    $stats['new_contacts']++;
                    continue;
                }

                $contactId = (int) $existing['id'];
                $changed = 0;
                $changed += $addedE = $this->addMissingEmails($contactId, $emails, (array) ($existing['emails'] ?? []));
                $changed += $addedP = $this->addMissingPhones($contactId, $phones, (array) ($existing['phones'] ?? []));
                $stats['added_emails'] += $addedE;
                $stats['added_phones'] += $addedP;

                foreach ($tagIds as $tagId) {
                    if ($this->linkTagIfMissing($contactId, $tagId)) {
                        $stats['added_tags']++;
                        $changed++;
                    }
                }

                $changed += $this->fillEmptyContactFields($contactId, $row, $categoryName, $stats);

                if ($changed > 0) {
                    $stats['updated_contacts']++;
                }
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw new RuntimeException('Zusammenführen abgebrochen: ' . $e->getMessage());
        }

        return $stats;
    }

    /** @return array<int,string> */
    private function indexByIdColumn(mixed $rows, string $column): array
    {
        $map = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row) && isset($row['id'])) {
                $map[(int) $row['id']] = (string) ($row[$column] ?? '');
            }
        }

        return $map;
    }

    /** @return array<int,list<array>> */
    private function groupByColumn(mixed $rows, string $column): array
    {
        $map = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row) && isset($row[$column])) {
                $map[(int) $row[$column]][] = $row;
            }
        }

        return $map;
    }

    private function normalizeEmailRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $email = trim((string) ($row['email'] ?? ''));
            if ($email !== '') {
                $out[] = ['email' => $email, 'label' => trim((string) ($row['label'] ?? ''))];
            }
        }

        return $out;
    }

    private function normalizePhoneRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $phone = trim((string) ($row['phone'] ?? ''));
            if ($phone !== '') {
                $out[] = ['phone' => $phone, 'label' => trim((string) ($row['label'] ?? '')) ?: 'Sonstige'];
            }
        }

        return $out;
    }

    private function resolveCategoryId(string $name, array &$stats): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT id FROM categories WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        $id = (int) $stmt->fetchColumn();
        if ($id > 0) {
            return $id;
        }
        $this->pdo->prepare('INSERT INTO categories (name) VALUES (:name)')->execute(['name' => $name]);
        $stats['new_categories']++;

        return (int) $this->pdo->lastInsertId();
    }

    private function resolveTagId(string $name, array &$stats): int
    {
        $name = trim($name);
        if ($name === '') {
            return 0;
        }
        $stmt = $this->pdo->prepare('SELECT id FROM tags WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        $id = (int) $stmt->fetchColumn();
        if ($id > 0) {
            return $id;
        }
        $this->pdo->prepare('INSERT INTO tags (name) VALUES (:name)')->execute(['name' => $name]);
        $stats['new_tags']++;

        return (int) $this->pdo->lastInsertId();
    }

    private function addMissingEmails(int $contactId, array $incoming, array $existing): int
    {
        $have = [];
        foreach ($existing as $row) {
            $have[mb_strtolower(trim((string) ($row['email'] ?? '')))] = true;
        }
        $stmt = $this->pdo->prepare('INSERT INTO contact_emails (contact_id, email, label) VALUES (:contact_id, :email, :label)');
        $added = 0;
        foreach ($incoming as $row) {
            $key = mb_strtolower($row['email']);
            if ($key === '' || isset($have[$key])) {
                continue;
            }
            $stmt->execute(['contact_id' => $contactId, 'email' => $row['email'], 'label' => $row['label'] ?: null]);
            $have[$key] = true;
            $added++;
        }

        return $added;
    }

    private function addMissingPhones(int $contactId, array $incoming, array $existing): int
    {
        $digits = static fn (string $v): string => preg_replace('/\D+/', '', $v) ?? '';
        $have = [];
        foreach ($existing as $row) {
            $have[$digits((string) ($row['phone'] ?? ''))] = true;
        }
        $stmt = $this->pdo->prepare('INSERT INTO contact_phones (contact_id, phone, label) VALUES (:contact_id, :phone, :label)');
        $added = 0;
        foreach ($incoming as $row) {
            $key = $digits($row['phone']);
            if ($key === '' || isset($have[$key])) {
                continue;
            }
            $stmt->execute(['contact_id' => $contactId, 'phone' => $row['phone'], 'label' => $row['label']]);
            $have[$key] = true;
            $added++;
        }

        return $added;
    }

    private function linkTagIfMissing(int $contactId, int $tagId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM contact_tags WHERE contact_id = :c AND tag_id = :t LIMIT 1');
        $stmt->execute(['c' => $contactId, 't' => $tagId]);
        if ($stmt->fetchColumn() !== false) {
            return false;
        }
        $this->pdo->prepare('INSERT INTO contact_tags (contact_id, tag_id) VALUES (:c, :t)')
            ->execute(['c' => $contactId, 't' => $tagId]);

        return true;
    }

    /** Füllt nur leere Felder eines bestehenden Kontakts aus dem Backup. */
    private function fillEmptyContactFields(int $contactId, array $backupRow, ?string $categoryName, array &$stats): int
    {
        $stmt = $this->pdo->prepare('SELECT geschlecht, geburtstag, strasse, plz, ort, land, notizen, category_id FROM contacts WHERE id = :id');
        $stmt->execute(['id' => $contactId]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $updates = [];
        foreach (['geschlecht', 'geburtstag', 'strasse', 'plz', 'ort', 'land', 'notizen'] as $field) {
            $currentValue = trim((string) ($current[$field] ?? ''));
            $backupValue = trim((string) ($backupRow[$field] ?? ''));
            if ($currentValue === '' && $backupValue !== '') {
                $updates[$field] = $backupValue;
            }
        }
        if ($categoryName !== null && (int) ($current['category_id'] ?? 0) === 0) {
            $updates['category_id'] = $this->resolveCategoryId($categoryName, $stats);
        }

        if ($updates === []) {
            return 0;
        }

        $set = implode(', ', array_map(static fn (string $c): string => "`$c` = :$c", array_keys($updates)));
        $updates['id'] = $contactId;
        $this->pdo->prepare("UPDATE contacts SET $set WHERE id = :id")->execute($updates);

        return 1;
    }

    private function restoreMergePhoto(ZipArchive $zip, string $photoPath, array &$stats): ?string
    {
        $base = basename(trim($photoPath));
        if (!$this->isSafeUploadName($base)) {
            return null;
        }
        $contents = $zip->getFromName('uploads/' . $base);
        if ($contents === false) {
            return null;
        }
        if (!is_dir($this->uploadsDir) && !mkdir($this->uploadsDir, 0775, true) && !is_dir($this->uploadsDir)) {
            return null;
        }
        $target = $this->uploadsDir . '/' . $base;
        if (!is_file($target) && file_put_contents($target, $contents) !== false) {
            $stats['restored_photos']++;
        }

        return 'assets/uploads/' . $base;
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

    /** Diese app_settings gehören nicht ins Backup (Klartext-Geheimnisse). */
    private const SECRET_SETTINGS = ['mail_smtp_password', 'mail_imap_password'];

    private function exportTable(string $table): array
    {
        $binary = self::BINARY_COLUMNS[$table] ?? [];
        $rows = $this->pdo->query('SELECT * FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC);

        if ($table === 'app_settings') {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $r): bool => !in_array((string) ($r['setting_key'] ?? ''), self::SECRET_SETTINGS, true)
            ));
        }

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

        // Nur echte Spalten der Zieltabelle zulassen – Spaltennamen aus der
        // Backup-JSON dürfen nicht in das INSERT-Statement wandern.
        $realColumns = $this->columnsOf($table);

        $count = 0;
        $stmt = null;
        $lastColumns = null;

        foreach ($rows as $row) {
            if (!is_array($row) || $row === []) {
                continue;
            }
            $row = array_intersect_key($row, array_flip($realColumns));
            if ($row === []) {
                continue;
            }
            $columns = array_keys($row);
            if ($columns !== $lastColumns) {
                $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                $columnList = '`' . implode('`, `', array_map(
                    static fn (string $c): string => str_replace('`', '``', $c),
                    $columns
                )) . '`';
                $stmt = $this->pdo->prepare(
                    'INSERT INTO `' . str_replace('`', '``', $table) . '` (' . $columnList . ') VALUES (' . $placeholders . ')'
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

    /** @return list<string> Spaltennamen der Tabelle */
    private function columnsOf(string $table): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT column_name FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = :name'
        );
        $stmt->execute(['name' => $table]);

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Nur Dateinamen, die zum eigenen Upload-Schema passen (Zufalls-Hex plus
     * Bild-Endung). Wehrt manipulierte Backups ab, die z. B. `.htaccess` oder
     * `*.php` in den Upload-Ordner schreiben wollen.
     */
    private function isSafeUploadName(string $name): bool
    {
        if ($name === '' || str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, "\0")) {
            return false;
        }
        if (str_starts_with($name, '.') || substr_count($name, '.') !== 1) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9_-]{1,80}\.(jpe?g|png|gif|webp)$/i', $name);
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
            if (!$this->isSafeUploadName($name)) {
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

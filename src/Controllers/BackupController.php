<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Services\BackupService;
use App\Support\Redirect;
use Throwable;

final class BackupController extends BaseController
{
    private const RESTORE_KEYWORD = 'WIEDERHERSTELLEN';

    public function __construct(Auth $auth, private BackupService $backups)
    {
        parent::__construct($auth);
    }

    public function index(): void
    {
        $this->requirePermission('users.manage');

        $this->render('admin/backup', [
            'rowCounts' => $this->backups->tableRowCounts(true),
            'restoreKeyword' => self::RESTORE_KEYWORD,
        ]);
    }

    public function export(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));

        $includeLogs = (string) $request->input('include_logs', '0') === '1';

        try {
            $path = $this->backups->createArchive($includeLogs);
        } catch (Throwable $e) {
            flash('error', 'Das Backup konnte nicht erstellt werden: ' . $e->getMessage());
            Redirect::to('/admin/backup');
        }

        $name = $this->backups->suggestedFileName();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: no-store');

        readfile($path);
        @unlink($path);
        exit;
    }

    public function restore(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));

        $mode = (string) $request->input('mode', '');
        $file = $request->file('backup_file');

        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('error', 'Bitte eine Backup-ZIP-Datei auswählen.');
            Redirect::to('/admin/backup');
        }

        if ($mode === 'replace' && (string) $request->input('confirm') !== self::RESTORE_KEYWORD) {
            flash('error', 'Zur Sicherheit bitte das Wort ' . self::RESTORE_KEYWORD . ' eintippen.');
            Redirect::to('/admin/backup');
        }

        $tmp = $file['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            flash('error', 'Der Upload ist ungültig.');
            Redirect::to('/admin/backup');
        }

        try {
            $result = $this->backups->restoreArchive($tmp, $mode);
        } catch (Throwable $e) {
            flash('error', 'Wiederherstellung fehlgeschlagen: ' . $e->getMessage());
            Redirect::to('/admin/backup');
        }

        $rowSum = array_sum($result['tables']);
        flash('success', sprintf(
            'Wiederherstellung abgeschlossen: %d Datensätze in %d Tabellen, %d Dateien. Bitte neu anmelden, falls nötig.',
            $rowSum,
            count(array_filter($result['tables'], static fn (int $n): bool => $n > 0)),
            $result['uploads']
        ));
        Redirect::to('/admin/backup');
    }
}

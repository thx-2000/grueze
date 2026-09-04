<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\GalleryMediaRepository;
use App\Repositories\GalleryRepository;
use App\Repositories\LogRepository;
use App\Services\BackupService;
use App\Services\MediaService;
use App\Support\GalleryZip;
use App\Support\Redirect;
use App\Support\StreamZip;
use RuntimeException;
use Throwable;
use ZipArchive;

final class BackupController extends BaseController
{
    private const RESTORE_KEYWORD = 'WIEDERHERSTELLEN';
    private const MEDIA_BACKUP_FORMAT = 1;

    public function __construct(
        Auth $auth,
        private BackupService $backups,
        private GalleryRepository $galleries,
        private GalleryMediaRepository $galleryMedia,
        private MediaService $mediaStorage,
        private LogRepository $logs,
    ) {
        parent::__construct($auth);
    }

    public function index(): void
    {
        $this->requirePermission('users.manage');

        $this->render('admin/backup', [
            'rowCounts' => $this->backups->tableRowCounts(true),
            'restoreKeyword' => self::RESTORE_KEYWORD,
            'zipEncryption' => $this->backups->zipEncryptionAvailable(),
            'mediaBytes' => $this->galleryMedia->totalActiveBytes(),
            'mediaBackupMax' => (int) config('media.backup_max_bytes', 2147483648),
            'galleryCount' => count($this->galleries->all()),
        ]);
    }

    public function export(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));

        $includeLogs = (string) $request->input('include_logs', '0') === '1';
        $password = trim((string) $request->input('backup_password', ''));
        if ($password !== '' && mb_strlen($password) < 8) {
            flash('error', 'Das Backup-Passwort sollte mindestens 8 Zeichen haben – oder leer bleiben.');
            Redirect::to('/admin/backup');
        }

        try {
            $path = $this->backups->createArchive($includeLogs, $password !== '' ? $password : null);
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

        $password = trim((string) $request->input('backup_password', ''));

        try {
            $result = $this->backups->restoreArchive($tmp, $mode, $password !== '' ? $password : null);
        } catch (Throwable $e) {
            flash('error', ($mode === 'merge' ? 'Zusammenführen' : 'Wiederherstellung') . ' fehlgeschlagen: ' . $e->getMessage());
            Redirect::to('/admin/backup');
        }

        if ($mode === 'merge') {
            $m = $result['merge'] ?? [];
            flash('success', sprintf(
                'Zusammenführen abgeschlossen: %d neue Kontakte, %d Kontakte ergänzt (+%d Mailadressen, +%d Telefonnummern, +%d Tags). %d Kategorien und %d Tags neu angelegt, %d Fotos übernommen.',
                $m['new_contacts'] ?? 0,
                $m['updated_contacts'] ?? 0,
                $m['added_emails'] ?? 0,
                $m['added_phones'] ?? 0,
                $m['added_tags'] ?? 0,
                $m['new_categories'] ?? 0,
                $m['new_tags'] ?? 0,
                $m['restored_photos'] ?? 0,
            ));
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

    // ------------------------------------------------------ Galerie-Medien

    /** Komplette Medien-Sicherung als ZIP (alle Galerien + Manifest). */
    public function mediaExport(): void
    {
        $this->requirePermission('users.manage');

        $max = (int) config('media.backup_max_bytes', 2147483648);
        $total = $this->galleryMedia->totalActiveBytes();
        if ($max > 0 && $total > $max) {
            flash('error', 'Die Medien sind zusammen ' . MediaService::humanBytes($total)
                . ' groß – das ist mehr als das Sicherungs-Limit (' . MediaService::humanBytes($max)
                . '). Bitte einzelne Galerien über deren „Als ZIP" sichern.');
            Redirect::to('/admin/backup');
        }

        $galleries = $this->galleries->all();
        if ($galleries === []) {
            flash('error', 'Es gibt noch keine Galerie zum Sichern.');
            Redirect::to('/admin/backup');
        }

        $this->logs->addAudit((int) ($this->auth->user()['id'] ?? 0), null, 'created', 'Medien-Sicherung heruntergeladen (' . count($galleries) . ' Galerien).');

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="galerien-medien-' . date('Y-m-d') . '.zip"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-cache');

        // Direkt in den Ausgabe-Stream schreiben statt erst eine komplette
        // ZIP-Datei auf der Platte aufzubauen – der Download beginnt sofort,
        // und es wird nie doppelt so viel Platz wie die Originale gebraucht.
        $zip = new StreamZip();
        $zip->addFromString('HINWEIS.txt', GalleryZip::noticeText());
        $manifest = [
            'format' => self::MEDIA_BACKUP_FORMAT,
            'exported_at' => date('c'),
            'usage_notice' => gallery_usage_notice(),
            'galleries' => [],
        ];

        $gi = 1;
        foreach ($galleries as $gallery) {
            $folder = sprintf('%03d-%s', $gi++, GalleryZip::slug((string) $gallery['title']));
            $items = $this->galleryMedia->forGallery((int) $gallery['id'], (string) $gallery['sort_mode']);
            $entryG = [
                'title' => (string) $gallery['title'],
                'description' => (string) ($gallery['description'] ?? ''),
                'gallery_date' => $gallery['gallery_date'] ? substr((string) $gallery['gallery_date'], 0, 10) : null,
                'sort_mode' => (string) $gallery['sort_mode'],
                'event_title' => (string) ($gallery['event_title'] ?? ''),
                'media' => [],
            ];

            $used = [];
            $n = 1;
            foreach ($items as $item) {
                $abs = $this->mediaStorage->absolutePath((string) $item['stored_path']);
                if ($abs === null) {
                    continue;
                }
                $file = $folder . '/' . GalleryZip::entryName($n++, (string) ($item['original_name'] ?? ''), $used);
                $zip->addFile($abs, $file);
                $entryG['media'][] = [
                    'file' => $file,
                    'kind' => (string) $item['kind'],
                    'original_name' => (string) ($item['original_name'] ?? ''),
                    'caption' => (string) ($item['caption'] ?? ''),
                    'captured_at' => $item['captured_at'] ? substr((string) $item['captured_at'], 0, 19) : null,
                ];
            }
            $manifest['galleries'][] = $entryG;
        }

        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $zip->finish();
        exit;
    }

    /** Medien-Sicherung wieder einspielen – legt neue Galerien an (kein Merge). */
    public function mediaImport(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));

        $file = $request->file('backup_file');
        if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            flash('error', 'Bitte eine Sicherungs-ZIP auswählen.');
            Redirect::to('/admin/backup');
        }
        $max = (int) config('media.backup_max_bytes', 2147483648);
        if ($max > 0 && (int) ($file['size'] ?? 0) > $max) {
            flash('error', 'Die Datei ist größer als das Sicherungs-Limit.');
            Redirect::to('/admin/backup');
        }

        $zip = new ZipArchive();
        if ($zip->open((string) $file['tmp_name']) !== true) {
            flash('error', 'Die Datei ließ sich nicht öffnen – ist es eine gültige ZIP?');
            Redirect::to('/admin/backup');
        }
        $raw = $zip->getFromName('manifest.json');
        $manifest = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($manifest) || (int) ($manifest['format'] ?? 0) !== self::MEDIA_BACKUP_FORMAT || !is_array($manifest['galleries'] ?? null)) {
            $zip->close();
            flash('error', 'In der ZIP fehlt ein passendes manifest.json.');
            Redirect::to('/admin/backup');
        }

        $userId = (int) ($this->auth->user()['id'] ?? 0) ?: null;
        $galleriesAdded = 0;
        $mediaAdded = 0;
        foreach ($manifest['galleries'] as $g) {
            if (!is_array($g) || trim((string) ($g['title'] ?? '')) === '') {
                continue;
            }
            $galleryId = $this->galleries->create([
                'title' => mb_substr(trim((string) $g['title']), 0, 190),
                'description' => mb_substr((string) ($g['description'] ?? ''), 0, 5000),
                'gallery_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($g['gallery_date'] ?? '')) ? $g['gallery_date'] : '',
                'event_id' => null,
                'sort_mode' => in_array($g['sort_mode'] ?? '', GalleryRepository::SORT_MODES, true) ? $g['sort_mode'] : 'captured',
            ], $userId);
            $galleriesAdded++;

            foreach ((array) ($g['media'] ?? []) as $m) {
                $inZip = (string) ($m['file'] ?? '');
                if ($inZip === '' || str_contains($inZip, '..')) {
                    continue;
                }
                $bytes = $zip->getFromName($inZip);
                if ($bytes === false) {
                    continue;
                }
                $tmpFile = tempnam($this->mediaStorage->tmpDir(), 'gimp_');
                file_put_contents($tmpFile, $bytes);
                try {
                    $meta = $this->mediaStorage->adopt($tmpFile, (string) ($m['original_name'] ?? basename($inZip)));
                    $capManifest = (string) ($m['captured_at'] ?? '');
                    if (($meta['captured_at'] ?? null) === null && preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}$/', $capManifest)) {
                        $meta['captured_at'] = str_replace('T', ' ', $capManifest);
                    }
                    $mediaId = $this->galleryMedia->add($galleryId, $meta, $userId);
                    if (trim((string) ($m['caption'] ?? '')) !== '') {
                        $this->galleryMedia->updateCaption($mediaId, (string) $m['caption']);
                    }
                    $mediaAdded++;
                } catch (RuntimeException) {
                    // einzelne Datei überspringen
                } finally {
                    @unlink($tmpFile);
                }
            }
        }
        $zip->close();

        $this->logs->addAudit((int) $userId, null, 'created', "Medien-Sicherung eingespielt: {$galleriesAdded} Galerien, {$mediaAdded} Medien.");
        flash('success', "Eingespielt: {$galleriesAdded} Galerien, {$mediaAdded} Medien (als neue Galerien).");
        Redirect::to('/admin/backup');
    }
}

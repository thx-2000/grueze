<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\EventRepository;
use App\Repositories\GalleryMediaRepository;
use App\Repositories\GalleryRepository;
use App\Repositories\LogRepository;
use App\Services\MediaService;
use App\Support\FileResponse;
use App\Support\JsonResponse;
use App\Support\Redirect;
use RuntimeException;
use ZipArchive;

/**
 * Galerien: Foto-/Video-Sammlungen (z. B. pro Stufentreffen). Vorerst nur für
 * Rollen mit `galleries.manage` (Standard: nur Admin) – die feinere
 * Rechteverteilung (wer sieht/lädt hoch/verschiebt/löscht) kommt später.
 */
final class GalleryController extends BaseController
{
    public function __construct(
        \App\Core\Auth $auth,
        private GalleryRepository $galleries,
        private GalleryMediaRepository $media,
        private MediaService $storage,
        private EventRepository $events,
        private LogRepository $logs,
    ) {
        parent::__construct($auth);
    }

    // ------------------------------------------------------------- Übersicht

    public function index(): void
    {
        $this->requirePermission('galleries.manage');

        $this->render('galleries/index', [
            'galleries' => $this->galleries->all(),
            'trashedCount' => count($this->galleries->trashed()),
            'capabilities' => $this->storage->capabilities(),
        ]);
    }

    public function createForm(): void
    {
        $this->requirePermission('galleries.manage');

        $this->render('galleries/form', [
            'gallery' => null,
            'events' => $this->events->all(),
        ]);
    }

    public function store(Request $request): void
    {
        $this->requirePermission('galleries.manage');
        Csrf::validate($request->input('_csrf'));

        $data = $this->sanitize($request);
        if ($data['title'] === '') {
            flash('error', 'Bitte einen Titel angeben.');
            Redirect::to('/galerien/neu');
        }

        $id = $this->galleries->create($data, (int) ($this->auth->user()['id'] ?? 0) ?: null);
        $this->logs->addAudit((int) ($this->auth->user()['id'] ?? 0), null, 'created', 'Galerie angelegt: „' . $data['title'] . '".');
        flash('success', 'Galerie angelegt. Jetzt Bilder und Videos hochladen.');
        Redirect::to('/galerien/ansehen?id=' . $id);
    }

    // ---------------------------------------------------------- eine Galerie

    public function show(Request $request): void
    {
        $this->requirePermission('galleries.manage');

        $gallery = $this->galleries->find((int) $request->input('id'));
        if ($gallery === null) {
            flash('error', 'Galerie nicht gefunden.');
            Redirect::to('/galerien');
        }

        $items = $this->media->forGallery((int) $gallery['id'], (string) $gallery['sort_mode']);

        $this->render('galleries/show', [
            'gallery' => $gallery,
            'items' => $items,
            'events' => $this->events->all(),
            'capabilities' => $this->storage->capabilities(),
        ]);
    }

    public function update(Request $request): void
    {
        $this->requirePermission('galleries.manage');
        Csrf::validate($request->input('_csrf'));

        $gallery = $this->galleries->find((int) $request->input('id'));
        if ($gallery === null) {
            Redirect::to('/galerien');
        }

        $data = $this->sanitize($request);
        if ($data['title'] === '') {
            flash('error', 'Bitte einen Titel angeben.');
            Redirect::to('/galerien/ansehen?id=' . $gallery['id']);
        }

        $this->galleries->update((int) $gallery['id'], $data);
        flash('success', 'Galerie gespeichert.');
        Redirect::to('/galerien/ansehen?id=' . $gallery['id']);
    }

    // ------------------------------------------------------------- Upload

    public function upload(Request $request): void
    {
        $this->requirePermission('galleries.manage');
        Csrf::validate($request->input('_csrf'));

        $gallery = $this->galleries->find((int) $request->input('gallery_id'));
        if ($gallery === null) {
            JsonResponse::send(['ok' => false, 'error' => 'Galerie nicht gefunden.'], 404);
        }

        $file = $request->file('file');
        if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            JsonResponse::send(['ok' => false, 'error' => 'Keine Datei empfangen (evtl. zu groß fürs Server-Limit).'], 400);
        }
        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            JsonResponse::send(['ok' => false, 'error' => $this->uploadErrorText((int) $file['error'])], 400);
        }
        if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            JsonResponse::send(['ok' => false, 'error' => 'Ungültiger Upload.'], 400);
        }

        // Optionales, im Browser erzeugtes Video-Vorschaubild.
        $posterTmp = null;
        $poster = $request->file('poster');
        if ($poster && (int) ($poster['error'] ?? 1) === UPLOAD_ERR_OK && is_uploaded_file((string) $poster['tmp_name'])) {
            $posterTmp = (string) $poster['tmp_name'];
        }

        try {
            $meta = $this->storage->ingest(
                (string) $file['tmp_name'],
                (string) ($file['name'] ?? 'datei'),
                (int) ($file['size'] ?? 0),
                $posterTmp
            );
        } catch (RuntimeException $e) {
            JsonResponse::send(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        $mediaId = $this->media->add((int) $gallery['id'], $meta, (int) ($this->auth->user()['id'] ?? 0) ?: null);

        JsonResponse::send([
            'ok' => true,
            'media' => [
                'id' => $mediaId,
                'kind' => $meta['kind'],
                'name' => $meta['original_name'],
                'has_thumb' => !empty($meta['thumb_path']),
                'thumb_url' => url('/galerien/datei?id=' . $mediaId . '&v=thumb'),
                'full_url' => url('/galerien/datei?id=' . $mediaId . '&v=' . ($meta['kind'] === 'video' ? 'original' : 'web')),
                'download_url' => url('/galerien/datei?id=' . $mediaId . '&v=original&dl=1'),
            ],
        ]);
    }

    // -------------------------------------------------------- Medien-Aktionen

    public function mediaCaption(Request $request): void
    {
        $this->requirePermission('galleries.manage');
        Csrf::validate($request->input('_csrf'));

        $item = $this->mediaInGallery($request);
        $this->media->updateCaption((int) $item['id'], trim((string) $request->input('caption')));
        JsonResponse::send(['ok' => true]);
    }

    public function mediaReorder(Request $request): void
    {
        $this->requirePermission('galleries.manage');
        Csrf::validate($request->input('_csrf'));

        $gallery = $this->galleries->find((int) $request->input('gallery_id'));
        if ($gallery === null) {
            JsonResponse::send(['ok' => false], 404);
        }
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('order', []))));
        $this->media->reorder((int) $gallery['id'], $ids);
        JsonResponse::send(['ok' => true]);
    }

    public function mediaDelete(Request $request): void
    {
        $this->requirePermission('galleries.manage');
        Csrf::validate($request->input('_csrf'));

        $item = $this->mediaInGallery($request);
        $this->media->softDelete((int) $item['id']);
        JsonResponse::send(['ok' => true]);
    }

    public function setCover(Request $request): void
    {
        $this->requirePermission('galleries.manage');
        Csrf::validate($request->input('_csrf'));

        $item = $this->mediaInGallery($request);
        $this->galleries->setCover((int) $item['gallery_id'], (int) $item['id']);
        JsonResponse::send(['ok' => true]);
    }

    // ---------------------------------------------------------- Galerie weg

    public function deleteGallery(Request $request): void
    {
        $this->requirePermission('galleries.manage');
        Csrf::validate($request->input('_csrf'));

        $gallery = $this->galleries->find((int) $request->input('id'));
        if ($gallery !== null) {
            $this->galleries->softDelete((int) $gallery['id']);
            $this->logs->addAudit((int) ($this->auth->user()['id'] ?? 0), null, 'deleted', 'Galerie in den Papierkorb: „' . $gallery['title'] . '".');
            flash('success', '„' . $gallery['title'] . '" liegt im Papierkorb.');
        }
        Redirect::to('/galerien');
    }

    public function trash(): void
    {
        $this->requirePermission('galleries.manage');
        $this->render('galleries/trash', [
            'galleries' => $this->galleries->trashed(),
            'trashDays' => (int) config('media.trash_days', 30),
        ]);
    }

    public function restoreGallery(Request $request): void
    {
        $this->requirePermission('galleries.manage');
        Csrf::validate($request->input('_csrf'));
        $this->galleries->restore((int) $request->input('id'));
        flash('success', 'Galerie wiederhergestellt.');
        Redirect::to('/galerien/papierkorb');
    }

    public function purgeGallery(Request $request): void
    {
        $this->requirePermission('galleries.manage');
        Csrf::validate($request->input('_csrf'));

        $gallery = $this->galleries->find((int) $request->input('id'), true);
        if ($gallery !== null && $gallery['deleted_at'] !== null) {
            foreach ($this->media->allForGallery((int) $gallery['id']) as $row) {
                $this->storage->deleteFiles($row);
            }
            $this->galleries->hardDelete((int) $gallery['id']);
            $this->logs->addAudit((int) ($this->auth->user()['id'] ?? 0), null, 'deleted', 'Galerie endgültig gelöscht: „' . $gallery['title'] . '".');
            flash('success', 'Galerie endgültig gelöscht.');
        }
        Redirect::to('/galerien/papierkorb');
    }

    // -------------------------------------------------------------- Dateien

    public function file(Request $request): void
    {
        $this->requirePermission('galleries.manage');

        $item = $this->media->find((int) $request->input('id'), true);
        if ($item === null) {
            http_response_code(404);
            exit;
        }

        // Session-Lock früh freigeben – eine Galerie lädt viele Dateien parallel.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $variant = (string) $request->input('v', 'web');
        $relative = match ($variant) {
            // Kein Thumbnail (Video ohne Poster, GD fehlt) → 404, das Frontend
            // zeigt dann ein Symbol statt eines kaputten Bildes.
            'thumb' => (string) ($item['thumb_path'] ?? ''),
            'original' => (string) $item['stored_path'],
            default => (string) ($item['web_path'] ?? '') ?: (string) $item['stored_path'],
        };
        if ($relative === '') {
            http_response_code(404);
            exit;
        }

        $abs = $this->storage->absolutePath($relative);
        if ($abs === null) {
            http_response_code(404);
            exit;
        }

        $mime = match (strtolower(pathinfo($abs, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            default => (string) $item['mime'],
        };

        $downloadName = null;
        if ($request->input('dl') === '1') {
            $downloadName = (string) ($item['original_name'] ?? 'datei');
        }

        FileResponse::stream($abs, $mime, $downloadName, $downloadName === null ? 86400 : 0);
    }

    public function downloadZip(Request $request): void
    {
        $this->requirePermission('galleries.manage');

        $gallery = $this->galleries->find((int) $request->input('id'));
        if ($gallery === null) {
            flash('error', 'Galerie nicht gefunden.');
            Redirect::to('/galerien');
        }

        $items = $this->media->forGallery((int) $gallery['id'], (string) $gallery['sort_mode']);
        if ($items === []) {
            flash('error', 'Die Galerie enthält noch keine Dateien.');
            Redirect::to('/galerien/ansehen?id=' . $gallery['id']);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'gzip_') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            flash('error', 'Das ZIP konnte nicht erstellt werden.');
            Redirect::to('/galerien/ansehen?id=' . $gallery['id']);
        }

        $used = [];
        $n = 1;
        foreach ($items as $item) {
            $abs = $this->storage->absolutePath((string) $item['stored_path']);
            if ($abs === null) {
                continue;
            }
            $base = (string) ($item['original_name'] ?? '') !== ''
                ? preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $item['original_name'])
                : 'datei';
            $entry = sprintf('%03d_%s', $n++, $base);
            while (isset($used[$entry])) {
                $entry = sprintf('%03d_%s', $n++, $base);
            }
            $used[$entry] = true;
            $zip->addFile($abs, $entry);
        }
        $zip->close();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $name = 'galerie-' . preg_replace('/[^a-z0-9]+/i', '-', mb_strtolower((string) $gallery['title'])) . '.zip';
        register_shutdown_function(static fn () => @unlink($tmp));
        FileResponse::stream($tmp, 'application/zip', $name, 0);
    }

    // --------------------------------------------------------------- intern

    /** @return array<string,mixed> */
    private function mediaInGallery(Request $request): array
    {
        $item = $this->media->find((int) $request->input('media_id'), true);
        if ($item === null) {
            JsonResponse::send(['ok' => false, 'error' => 'Medium nicht gefunden.'], 404);
        }

        return $item;
    }

    /** @return array<string,mixed> */
    private function sanitize(Request $request): array
    {
        return [
            'title' => mb_substr(trim((string) $request->input('title')), 0, 190),
            'description' => mb_substr(trim((string) $request->input('description')), 0, 5000),
            'gallery_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->input('gallery_date'))
                ? (string) $request->input('gallery_date')
                : '',
            'event_id' => (int) $request->input('event_id') ?: null,
            'sort_mode' => in_array((string) $request->input('sort_mode'), GalleryRepository::SORT_MODES, true)
                ? (string) $request->input('sort_mode')
                : 'captured',
        ];
    }

    private function uploadErrorText(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Die Datei ist größer als das Server-Limit erlaubt.',
            UPLOAD_ERR_PARTIAL => 'Der Upload wurde abgebrochen.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'Der Server konnte die Datei nicht ablegen.',
            default => 'Der Upload ist fehlgeschlagen.',
        };
    }
}

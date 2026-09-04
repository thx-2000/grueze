<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\EventRepository;
use App\Repositories\GalleryMediaRepository;
use App\Repositories\GalleryRepository;
use App\Repositories\GroupRepository;
use App\Repositories\LogRepository;
use App\Repositories\SettingRepository;
use App\Services\MediaService;
use App\Support\FileResponse;
use App\Support\JsonResponse;
use App\Support\Redirect;
use RuntimeException;
use ZipArchive;

/**
 * Galerien: Foto-/Video-Sammlungen (z. B. pro Stufentreffen).
 *
 * Drei globale Rechte:
 *  - galleries.view    ansehen + herunterladen
 *  - galleries.upload  Medien hochladen, eigene Uploads beschriften/löschen
 *  - galleries.manage  Galerien anlegen/bearbeiten/löschen, fremde Medien
 *                      verschieben/löschen, Titelbild, Sortierung, Sicherung,
 *                      Auffangraum, Papierkorb, Sicherung – alles global
 *
 * Zusätzlich, unabhängig von den globalen Rechten: Gruppenleitung darf für
 * die eigene Gruppe eine Galerie anlegen (`owner_group_id`) und verwalten,
 * auch ohne `galleries.manage`. Jede Galerie kann ihre Sichtbarkeit auf eine
 * Gruppe einschränken (`visible_group_id`, NULL = normale globale Rechte).
 * Admin sieht/verwaltet über `galleries.manage` ohnehin immer alles.
 */
final class GalleryController extends BaseController
{
    private const NOTICE_KEY = 'gallery_usage_notice';
    private const NOTICE_DEFAULT = 'Diese Aufnahmen sind für die Teilnehmenden des Treffens zum Ansehen gedacht. '
        . 'Eine Weitergabe oder Veröffentlichung – etwa in sozialen Netzwerken – ist nur mit Einverständnis der '
        . 'abgebildeten Personen zulässig.';

    private ?array $leadGroupIdsCache = null;
    private ?array $memberGroupIdsCache = null;

    public function __construct(
        \App\Core\Auth $auth,
        private GalleryRepository $galleries,
        private GalleryMediaRepository $media,
        private MediaService $storage,
        private EventRepository $events,
        private LogRepository $logs,
        private SettingRepository $settings,
        private \App\Repositories\GalleryUploadLinkRepository $uploadLinks,
        private GroupRepository $groups,
    ) {
        parent::__construct($auth);
    }

    // ------------------------------------------------------------- Übersicht

    public function index(): void
    {
        $this->requireGalleryAccess();

        $canManage = $this->canManage();
        $visible = array_values(array_filter(
            $this->galleries->all(),
            fn (array $g): bool => $this->canViewGallery($g)
        ));

        $this->render('galleries/index', [
            'galleries' => $visible,
            'canCreate' => $this->canCreateGallery(),
            'trashedCount' => $canManage ? count($this->galleries->trashed()) : 0,
            'capabilities' => $this->storage->capabilities(),
            'canManage' => $canManage,
            'canUpload' => $this->canUpload(),
            'usageNotice' => gallery_usage_notice(),
            'unassignedCount' => $canManage ? $this->media->countUnassigned() : 0,
            'catchAllLinks' => $canManage ? $this->uploadLinks->active(0) : [],
            'freshLink' => $canManage ? $this->takeFreshLink(null) : null,
            'linkDays' => (int) config('media.link_expiry_days', 21),
        ]);
    }

    public function createForm(): void
    {
        $this->requireCreate();

        $this->render('galleries/form', [
            'gallery' => null,
            'events' => $this->events->all(),
            'groupChoices' => $this->groupChoicesForCreate(),
            'canPickGroup' => $this->canManage(),
        ]);
    }

    public function store(Request $request): void
    {
        $this->requireCreate();
        Csrf::validate($request->input('_csrf'));

        $data = $this->sanitizeGroups($this->sanitize($request), $request, null);
        if ($data['title'] === '') {
            flash('error', 'Bitte einen Titel angeben.');
            Redirect::to('/galerien/neu');
        }

        $id = $this->galleries->create($data, $this->userId());
        $this->logs->addAudit((int) $this->userId(), null, 'created', 'Galerie angelegt: „' . $data['title'] . '".');
        flash('success', 'Galerie angelegt. Jetzt Bilder und Videos hochladen.');
        Redirect::to('/galerien/ansehen?id=' . $id);
    }

    // ---------------------------------------------------------- eine Galerie

    public function show(Request $request): void
    {
        $this->requireAuth();
        $gallery = $this->galleries->find((int) $request->input('id'));
        if ($gallery === null || !$this->canViewGallery($gallery)) {
            flash('error', 'Galerie nicht gefunden.');
            Redirect::to('/galerien');
        }

        $items = $this->media->forGallery((int) $gallery['id'], (string) $gallery['sort_mode']);
        $canManageThis = $this->canManageGallery($gallery);
        $canUploadThis = $this->canUploadToGallery($gallery);

        $this->render('galleries/show', [
            'gallery' => $gallery,
            'items' => $items,
            'events' => $this->events->all(),
            'capabilities' => $this->storage->capabilities(),
            'canManage' => $canManageThis,
            'canUpload' => $canUploadThis,
            'currentUserId' => (int) $this->userId(),
            'usageNotice' => gallery_usage_notice(),
            'uploadLinks' => $canManageThis ? $this->uploadLinks->active((int) $gallery['id']) : [],
            'freshLink' => $canManageThis ? $this->takeFreshLink((int) $gallery['id']) : null,
            'linkDays' => (int) config('media.link_expiry_days', 21),
            'groupChoices' => $this->groupChoicesForCreate(),
            'canPickGroup' => $this->canManage(),
        ]);
    }

    public function update(Request $request): void
    {
        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));

        $gallery = $this->galleries->find((int) $request->input('id'));
        if ($gallery === null || !$this->canManageGallery($gallery)) {
            Redirect::to('/galerien');
        }

        $data = $this->sanitizeGroups($this->sanitize($request), $request, $gallery);
        if ($data['title'] === '') {
            flash('error', 'Bitte einen Titel angeben.');
            Redirect::to('/galerien/ansehen?id=' . $gallery['id']);
        }

        $this->galleries->update((int) $gallery['id'], $data);
        flash('success', 'Galerie gespeichert.');
        Redirect::to('/galerien/ansehen?id=' . $gallery['id']);
    }

    /** Hinweistext (Urheber-/Persönlichkeitsrechte) ändern. */
    public function updateNotice(Request $request): void
    {
        $this->requireManage();
        Csrf::validate($request->input('_csrf'));

        $text = trim((string) $request->input('usage_notice'));
        $this->settings->set(self::NOTICE_KEY, $text !== '' ? mb_substr($text, 0, 1000) : self::NOTICE_DEFAULT);
        flash('success', 'Hinweistext gespeichert.');
        Redirect::to('/galerien');
    }

    // -------------------------------------------------- Weitergabe-Links (QR)

    public function createLink(Request $request): void
    {
        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));

        $galleryId = (int) $request->input('gallery_id') ?: null;
        $gallery = $galleryId !== null ? $this->galleries->find($galleryId) : null;
        if ($galleryId !== null && $gallery === null) {
            flash('error', 'Galerie nicht gefunden.');
            Redirect::to('/galerien');
        }
        // Auffangraum-Link (kein $gallery) bleibt eine globale Verwaltungs-
        // Aktion; ein Link auf eine bestimmte Galerie darf auch deren
        // Gruppenleitung erzeugen.
        $allowed = $gallery !== null ? $this->canManageGallery($gallery) : $this->canManage();
        if (!$allowed) {
            throw new RuntimeException('Zum Erzeugen eines Upload-Links fehlt die Berechtigung.');
        }

        $days = max(1, min(365, (int) $request->input('days', (int) config('media.link_expiry_days', 21))));
        $maxUploads = (int) $request->input('max_uploads') ?: null;
        $label = trim((string) $request->input('label'));

        $token = $this->uploadLinks->create($galleryId, $label !== '' ? $label : null, $days, $maxUploads, $this->userId());
        $url = url('/beitragen/' . rawurlencode($token));

        $_SESSION['fresh_upload_link'] = ['url' => $url, 'for_gallery' => $galleryId];
        $this->logs->addAudit((int) $this->userId(), null, 'created',
            'Galerie-Upload-Link erstellt' . ($gallery ? ' für „' . $gallery['title'] . '"' : ' (Auffangraum)') . ', gültig ' . $days . ' Tage.');
        flash('success', 'Upload-Link erstellt – unten zum Kopieren und als QR-Code.');
        Redirect::to($galleryId !== null ? '/galerien/ansehen?id=' . $galleryId : '/galerien');
    }

    public function revokeLink(Request $request): void
    {
        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));

        $link = $this->uploadLinks->find((int) $request->input('id'));
        if ($link !== null) {
            $gallery = $link['gallery_id'] !== null ? $this->galleries->find((int) $link['gallery_id']) : null;
            $allowed = $gallery !== null ? $this->canManageGallery($gallery) : $this->canManage();
            if (!$allowed) {
                throw new RuntimeException('Zum Zurückziehen dieses Links fehlt die Berechtigung.');
            }
            $this->uploadLinks->revoke((int) $link['id']);
            flash('success', 'Der Upload-Link ist jetzt ungültig.');
        }
        Redirect::to($link && $link['gallery_id'] !== null ? '/galerien/ansehen?id=' . (int) $link['gallery_id'] : '/galerien');
    }

    /**
     * Für einen bestehenden Link einen QR-Code besorgen: das Klartext-Token
     * ist nur bei der Erzeugung bekannt (danach nur noch gehasht) – also den
     * alten Link zurückziehen und mit denselben Eckdaten (Bezeichnung, Max.
     * Uploads) einen neuen erzeugen, der sofort mit QR-Code angezeigt wird.
     */
    public function renewLink(Request $request): void
    {
        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));

        $old = $this->uploadLinks->find((int) $request->input('id'));
        if ($old === null) {
            flash('error', 'Link nicht gefunden.');
            Redirect::to('/galerien');
        }

        $galleryId = $old['gallery_id'] !== null ? (int) $old['gallery_id'] : null;
        $gallery = $galleryId !== null ? $this->galleries->find($galleryId) : null;
        $allowed = $gallery !== null ? $this->canManageGallery($gallery) : $this->canManage();
        if (!$allowed) {
            throw new RuntimeException('Zum Erneuern dieses Links fehlt die Berechtigung.');
        }

        $this->uploadLinks->revoke((int) $old['id']);

        $days = max(1, min(365, (int) config('media.link_expiry_days', 21)));
        $label = trim((string) ($old['label'] ?? ''));
        $maxUploads = $old['max_uploads'] !== null ? (int) $old['max_uploads'] : null;
        $token = $this->uploadLinks->create($galleryId, $label !== '' ? $label : null, $days, $maxUploads, $this->userId());
        $url = url('/beitragen/' . rawurlencode($token));

        $_SESSION['fresh_upload_link'] = ['url' => $url, 'for_gallery' => $galleryId];
        $this->logs->addAudit((int) $this->userId(), null, 'created',
            'Galerie-Upload-Link erneuert' . ($gallery ? ' für „' . $gallery['title'] . '"' : ' (Auffangraum)') . ', gültig ' . $days . ' Tage.');
        flash('success', 'Neuer Link erstellt (der alte ist jetzt ungültig) – unten zum Kopieren und als QR-Code.');
        Redirect::to($galleryId !== null ? '/galerien/ansehen?id=' . $galleryId : '/galerien');
    }

    // ---------------------------------------------------- Auffangraum

    public function unassigned(): void
    {
        $this->requireManage();

        $this->render('galleries/unassigned', [
            'items' => $this->media->unassigned(),
            'galleries' => $this->galleries->all(),
            'usageNotice' => gallery_usage_notice(),
        ]);
    }

    public function moveMedia(Request $request): void
    {
        $this->requireManage();
        Csrf::validate($request->input('_csrf'));

        $ids = array_values(array_filter(array_map('intval', (array) $request->input('media_id', []))));
        if ($ids === []) {
            flash('error', 'Keine Medien ausgewählt.');
            Redirect::to('/galerien/auffang');
        }

        $target = (string) $request->input('target');
        if ($target === 'new') {
            $title = mb_substr(trim((string) $request->input('new_title')), 0, 190);
            if ($title === '') {
                flash('error', 'Bitte einen Titel für die neue Galerie angeben.');
                Redirect::to('/galerien/auffang');
            }
            $galleryId = $this->galleries->create(['title' => $title, 'sort_mode' => 'captured'], $this->userId());
        } else {
            $galleryId = (int) $target;
            if ($this->galleries->find($galleryId) === null) {
                flash('error', 'Zielgalerie nicht gefunden.');
                Redirect::to('/galerien/auffang');
            }
        }

        $moved = 0;
        foreach ($ids as $id) {
            $item = $this->media->find($id, true);
            if ($item !== null && $item['gallery_id'] === null && $item['deleted_at'] === null) {
                $this->media->move($id, $galleryId);
                $moved++;
            }
        }
        $this->logs->addAudit((int) $this->userId(), null, 'updated', $moved . ' Medien aus dem Auffangraum einer Galerie zugeordnet.');
        flash('success', $moved . ' ' . ($moved === 1 ? 'Medium' : 'Medien') . ' verschoben.');
        Redirect::to('/galerien/ansehen?id=' . $galleryId);
    }

    // ------------------------------------------------------------- Upload

    public function upload(Request $request): void
    {
        // Ausgabe puffern: eine evtl. PHP-Warnung (aktives display_errors auf
        // dem Server) darf die JSON-Antwort nicht zerschießen. JsonResponse
        // verwirft den Puffer vor dem Senden.
        ob_start();

        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));

        $gallery = $this->galleries->find((int) $request->input('gallery_id'));
        if ($gallery === null) {
            JsonResponse::send(['ok' => false, 'error' => 'Galerie nicht gefunden.'], 404);
        }
        if (!$this->canUploadToGallery($gallery)) {
            JsonResponse::send(['ok' => false, 'error' => 'Zum Hochladen fehlt die Berechtigung.'], 403);
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

        $mediaId = $this->media->add((int) $gallery['id'], $meta, $this->userId());

        JsonResponse::send([
            'ok' => true,
            'media' => [
                'id' => $mediaId,
                'kind' => $meta['kind'],
                'name' => $meta['original_name'],
                'has_thumb' => !empty($meta['thumb_path']),
                'own' => true,
                'thumb_url' => url('/galerien/datei?id=' . $mediaId . '&v=thumb'),
                'full_url' => url('/galerien/datei?id=' . $mediaId . '&v=' . ($meta['kind'] === 'video' ? 'original' : 'web')),
                'download_url' => url('/galerien/datei?id=' . $mediaId . '&v=original&dl=1'),
            ],
        ]);
    }

    // -------------------------------------------------------- Medien-Aktionen

    public function mediaCaption(Request $request): void
    {
        ob_start();
        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));

        $item = $this->mediaInGallery($request);
        $this->requireMediaEdit($item);
        $this->media->updateCaption((int) $item['id'], trim((string) $request->input('caption')));
        JsonResponse::send(['ok' => true]);
    }

    public function mediaReorder(Request $request): void
    {
        ob_start();
        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));

        $gallery = $this->galleries->find((int) $request->input('gallery_id'));
        if ($gallery === null || !$this->canManageGallery($gallery)) {
            JsonResponse::send(['ok' => false], 404);
        }
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('order', []))));
        $this->media->reorder((int) $gallery['id'], $ids);
        JsonResponse::send(['ok' => true]);
    }

    public function mediaDelete(Request $request): void
    {
        ob_start();
        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));

        $item = $this->mediaInGallery($request);
        $this->requireMediaEdit($item);
        $this->media->softDelete((int) $item['id']);
        JsonResponse::send(['ok' => true]);
    }

    public function setCover(Request $request): void
    {
        ob_start();
        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));

        $item = $this->mediaInGallery($request);
        $gallery = $this->galleryOfMedia($item);
        if ($gallery === null || !$this->canManageGallery($gallery)) {
            JsonResponse::send(['ok' => false, 'error' => 'Keine Berechtigung.'], 403);
        }
        $this->galleries->setCover((int) $item['gallery_id'], (int) $item['id']);
        JsonResponse::send(['ok' => true]);
    }

    // ---------------------------------------------------------- Galerie weg

    public function deleteGallery(Request $request): void
    {
        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));

        $gallery = $this->galleries->find((int) $request->input('id'));
        if ($gallery !== null && !$this->canManageGallery($gallery)) {
            throw new RuntimeException('Zum Löschen dieser Galerie fehlt die Berechtigung.');
        }
        if ($gallery !== null) {
            $this->galleries->softDelete((int) $gallery['id']);
            $this->logs->addAudit((int) $this->userId(), null, 'deleted', 'Galerie in den Papierkorb: „' . $gallery['title'] . '".');
            flash('success', '„' . $gallery['title'] . '" liegt im Papierkorb.');
        }
        Redirect::to('/galerien');
    }

    public function trash(): void
    {
        $this->requireManage();
        $this->render('galleries/trash', [
            'galleries' => $this->galleries->trashed(),
            'trashDays' => (int) config('media.trash_days', 30),
        ]);
    }

    public function restoreGallery(Request $request): void
    {
        $this->requireManage();
        Csrf::validate($request->input('_csrf'));
        $this->galleries->restore((int) $request->input('id'));
        flash('success', 'Galerie wiederhergestellt.');
        Redirect::to('/galerien/papierkorb');
    }

    public function purgeGallery(Request $request): void
    {
        $this->requireManage();
        Csrf::validate($request->input('_csrf'));

        $gallery = $this->galleries->find((int) $request->input('id'), true);
        if ($gallery !== null && $gallery['deleted_at'] !== null) {
            foreach ($this->media->allForGallery((int) $gallery['id']) as $row) {
                $this->storage->deleteFiles($row);
            }
            $this->galleries->hardDelete((int) $gallery['id']);
            $this->logs->addAudit((int) $this->userId(), null, 'deleted', 'Galerie endgültig gelöscht: „' . $gallery['title'] . '".');
            flash('success', 'Galerie endgültig gelöscht.');
        }
        Redirect::to('/galerien/papierkorb');
    }

    // -------------------------------------------------------------- Dateien

    public function file(Request $request): void
    {
        $this->requireAuth();

        $item = $this->media->find((int) $request->input('id'), true);
        if ($item === null) {
            http_response_code(404);
            exit;
        }
        $gallery = $this->galleryOfMedia($item);
        $allowed = $gallery !== null ? $this->canViewGallery($gallery) : $this->canManage();
        if (!$allowed) {
            http_response_code(404);
            exit;
        }

        // Session-Lock früh freigeben – eine Galerie lädt viele Dateien parallel.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $variant = (string) $request->input('v', 'web');
        $relative = match ($variant) {
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
        $this->requireAuth();

        $gallery = $this->galleries->find((int) $request->input('id'));
        if ($gallery === null || !$this->canViewGallery($gallery)) {
            flash('error', 'Galerie nicht gefunden.');
            Redirect::to('/galerien');
        }

        $items = $this->media->forGallery((int) $gallery['id'], (string) $gallery['sort_mode']);
        if ($items === []) {
            flash('error', 'Die Galerie enthält noch keine Dateien.');
            Redirect::to('/galerien/ansehen?id=' . $gallery['id']);
        }

        $tmp = tempnam($this->storage->tmpDir(), 'gzip_') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            flash('error', 'Das ZIP konnte nicht erstellt werden.');
            Redirect::to('/galerien/ansehen?id=' . $gallery['id']);
        }

        $zip->addFromString('HINWEIS.txt', \App\Support\GalleryZip::noticeText());
        $used = [];
        $n = 1;
        foreach ($items as $item) {
            $abs = $this->storage->absolutePath((string) $item['stored_path']);
            if ($abs === null) {
                continue;
            }
            $entry = \App\Support\GalleryZip::entryName($n++, (string) ($item['original_name'] ?? ''), $used);
            $zip->addFile($abs, $entry);
        }
        $zip->close();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $name = 'galerie-' . \App\Support\GalleryZip::slug((string) $gallery['title']) . '.zip';
        register_shutdown_function(static fn () => @unlink($tmp));
        FileResponse::stream($tmp, 'application/zip', $name, 0);
    }

    // --------------------------------------------------------------- intern

    private function userId(): ?int
    {
        return (int) ($this->auth->user()['id'] ?? 0) ?: null;
    }

    private function canView(): bool
    {
        return can_any('galleries.view', 'galleries.upload', 'galleries.manage');
    }

    private function canUpload(): bool
    {
        return can_any('galleries.upload', 'galleries.manage');
    }

    private function canManage(): bool
    {
        return $this->auth->can('galleries.manage');
    }

    private function requireManage(): void
    {
        $this->requireAuth();
        if (!$this->canManage()) {
            throw new RuntimeException('Zum Verwalten von Galerien fehlt die Berechtigung.');
        }
    }

    /** Betreten der Galerien-Übersicht: globale Rechte ODER eigene Gruppen-Galerien. */
    private function requireGalleryAccess(): void
    {
        $this->requireAuth();
        if (!$this->canView() && $this->leadGroupIds() === [] && $this->memberGroupIds() === []) {
            throw new RuntimeException('Für die Galerien fehlt die Berechtigung.');
        }
    }

    private function requireCreate(): void
    {
        $this->requireAuth();
        if (!$this->canCreateGallery()) {
            throw new RuntimeException('Zum Anlegen einer Galerie fehlt die Berechtigung.');
        }
    }

    /** Globales Verwalten ODER Gruppenleitung mit mindestens einer Gruppe. */
    private function canCreateGallery(): bool
    {
        return $this->canManage() || $this->leadGroupIds() !== [];
    }

    /** Darf diese eine Galerie angesehen (und heruntergeladen) werden? */
    private function canViewGallery(array $gallery): bool
    {
        if ($this->canManage()) {
            return true;
        }
        $groupId = (int) ($gallery['visible_group_id'] ?? 0) ?: null;

        return $groupId === null ? $this->canView() : $this->isMemberOfGroup($groupId);
    }

    /** Darf diese eine Galerie verwaltet werden (bearbeiten/löschen/Titelbild/Links/Sortierung)? */
    private function canManageGallery(array $gallery): bool
    {
        if ($this->canManage()) {
            return true;
        }
        $ownerGroupId = (int) ($gallery['owner_group_id'] ?? 0) ?: null;

        return $ownerGroupId !== null && $this->isLeadOfGroup($ownerGroupId);
    }

    /** Darf in diese eine Galerie hochgeladen werden? */
    private function canUploadToGallery(array $gallery): bool
    {
        if ($this->canManageGallery($gallery)) {
            return true;
        }
        $groupId = (int) ($gallery['visible_group_id'] ?? 0) ?: null;

        return $groupId === null ? $this->canUpload() : ($this->isMemberOfGroup($groupId) && $this->canUpload());
    }

    /** Darf die aktuelle Person dieses Medium bearbeiten/löschen? */
    private function requireMediaEdit(array $item): void
    {
        $gallery = $this->galleryOfMedia($item);
        if ($gallery !== null && $this->canManageGallery($gallery)) {
            return;
        }
        if ($gallery === null && $this->canManage()) {
            return; // Auffangraum: nur global verwaltbar
        }
        $uid = $this->userId();
        if ($gallery !== null && $uid !== null && (int) ($item['uploaded_by'] ?? 0) === $uid && $this->canUploadToGallery($gallery)) {
            return;
        }
        JsonResponse::send(['ok' => false, 'error' => 'Nur eigene Uploads oder mit Verwalten-Recht.'], 403);
    }

    /** Galerie zu einem Medium – null, wenn das Medium (noch) im Auffangraum liegt. */
    private function galleryOfMedia(array $item): ?array
    {
        $galleryId = (int) ($item['gallery_id'] ?? 0);

        return $galleryId > 0 ? $this->galleries->find($galleryId) : null;
    }

    private function contactId(): int
    {
        return (int) ($this->auth->user()['contact_id'] ?? 0);
    }

    /** @return list<int> Gruppen, die die aktuelle Person leitet. */
    private function leadGroupIds(): array
    {
        if ($this->leadGroupIdsCache === null) {
            $cid = $this->contactId();
            $this->leadGroupIdsCache = $cid > 0 ? $this->groups->leadGroupIds($cid) : [];
        }

        return $this->leadGroupIdsCache;
    }

    /** @return list<int> Gruppen, in denen die aktuelle Person Mitglied ist (Leitung eingeschlossen). */
    private function memberGroupIds(): array
    {
        if ($this->memberGroupIdsCache === null) {
            $cid = $this->contactId();
            $this->memberGroupIdsCache = $cid > 0
                ? array_map(static fn (array $g): int => (int) $g['id'], $this->groups->forContact($cid))
                : [];
        }

        return $this->memberGroupIdsCache;
    }

    private function isLeadOfGroup(int $groupId): bool
    {
        return in_array($groupId, $this->leadGroupIds(), true);
    }

    private function isMemberOfGroup(int $groupId): bool
    {
        return in_array($groupId, $this->memberGroupIds(), true);
    }

    /**
     * Gruppen, die als Galerie-Ziel wählbar sind: bei globalem Verwalten alle,
     * sonst nur die eigenen geleiteten Gruppen.
     *
     * @return list<array<string,mixed>>
     */
    private function groupChoicesForCreate(): array
    {
        if ($this->canManage()) {
            return $this->groups->all();
        }
        $leadIds = $this->leadGroupIds();

        return array_values(array_filter(
            $this->groups->all(),
            static fn (array $g): bool => in_array((int) $g['id'], $leadIds, true)
        ));
    }

    /**
     * Gruppen-Felder zu den sanitierten Basisdaten ergänzen – je nachdem, ob
     * global verwaltet wird oder nur eine Gruppenleitung anlegt/bearbeitet.
     *
     * @param array<string,mixed>      $data
     * @param array<string,mixed>|null $existing null = Neuanlage
     * @return array<string,mixed>
     */
    private function sanitizeGroups(array $data, Request $request, ?array $existing): array
    {
        $requestedVisible = (int) $request->input('visible_group_id') ?: null;

        if ($this->canManage()) {
            // Globale Verwaltung: jede Gruppe als Sichtbarkeit wählbar, und
            // darf die Eigentümer-Gruppe (owner_group_id) frei setzen/ändern/
            // aufheben – z. B. um eine Galerie an eine andere Gruppenleitung
            // zu übergeben. Eine Gruppenleitung selbst kann das nicht (siehe
            // unten) – die legt owner_group_id nur einmalig bei Neuanlage fest.
            $allGroupIds = array_map(static fn (array $g): int => (int) $g['id'], $this->groups->all());
            $requestedOwner = (int) $request->input('owner_group_id') ?: null;
            $data['visible_group_id'] = $requestedVisible !== null && in_array($requestedVisible, $allGroupIds, true)
                ? $requestedVisible
                : null;
            $data['owner_group_id'] = $requestedOwner !== null && in_array($requestedOwner, $allGroupIds, true)
                ? $requestedOwner
                : null;

            return $data;
        }

        // Gruppenleitung ohne globales Recht: owner_group_id ist bei Neuanlage
        // eine der eigenen geleiteten Gruppen (fix danach), Sichtbarkeit ist
        // entweder „alle" oder genau diese eine Gruppe – nichts anderes.
        $ownerGroupId = $existing !== null
            ? ((int) ($existing['owner_group_id'] ?? 0) ?: null)
            : ((int) $request->input('owner_group_id') ?: null);
        if ($ownerGroupId === null || !$this->isLeadOfGroup($ownerGroupId)) {
            $ownerGroupId = $this->leadGroupIds()[0] ?? null;
        }

        $data['owner_group_id'] = $ownerGroupId;
        $data['visible_group_id'] = ($ownerGroupId !== null && $requestedVisible === $ownerGroupId) ? $ownerGroupId : null;

        return $data;
    }

    /**
     * Einmalig anzuzeigenden frischen Upload-Link aus der Session holen –
     * nur wenn er zur gerade gezeigten Seite gehört (`null` = Übersicht).
     *
     * @return array<string,mixed>|null
     */
    private function takeFreshLink(?int $galleryId): ?array
    {
        $fresh = $_SESSION['fresh_upload_link'] ?? null;
        if (!is_array($fresh) || ($fresh['for_gallery'] ?? null) !== $galleryId) {
            return null;
        }
        unset($_SESSION['fresh_upload_link']);

        return $fresh;
    }

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

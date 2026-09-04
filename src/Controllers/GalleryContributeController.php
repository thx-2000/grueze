<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\GalleryMediaRepository;
use App\Repositories\GalleryUploadLinkRepository;
use App\Services\MediaService;
use App\Support\JsonResponse;
use RuntimeException;

/**
 * Öffentliche „Beisteuern"-Seite: Wer den Weitergabe-Link (Token) hat, kann
 * ohne Login Fotos/Videos in eine Galerie oder den Auffangraum hochladen.
 * Kein Ansehen, kein Löschen – nur beitragen.
 */
final class GalleryContributeController extends BaseController
{
    private const SESSION_CAP = 150;

    public function __construct(
        \App\Core\Auth $auth,
        private GalleryUploadLinkRepository $links,
        private GalleryMediaRepository $media,
        private MediaService $storage,
    ) {
        parent::__construct($auth);
    }

    public function form(Request $request, string $token = ''): void
    {
        $link = $this->links->findValidByToken($token);
        if ($link === null) {
            render_error_page(410, 'Link nicht mehr gültig', 'Dieser Upload-Link ist abgelaufen, wurde zurückgezogen oder ist voll. Bitte an die Orga wenden.');

            return;
        }

        $maxImage = (int) config('media.max_image_bytes', 25165824);
        $maxVideo = (int) config('media.max_video_bytes', 524288000);

        $this->render('galleries/contribute', [
            'token' => $token,
            'target' => $link['gallery_id'] !== null ? (string) $link['gallery_title'] : null,
            'usageNotice' => gallery_usage_notice(),
            'maxImage' => $maxImage,
            'maxVideo' => $maxVideo,
            'remaining' => $link['max_uploads'] !== null
                ? max(0, (int) $link['max_uploads'] - (int) $link['upload_count'])
                : null,
        ]);
    }

    public function upload(Request $request, string $token = ''): void
    {
        ob_start();
        Csrf::validate($request->input('_csrf'));

        $link = $this->links->findValidByToken($token);
        if ($link === null) {
            JsonResponse::send(['ok' => false, 'error' => 'Der Upload-Link ist nicht mehr gültig.'], 410);
        }

        $key = 'contribute_' . (int) $link['id'];
        $used = (int) ($_SESSION[$key] ?? 0);
        if ($used >= self::SESSION_CAP) {
            JsonResponse::send(['ok' => false, 'error' => 'Für diese Sitzung ist genug – danke! Bitte später weitermachen.'], 429);
        }

        $file = $request->file('file');
        if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            JsonResponse::send(['ok' => false, 'error' => 'Keine Datei empfangen (evtl. zu groß fürs Server-Limit).'], 400);
        }
        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            JsonResponse::send(['ok' => false, 'error' => 'Der Upload ist fehlgeschlagen.'], 400);
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

        $this->media->add(
            $link['gallery_id'] !== null ? (int) $link['gallery_id'] : null,
            $meta,
            null,
            true
        );
        $this->links->noteUpload((int) $link['id']);
        $_SESSION[$key] = $used + 1;

        JsonResponse::send(['ok' => true, 'name' => $meta['original_name'], 'kind' => $meta['kind']]);
    }

    // ------------------------------------------------- Chunked Upload (Video)

    /** Wie GalleryController::chunkStart(), nur token- statt login-geprüft. */
    public function chunkStart(Request $request, string $token = ''): void
    {
        ob_start();
        Csrf::validate($request->input('_csrf'));

        $link = $this->links->findValidByToken($token);
        if ($link === null) {
            JsonResponse::send(['ok' => false, 'error' => 'Der Upload-Link ist nicht mehr gültig.'], 410);
        }

        $key = 'contribute_' . (int) $link['id'];
        if ((int) ($_SESSION[$key] ?? 0) >= self::SESSION_CAP) {
            JsonResponse::send(['ok' => false, 'error' => 'Für diese Sitzung ist genug – danke! Bitte später weitermachen.'], 429);
        }

        $filename = trim((string) $request->input('filename'));
        $totalSize = (int) $request->input('total_size');
        $totalChunks = (int) $request->input('total_chunks');
        if ($filename === '' || $totalSize <= 0 || $totalChunks <= 0 || $totalChunks > 100000) {
            JsonResponse::send(['ok' => false, 'error' => 'Ungültige Angaben.'], 400);
        }

        $maxAllowed = max((int) config('media.max_image_bytes', 25165824), (int) config('media.max_video_bytes', 524288000));
        if ($totalSize > $maxAllowed) {
            JsonResponse::send(['ok' => false, 'error' => 'Die Datei ist zu groß (max. ' . MediaService::humanBytes($maxAllowed) . ').'], 422);
        }

        $sessionId = $this->storage->startChunkSession(['link_id' => (int) $link['id']], $filename, $totalSize, $totalChunks);
        JsonResponse::send(['ok' => true, 'session_id' => $sessionId]);
    }

    public function chunkPart(Request $request, string $token = ''): void
    {
        ob_start();
        Csrf::validate($request->input('_csrf'));

        [, $error] = $this->chunkSessionGuard($request, $token);
        if ($error !== null) {
            JsonResponse::send(['ok' => false, 'error' => $error[0]], $error[1]);
        }

        $chunk = $request->file('chunk');
        if (!$chunk || (int) ($chunk['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($chunk['tmp_name'] ?? ''))) {
            JsonResponse::send(['ok' => false, 'error' => 'Ungültiges Datei-Stück.'], 400);
        }

        try {
            $this->storage->writeChunkPart((string) $request->input('session_id'), (int) $request->input('index'), (string) $chunk['tmp_name']);
        } catch (RuntimeException $e) {
            JsonResponse::send(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        JsonResponse::send(['ok' => true]);
    }

    public function chunkFinish(Request $request, string $token = ''): void
    {
        ob_start();
        Csrf::validate($request->input('_csrf'));

        $sessionId = (string) $request->input('session_id');
        [$meta, $error] = $this->chunkSessionGuard($request, $token);
        if ($error !== null) {
            JsonResponse::send(['ok' => false, 'error' => $error[0]], $error[1]);
        }

        $link = $this->links->findValidByToken($token);
        $key = 'contribute_' . (int) $link['id'];
        $used = (int) ($_SESSION[$key] ?? 0);
        if ($used >= self::SESSION_CAP) {
            $this->storage->discardChunkSession($sessionId);
            JsonResponse::send(['ok' => false, 'error' => 'Für diese Sitzung ist genug – danke! Bitte später weitermachen.'], 429);
        }

        $totalChunks = (int) $meta['total_chunks'];
        if (!$this->storage->chunkSessionComplete($sessionId, $totalChunks)) {
            JsonResponse::send(['ok' => false, 'error' => 'Es fehlen noch Teile der Datei.'], 422);
        }

        $posterTmp = null;
        $poster = $request->file('poster');
        if ($poster && (int) ($poster['error'] ?? 1) === UPLOAD_ERR_OK && is_uploaded_file((string) $poster['tmp_name'])) {
            $posterTmp = (string) $poster['tmp_name'];
        }

        try {
            $assembled = $this->storage->assembleChunkSession($sessionId, $totalChunks);
            $ingested = $this->storage->ingest($assembled, (string) $meta['original_name'], (int) (@filesize($assembled) ?: $meta['total_size']), $posterTmp, false);
        } catch (RuntimeException $e) {
            $this->storage->discardChunkSession($sessionId);
            JsonResponse::send(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        $this->storage->discardChunkSession($sessionId);

        $this->media->add($link['gallery_id'] !== null ? (int) $link['gallery_id'] : null, $ingested, null, true);
        $this->links->noteUpload((int) $link['id']);
        $_SESSION[$key] = $used + 1;

        JsonResponse::send(['ok' => true, 'name' => $ingested['original_name'], 'kind' => $ingested['kind']]);
    }

    /**
     * Sitzung laden + Token/Zugehörigkeit prüfen – gemeinsame Vorprüfung für
     * chunkPart()/chunkFinish(). Anders als beim eingeloggten Upload gibt es
     * keine Person, die man prüfen könnte – stattdessen muss die Sitzung zum
     * (weiterhin gültigen) Link gehören, dessen Token gerade vorgezeigt wird.
     *
     * @return array{0: array<string,mixed>, 1: array{0:string,1:int}|null} [meta, error]
     */
    private function chunkSessionGuard(Request $request, string $token): array
    {
        $link = $this->links->findValidByToken($token);
        if ($link === null) {
            return [[], ['Der Upload-Link ist nicht mehr gültig.', 410]];
        }
        $meta = $this->storage->chunkSessionMeta((string) $request->input('session_id'));
        if ($meta === null || (int) ($meta['link_id'] ?? 0) !== (int) $link['id']) {
            return [[], ['Unbekannte Upload-Sitzung.', 404]];
        }

        return [$meta, null];
    }
}

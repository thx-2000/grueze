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
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\AnnouncementRepository;
use App\Repositories\DocumentFolderRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\GroupRepository;
use App\Repositories\LogRepository;
use App\Services\DocumentStorageService;
use App\Support\FileResponse;
use App\Support\Redirect;
use RuntimeException;

/**
 * Dokumente: Ordner mit Dateien (PDF, Word, Excel, …) fürs Orga-Team und
 * für Gruppenleitung.
 *
 * Drei globale Rechte:
 *  - documents.view    ansehen + herunterladen
 *  - documents.upload  Dateien hochladen (und eigene Uploads bearbeiten/löschen)
 *  - documents.manage  Ordner anlegen/bearbeiten/löschen, fremde Dateien
 *                      bearbeiten/löschen – alles global
 *
 * Zusätzlich, unabhängig von den globalen Rechten: Gruppenleitung darf für
 * die eigene Gruppe einen Ordner anlegen (`owner_group_id`) und verwalten,
 * auch ohne `documents.manage`. Jeder Ordner kann seine Sichtbarkeit auf eine
 * Gruppe einschränken (`visible_group_id`, NULL = normale globale Rechte).
 * Admin sieht/verwaltet über `documents.manage` ohnehin immer alles.
 *
 * Anders als bei Galerien gibt es keinen Papierkorb (wie bei Gruppen) –
 * Löschen ist hier bewusst endgültig.
 */
final class DocumentController extends BaseController
{
    private ?array $leadGroupIdsCache = null;
    private ?array $memberGroupIdsCache = null;

    public function __construct(
        \App\Core\Auth $auth,
        private DocumentFolderRepository $folders,
        private DocumentRepository $documents,
        private DocumentStorageService $storage,
        private LogRepository $logs,
        private GroupRepository $groups,
        private AnnouncementRepository $announcements,
    ) {
        parent::__construct($auth);
    }

    // ------------------------------------------------------------- Übersicht

    public function index(): void
    {
        $this->requireFolderAccess();

        $visible = array_values(array_filter(
            $this->folders->topLevel(),
            fn (array $f): bool => $this->canViewFolder($f)
        ));

        $this->render('documents/index', [
            'folders' => $visible,
            'canCreate' => $this->canCreateFolder(),
            'canManage' => $this->canManage(),
        ]);
    }

    public function createForm(Request $request): void
    {
        $parentId = (int) $request->input('parent_id') ?: null;
        $parent = null;
        if ($parentId !== null) {
            $parent = $this->folders->find($parentId);
            if ($parent === null || !$this->canManageFolder($parent)) {
                throw new RuntimeException('Zum Anlegen eines Unterordners fehlt die Berechtigung.');
            }
        } else {
            $this->requireCreate();
        }

        $this->render('documents/form', [
            'folder' => null,
            'parent' => $parent,
            'announcements' => $this->announcements->all(),
            'groupChoices' => $this->groupChoicesForCreate(),
            'canPickGroup' => $this->canManage(),
        ]);
    }

    public function store(Request $request): void
    {
        $parentId = (int) $request->input('parent_id') ?: null;
        $parent = null;
        if ($parentId !== null) {
            $parent = $this->folders->find($parentId);
            if ($parent === null || !$this->canManageFolder($parent)) {
                throw new RuntimeException('Zum Anlegen eines Unterordners fehlt die Berechtigung.');
            }
        } else {
            $this->requireCreate();
        }
        Csrf::validate($request->input('_csrf'));

        $data = $this->sanitizeGroups($this->sanitize($request), $request, null);
        $data['parent_id'] = $parentId;
        if ($data['title'] === '') {
            flash('error', 'Bitte einen Titel angeben.');
            Redirect::to('/dokumente/neu' . ($parentId !== null ? '?parent_id=' . $parentId : ''));
        }

        $id = $this->folders->create($data, $this->userId());
        $this->logs->addAudit((int) $this->userId(), null, 'created', 'Dokumente-Ordner angelegt: „' . $data['title'] . '".'
            . ($parent !== null ? ' (Unterordner von „' . $parent['title'] . '")' : ''));
        flash('success', 'Ordner angelegt. Jetzt Dateien hochladen.');
        Redirect::to('/dokumente/ansehen?id=' . $id);
    }

    // ------------------------------------------------------------ ein Ordner

    public function show(Request $request): void
    {
        $this->requireAuth();
        $folder = $this->folders->find((int) $request->input('id'));
        if ($folder === null || !$this->canViewFolder($folder)) {
            flash('error', 'Ordner nicht gefunden.');
            Redirect::to('/dokumente');
        }

        $canManageThis = $this->canManageFolder($folder);
        $subfolders = array_values(array_filter(
            $this->folders->childrenOf((int) $folder['id']),
            fn (array $f): bool => $this->canViewFolder($f)
        ));

        $sort = (string) $request->input('sort', 'title');
        $search = trim((string) $request->input('q', ''));

        $this->render('documents/show', [
            'folder' => $folder,
            'breadcrumb' => $this->folders->ancestors((int) $folder['id']),
            'subfolders' => $subfolders,
            'canCreateSubfolder' => $canManageThis,
            'documents' => $this->documents->forFolder((int) $folder['id'], $sort, $search),
            'sort' => $sort,
            'search' => $search,
            'canManage' => $canManageThis,
            'canUpload' => $this->canUploadToFolder($folder),
            'currentUserId' => (int) $this->userId(),
            'announcements' => $this->announcements->all(),
            'groupChoices' => $this->groupChoicesForCreate(),
            'canPickGroup' => $this->canManage(),
            'maxBytes' => $this->storage->maxBytes(),
            'allowedExtensions' => array_keys($this->storage->allowedExtensions()),
        ]);
    }

    public function update(Request $request): void
    {
        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));

        $folder = $this->folders->find((int) $request->input('id'));
        if ($folder === null || !$this->canManageFolder($folder)) {
            Redirect::to('/dokumente');
        }

        $data = $this->sanitizeGroups($this->sanitize($request), $request, $folder);
        if ($data['title'] === '') {
            flash('error', 'Bitte einen Titel angeben.');
            Redirect::to('/dokumente/ansehen?id=' . $folder['id']);
        }

        $this->folders->update((int) $folder['id'], $data);
        flash('success', 'Ordner gespeichert.');
        Redirect::to('/dokumente/ansehen?id=' . $folder['id']);
    }

    public function deleteFolder(Request $request): void
    {
        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));

        $folder = $this->folders->find((int) $request->input('id'));
        if ($folder !== null && !$this->canManageFolder($folder)) {
            throw new RuntimeException('Zum Löschen dieses Ordners fehlt die Berechtigung.');
        }
        if ($folder !== null && $this->folders->hasChildren((int) $folder['id'])) {
            flash('error', 'Dieser Ordner hat noch Unterordner – erst die entfernen.');
            Redirect::to('/dokumente/ansehen?id=' . $folder['id']);
        }
        if ($folder !== null) {
            foreach ($this->documents->forFolder((int) $folder['id']) as $doc) {
                $this->storage->deleteFile($doc);
            }
            $this->documents->deleteAllInFolder((int) $folder['id']);
            $parentId = $folder['parent_id'] !== null ? (int) $folder['parent_id'] : null;
            $this->folders->delete((int) $folder['id']);
            $this->logs->addAudit((int) $this->userId(), null, 'deleted', 'Dokumente-Ordner endgültig gelöscht: „' . $folder['title'] . '" (inkl. aller Dateien).');
            flash('success', '„' . $folder['title'] . '" wurde endgültig gelöscht.');
            Redirect::to($parentId !== null ? '/dokumente/ansehen?id=' . $parentId : '/dokumente');
        }
        Redirect::to('/dokumente');
    }

    // ------------------------------------------------------------- Dateien

    public function upload(Request $request): void
    {
        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));

        $folder = $this->folders->find((int) $request->input('folder_id'));
        if ($folder === null) {
            flash('error', 'Ordner nicht gefunden.');
            Redirect::to('/dokumente');
        }
        if (!$this->canUploadToFolder($folder)) {
            throw new RuntimeException('Zum Hochladen fehlt die Berechtigung.');
        }

        $file = $request->file('file');
        if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            flash('error', 'Keine Datei ausgewählt (evtl. zu groß fürs Server-Limit).');
            Redirect::to('/dokumente/ansehen?id=' . $folder['id']);
        }
        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            flash('error', $this->uploadErrorText((int) $file['error']));
            Redirect::to('/dokumente/ansehen?id=' . $folder['id']);
        }
        if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            flash('error', 'Ungültiger Upload.');
            Redirect::to('/dokumente/ansehen?id=' . $folder['id']);
        }

        try {
            $meta = $this->storage->ingest((string) $file['tmp_name'], (string) ($file['name'] ?? 'datei'), (int) ($file['size'] ?? 0));
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            Redirect::to('/dokumente/ansehen?id=' . $folder['id']);
        }

        $title = trim((string) $request->input('title'));
        if ($title === '') {
            $title = pathinfo((string) $meta['original_name'], PATHINFO_FILENAME);
        }

        $this->documents->add((int) $folder['id'], [
            'title' => mb_substr($title, 0, 190),
            'description' => mb_substr(trim((string) $request->input('description')), 0, 5000),
            'original_name' => $meta['original_name'],
            'stored_path' => $meta['stored_path'],
            'preview_path' => $meta['preview_path'] ?? null,
            'mime' => $meta['mime'],
            'byte_size' => $meta['byte_size'],
        ], $this->userId());

        $this->logs->addAudit((int) $this->userId(), null, 'created', 'Datei hochgeladen in „' . $folder['title'] . '": ' . $meta['original_name']);
        flash('success', 'Datei hochgeladen.');
        Redirect::to('/dokumente/ansehen?id=' . $folder['id']);
    }

    public function documentUpdate(Request $request): void
    {
        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));

        $doc = $this->documents->find((int) $request->input('id'));
        if ($doc === null) {
            flash('error', 'Datei nicht gefunden.');
            Redirect::to('/dokumente');
        }
        $folder = $this->folders->find((int) $doc['folder_id']);
        $this->requireDocumentEdit($doc, $folder);

        $title = mb_substr(trim((string) $request->input('title')), 0, 190);
        if ($title === '') {
            flash('error', 'Bitte einen Titel angeben.');
            Redirect::to('/dokumente/ansehen?id=' . (int) $doc['folder_id']);
        }

        $this->documents->updateDetails((int) $doc['id'], $title, mb_substr(trim((string) $request->input('description')), 0, 5000));
        flash('success', 'Gespeichert.');
        Redirect::to('/dokumente/ansehen?id=' . (int) $doc['folder_id']);
    }

    public function documentDelete(Request $request): void
    {
        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));

        $doc = $this->documents->find((int) $request->input('id'));
        if ($doc === null) {
            flash('error', 'Datei nicht gefunden.');
            Redirect::to('/dokumente');
        }
        $folder = $this->folders->find((int) $doc['folder_id']);
        $this->requireDocumentEdit($doc, $folder);

        $this->storage->deleteFile($doc);
        $this->documents->delete((int) $doc['id']);
        flash('success', 'Datei gelöscht.');
        Redirect::to('/dokumente/ansehen?id=' . (int) $doc['folder_id']);
    }

    public function file(Request $request): void
    {
        $this->requireAuth();

        $doc = $this->documents->find((int) $request->input('id'));
        if ($doc === null) {
            http_response_code(404);
            exit;
        }
        $folder = $this->folders->find((int) $doc['folder_id']);
        if ($folder === null || !$this->canViewFolder($folder)) {
            http_response_code(404);
            exit;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $wantDownload = $request->input('dl') === '1';
        $previewRel = trim((string) ($doc['preview_path'] ?? ''));
        // Vorschau (PDF, aus Office-Formaten erzeugt) nur beim Ansehen nutzen –
        // heruntergeladen wird immer das Original.
        $usePreview = !$wantDownload && $previewRel !== '' && $request->input('v') !== 'original';

        $abs = $this->storage->absolutePath($usePreview ? $previewRel : (string) $doc['stored_path']);
        if ($abs === null) {
            http_response_code(404);
            exit;
        }

        $mime = $usePreview ? 'application/pdf' : (string) $doc['mime'];
        $downloadName = $wantDownload ? (string) ($doc['original_name'] ?? 'datei') : null;
        FileResponse::stream($abs, $mime, $downloadName, $downloadName === null ? 3600 : 0);
    }

    // --------------------------------------------------------------- intern

    private function userId(): ?int
    {
        return (int) ($this->auth->user()['id'] ?? 0) ?: null;
    }

    private function canView(): bool
    {
        return can_any('documents.view', 'documents.upload', 'documents.manage');
    }

    private function canUpload(): bool
    {
        return can_any('documents.upload', 'documents.manage');
    }

    private function canManage(): bool
    {
        return $this->auth->can('documents.manage');
    }

    /** Betreten der Dokumente-Übersicht: globale Rechte ODER eigene Gruppen-Ordner. */
    private function requireFolderAccess(): void
    {
        $this->requireAuth();
        if (!$this->canView() && $this->leadGroupIds() === [] && $this->memberGroupIds() === []) {
            throw new RuntimeException('Für den Dokumente-Bereich fehlt die Berechtigung.');
        }
    }

    private function requireCreate(): void
    {
        $this->requireAuth();
        if (!$this->canCreateFolder()) {
            throw new RuntimeException('Zum Anlegen eines Ordners fehlt die Berechtigung.');
        }
    }

    /** Globales Verwalten ODER Gruppenleitung mit mindestens einer Gruppe. */
    private function canCreateFolder(): bool
    {
        return $this->canManage() || $this->leadGroupIds() !== [];
    }

    /** Darf dieser eine Ordner angesehen (und heruntergeladen) werden? */
    private function canViewFolder(array $folder): bool
    {
        if ($this->canManage()) {
            return true;
        }
        $groupId = (int) ($folder['visible_group_id'] ?? 0) ?: null;

        return $groupId === null ? $this->canView() : $this->isMemberOfGroup($groupId);
    }

    /** Darf dieser eine Ordner verwaltet werden (bearbeiten/löschen)? */
    private function canManageFolder(array $folder): bool
    {
        if ($this->canManage()) {
            return true;
        }
        $ownerGroupId = (int) ($folder['owner_group_id'] ?? 0) ?: null;

        return $ownerGroupId !== null && $this->isLeadOfGroup($ownerGroupId);
    }

    /** Darf in diesen einen Ordner hochgeladen werden? */
    private function canUploadToFolder(array $folder): bool
    {
        if ($this->canManageFolder($folder)) {
            return true;
        }
        $groupId = (int) ($folder['visible_group_id'] ?? 0) ?: null;

        return $groupId === null ? $this->canUpload() : ($this->isMemberOfGroup($groupId) && $this->canUpload());
    }

    /** Darf die aktuelle Person diese eine Datei bearbeiten/löschen? */
    private function requireDocumentEdit(array $doc, ?array $folder): void
    {
        if ($folder !== null && $this->canManageFolder($folder)) {
            return;
        }
        $uid = $this->userId();
        if ($folder !== null && $uid !== null && (int) ($doc['uploaded_by'] ?? 0) === $uid && $this->canUploadToFolder($folder)) {
            return;
        }
        throw new RuntimeException('Nur eigene Uploads oder mit Verwalten-Recht.');
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
     * Gruppen, die als Ordner-Ziel wählbar sind: bei globalem Verwalten alle,
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
            $allGroupIds = array_map(static fn (array $g): int => (int) $g['id'], $this->groups->all());
            $data['visible_group_id'] = $requestedVisible !== null && in_array($requestedVisible, $allGroupIds, true)
                ? $requestedVisible
                : null;
            $data['owner_group_id'] = $existing['owner_group_id'] ?? null;

            return $data;
        }

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

    /** @return array<string,mixed> */
    private function sanitize(Request $request): array
    {
        return [
            'title' => mb_substr(trim((string) $request->input('title')), 0, 190),
            'description' => mb_substr(trim((string) $request->input('description')), 0, 5000),
            'announcement_id' => (int) $request->input('announcement_id') ?: null,
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

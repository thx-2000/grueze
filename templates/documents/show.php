<?php
/**
 * @var array<string,mixed> $folder
 * @var list<array<string,mixed>> $breadcrumb
 * @var list<array<string,mixed>> $subfolders
 * @var bool $canCreateSubfolder
 * @var list<array<string,mixed>> $documents
 * @var string $sort
 * @var string $search
 * @var bool $canManage
 * @var bool $canUpload
 * @var int $currentUserId
 * @var list<array<string,mixed>> $announcements
 * @var list<array<string,mixed>> $groupChoices
 * @var bool $canPickGroup
 * @var int $maxBytes
 * @var list<string> $allowedExtensions
 */
$f = $folder;
$sortLabels = ['title' => 'Name', 'newest' => 'Neueste zuerst', 'oldest' => 'Älteste zuerst', 'largest' => 'Größte zuerst'];
?>
<header class="contact-detail-head gallery-head">
    <div>
        <p class="eyebrow">
            <a href="<?= e(url('/dokumente')) ?>">Dokumente</a>
            <?php foreach ($breadcrumb as $crumb): ?> · <a href="<?= e(url('/dokumente/ansehen?id=' . (int) $crumb['id'])) ?>"><?= e((string) $crumb['title']) ?></a><?php endforeach; ?>
        </p>
        <h1><?= e($f['title']) ?></h1>
        <p class="muted">
            <?= count($documents) ?> <?= count($documents) === 1 ? 'Datei' : 'Dateien' ?>
            <?php if (trim((string) ($f['owner_group_name'] ?? '')) !== ''): ?>
                · <span class="gallery-card-event"><?= icon('users') ?>Gruppe „<?= e($f['owner_group_name']) ?>"</span>
            <?php endif; ?>
            <?php if (trim((string) ($f['visible_group_name'] ?? '')) !== ''): ?>
                · <span class="gallery-card-event"><?= icon('eye') ?>nur für „<?= e($f['visible_group_name']) ?>" sichtbar</span>
            <?php endif; ?>
            <?php if (trim((string) ($f['announcement_title'] ?? '')) !== ''): ?>
                · <a href="<?= e(url('/termine/detail?id=' . (int) $f['announcement_id'])) ?>"><?= icon('calendar') ?><?= e($f['announcement_title']) ?></a>
            <?php endif; ?>
        </p>
        <?php if (trim((string) ($f['description'] ?? '')) !== ''): ?>
            <p class="gallery-description"><?= nl2br(e($f['description'])) ?></p>
        <?php endif; ?>
    </div>
    <?php if ($canManage): ?>
        <div class="toolbar-actions">
            <form method="post" action="<?= e(url('/dokumente/loeschen')) ?>" data-confirm="Ordner „<?= e($f['title']) ?>“ mit allen <?= count($documents) ?> Dateien endgültig löschen? Das kann nicht rückgängig gemacht werden.">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" value="<?= e((string) $f['id']) ?>">
                <button type="submit" class="danger-button"><?= icon('trash') ?><span>Ordner löschen</span></button>
            </form>
        </div>
    <?php endif; ?>
</header>

<?php if ($canManage): ?>
<details class="panel gallery-settings">
    <summary>Ordner-Details bearbeiten</summary>
    <form method="post" action="<?= e(url('/dokumente/speichern')) ?>" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="id" value="<?= e((string) $f['id']) ?>">
        <label>
            <span>Titel <span aria-hidden="true">*</span></span>
            <input type="text" name="title" required maxlength="190" value="<?= e((string) $f['title']) ?>">
        </label>
        <label>
            <span>Beschreibung</span>
            <textarea name="description" rows="3" maxlength="5000"><?= e((string) ($f['description'] ?? '')) ?></textarea>
        </label>
        <label>
            <span>Zu einer Ankündigung (optional)</span>
            <select name="announcement_id">
                <option value="">— keine Ankündigung —</option>
                <?php foreach ($announcements as $announcement): ?>
                    <option value="<?= e((string) $announcement['id']) ?>" <?= (int) ($f['announcement_id'] ?? 0) === (int) $announcement['id'] ? 'selected' : '' ?>><?= e($announcement['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php view_partial('documents/_visibility-fields', ['folder' => $f, 'groupChoices' => $groupChoices, 'canPickGroup' => $canPickGroup]); ?>
        <div class="form-actions">
            <button type="submit" class="button-link"><?= icon('check') ?><span>Speichern</span></button>
        </div>
    </form>
</details>
<?php endif; ?>

<?php if ($canUpload): ?>
<section class="panel document-upload">
    <h3>Datei hochladen</h3>
    <p class="muted">PDF, Word, Excel, PowerPoint, ODF, Text, ZIP oder Bild – bis <?= e(\App\Services\MediaService::humanBytes($maxBytes)) ?>.</p>
    <form method="post" action="<?= e(url('/dokumente/hochladen')) ?>" enctype="multipart/form-data" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="folder_id" value="<?= e((string) $f['id']) ?>">
        <label>
            <span>Datei <span aria-hidden="true">*</span></span>
            <input type="file" name="file" required accept=".<?= e(implode(',.', $allowedExtensions)) ?>">
        </label>
        <label>
            <span>Titel (optional – sonst der Dateiname)</span>
            <input type="text" name="title" maxlength="190">
        </label>
        <label>
            <span>Beschreibung (optional)</span>
            <textarea name="description" rows="2" maxlength="5000"></textarea>
        </label>
        <div class="form-actions">
            <button type="submit" class="button-link"><?= icon('upload') ?><span>Hochladen</span></button>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="panel">
    <div class="panel-head">
        <h3>Unterordner<?= $subfolders !== [] ? ' (' . count($subfolders) . ')' : '' ?></h3>
        <?php if ($canCreateSubfolder): ?>
            <a class="ghost-button compact-action" href="<?= e(url('/dokumente/neu?parent_id=' . (int) $f['id'])) ?>"><?= icon('plus') ?><span>Neuer Unterordner</span></a>
        <?php endif; ?>
    </div>
    <?php if ($subfolders === []): ?>
        <p class="muted">Keine Unterordner.</p>
    <?php else: ?>
        <div class="gallery-grid document-folder-grid">
            <?php foreach ($subfolders as $sub): ?>
                <a class="gallery-card document-folder-card" href="<?= e(url('/dokumente/ansehen?id=' . (int) $sub['id'])) ?>">
                    <span class="gallery-card-cover document-folder-cover"><?= icon('folder') ?></span>
                    <span class="gallery-card-body">
                        <strong><?= e((string) $sub['title']) ?></strong>
                        <span class="muted"><?= (int) $sub['document_count'] === 1 ? '1 Datei' : (int) $sub['document_count'] . ' Dateien' ?><?= (int) $sub['subfolder_count'] > 0 ? ' · ' . (int) $sub['subfolder_count'] . ' Unterordner' : '' ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <?php if ($documents === [] && $search === ''): ?>
        <p class="muted">Noch keine Dateien in diesem Ordner.</p>
    <?php else: ?>
        <form method="get" action="<?= e(url('/dokumente/ansehen')) ?>" class="document-toolbar">
            <input type="hidden" name="id" value="<?= e((string) $f['id']) ?>">
            <label class="visually-hidden" for="docSearch">Dateien durchsuchen</label>
            <input type="search" id="docSearch" name="q" value="<?= e($search) ?>" placeholder="Dateien durchsuchen …">
            <label class="visually-hidden" for="docSort">Sortierung</label>
            <select id="docSort" name="sort">
                <?php foreach ($sortLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="ghost-button compact-action"><?= icon('search') ?><span>Anwenden</span></button>
        </form>
        <?php if ($documents === []): ?>
            <p class="muted">Keine Datei passt zu „<?= e($search) ?>".</p>
        <?php else: ?>
        <ul class="document-list">
            <?php foreach ($documents as $doc): ?>
                <?php
                $ext = strtoupper((string) pathinfo((string) ($doc['original_name'] ?? ''), PATHINFO_EXTENSION));
                $mayEdit = $canManage || ((int) ($doc['uploaded_by'] ?? 0) === $currentUserId);
                $viewUrl = url('/dokumente/datei?id=' . (int) $doc['id']);
                $downloadUrl = url('/dokumente/datei?id=' . (int) $doc['id'] . '&dl=1');
                ?>
                <li class="document-row">
                    <span class="document-icon"><?= icon('file') ?><?php if ($ext !== ''): ?><span class="document-ext-badge"><?= e($ext) ?></span><?php endif; ?></span>
                    <div class="document-row-body">
                        <strong><?= e((string) $doc['title']) ?></strong>
                        <?php if (trim((string) ($doc['description'] ?? '')) !== ''): ?>
                            <span class="muted"><?= nl2br(e((string) $doc['description'])) ?></span>
                        <?php endif; ?>
                        <span class="document-meta">
                            <?= e((string) ($doc['original_name'] ?? '')) ?> ·
                            <?= e(\App\Services\MediaService::humanBytes((int) $doc['byte_size'])) ?> ·
                            hochgeladen <?= e(format_date(substr((string) $doc['created_at'], 0, 10))) ?>
                        </span>
                    </div>
                    <div class="document-row-actions">
                        <a class="ghost-button compact-action" href="<?= e($viewUrl) ?>" target="_blank" rel="noopener"><?= icon('eye') ?><span>Ansehen<?= trim((string) ($doc['preview_path'] ?? '')) !== '' ? ' (Vorschau)' : '' ?></span></a>
                        <a class="ghost-button compact-action" href="<?= e($downloadUrl) ?>"><?= icon('download') ?><span>Herunterladen</span></a>
                        <?php if ($mayEdit): ?>
                            <details class="document-edit">
                                <summary class="ghost-button compact-action"><?= icon('edit') ?><span>Bearbeiten</span></summary>
                                <div class="document-edit-body">
                                    <div class="copy-field">
                                        <label class="visually-hidden" for="docLink<?= (int) $doc['id'] ?>">Direktlink</label>
                                        <input type="text" id="docLink<?= (int) $doc['id'] ?>" value="<?= e($viewUrl) ?>" readonly spellcheck="false">
                                        <button type="button" class="ghost-button" data-copy="#docLink<?= (int) $doc['id'] ?>"><?= icon('copy') ?><span>Link kopieren</span></button>
                                    </div>
                                    <p class="field-hint">Der Link führt direkt zur Datei – ein berechtigtes Login ist trotzdem nötig.</p>
                                    <form method="post" action="<?= e(url('/dokumente/datei/speichern')) ?>" class="stack">
                                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $doc['id'] ?>">
                                        <label>
                                            <span>Titel</span>
                                            <input type="text" name="title" required maxlength="190" value="<?= e((string) $doc['title']) ?>">
                                        </label>
                                        <label>
                                            <span>Beschreibung</span>
                                            <textarea name="description" rows="2" maxlength="5000"><?= e((string) ($doc['description'] ?? '')) ?></textarea>
                                        </label>
                                        <div class="form-actions">
                                            <button type="submit" class="button-link"><?= icon('check') ?><span>Speichern</span></button>
                                        </div>
                                    </form>
                                    <form method="post" action="<?= e(url('/dokumente/datei/loeschen')) ?>" data-confirm="Datei „<?= e((string) $doc['title']) ?>“ endgültig löschen?">
                                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $doc['id'] ?>">
                                        <button type="submit" class="danger-button compact-action"><?= icon('trash') ?><span>Datei löschen</span></button>
                                    </form>
                                </div>
                            </details>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php endif; ?>
</section>

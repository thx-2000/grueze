<?php
/**
 * @var array<string,mixed> $folder
 * @var list<array<string,mixed>> $documents
 * @var bool $canManage
 * @var bool $canUpload
 * @var int $currentUserId
 * @var list<array<string,mixed>> $groupChoices
 * @var bool $canPickGroup
 * @var int $maxBytes
 * @var list<string> $allowedExtensions
 */
$f = $folder;
?>
<header class="contact-detail-head gallery-head">
    <div>
        <p class="eyebrow"><a href="<?= e(url('/dokumente')) ?>">Dokumente</a></p>
        <h1><?= e($f['title']) ?></h1>
        <p class="muted">
            <?= count($documents) ?> <?= count($documents) === 1 ? 'Datei' : 'Dateien' ?>
            <?php if (trim((string) ($f['owner_group_name'] ?? '')) !== ''): ?>
                · <span class="gallery-card-event"><?= icon('users') ?>Gruppe „<?= e($f['owner_group_name']) ?>"</span>
            <?php endif; ?>
            <?php if (trim((string) ($f['visible_group_name'] ?? '')) !== ''): ?>
                · <span class="gallery-card-event"><?= icon('eye') ?>nur für „<?= e($f['visible_group_name']) ?>" sichtbar</span>
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
    <?php if ($documents === []): ?>
        <p class="muted">Noch keine Dateien in diesem Ordner.</p>
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
                        <a class="ghost-button compact-action" href="<?= e($viewUrl) ?>" target="_blank" rel="noopener"><?= icon('eye') ?><span>Ansehen</span></a>
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
</section>

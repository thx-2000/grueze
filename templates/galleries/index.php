<?php
/**
 * @var list<array<string,mixed>> $galleries
 * @var int $trashedCount
 * @var array<string,mixed> $capabilities
 * @var bool $canManage
 * @var bool $canUpload
 * @var string $usageNotice
 * @var int $mediaBytes
 * @var int $backupMax
 */
?>
<header class="contacts-header">
    <div>
        <h1>Galerien</h1>
        <p class="muted">Foto- und Video-Sammlungen, zum Beispiel pro Stufentreffen.</p>
    </div>
    <?php if ($canManage): ?>
        <a class="button-link" href="<?= e(url('/galerien/neu')) ?>"><?= icon('plus') ?><span>Neue Galerie</span></a>
    <?php endif; ?>
</header>

<div class="gallery-notice" role="note">
    <?= icon('eye') ?>
    <p><?= e($usageNotice) ?></p>
</div>

<?php if ($canManage): ?>
    <details class="panel gallery-notice-edit">
        <summary>Hinweistext bearbeiten</summary>
        <form method="post" action="<?= e(url('/galerien/hinweis')) ?>" class="stack">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <label>
                <span>Dieser Text steht beim Ansehen, im Download und in jeder ZIP-Datei.</span>
                <textarea name="usage_notice" rows="3" maxlength="1000"><?= e($usageNotice) ?></textarea>
            </label>
            <div class="form-actions"><button type="submit" class="button-link"><?= icon('check') ?><span>Speichern</span></button></div>
        </form>
    </details>
<?php endif; ?>

<?php
$missing = [];
if (($canUpload || $canManage) && empty($capabilities['gd']) && empty($capabilities['imagick'])) {
    $missing[] = 'Ohne GD/ImageMagick werden keine Vorschaubilder erzeugt – die Originale werden direkt angezeigt (auf dem Handy langsamer).';
}
if (($canUpload || $canManage) && empty($capabilities['heic'])) {
    $missing[] = 'HEIC-Fotos (iPhone-Standard) können nicht umgewandelt werden – bitte als JPG hochladen.';
}
$uploadMax = (int) ($capabilities['upload_max_bytes'] ?? 0);
?>
<?php if ($missing !== []): ?>
    <div class="hub-notice" role="status">
        <span><?= icon('sliders') ?></span>
        <div>
            <strong>Medien-Voraussetzungen auf diesem Server:</strong>
            <ul class="tight-list">
                <?php foreach ($missing as $line): ?><li><?= e($line) ?></li><?php endforeach; ?>
            </ul>
            <?php if ($uploadMax > 0): ?>
                <p class="muted">Maximale Uploadgröße pro Datei laut Server: <strong><?= e(\App\Services\MediaService::humanBytes($uploadMax)) ?></strong>.</p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($galleries === []): ?>
    <section class="panel">
        <p class="muted">
            <?php if ($canManage): ?>
                Noch keine Galerie. <a href="<?= e(url('/galerien/neu')) ?>">Erste Galerie anlegen</a>.
            <?php else: ?>
                Es gibt noch keine Galerie.
            <?php endif; ?>
        </p>
    </section>
<?php else: ?>
    <div class="gallery-grid">
        <?php foreach ($galleries as $g): ?>
            <?php $count = (int) $g['media_count']; ?>
            <a class="gallery-card" href="<?= e(url('/galerien/ansehen?id=' . (int) $g['id'])) ?>">
                <span class="gallery-card-cover<?= $g['cover_path'] ? '' : ' is-empty' ?>">
                    <?php if ($g['cover_path']): ?>
                        <img loading="lazy" alt="" src="<?= e(url('/galerien/datei?id=' . (int) $g['cover_media_id'] . '&v=thumb')) ?>">
                    <?php else: ?>
                        <?= icon('image') ?>
                    <?php endif; ?>
                </span>
                <span class="gallery-card-body">
                    <strong><?= e($g['title']) ?></strong>
                    <span class="muted">
                        <?php if ($g['gallery_date']): ?><?= e(format_date(substr((string) $g['gallery_date'], 0, 10))) ?> · <?php endif; ?>
                        <?= $count === 1 ? '1 Medium' : e((string) $count) . ' Medien' ?>
                        <?php if ((int) $g['video_count'] > 0): ?> · <?= e((string) (int) $g['video_count']) ?> Video<?= (int) $g['video_count'] === 1 ? '' : 's' ?><?php endif; ?>
                    </span>
                    <?php if (trim((string) ($g['event_title'] ?? '')) !== ''): ?>
                        <span class="gallery-card-event"><?= icon('calendar') ?><?= e($g['event_title']) ?></span>
                    <?php endif; ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($canManage): ?>
    <section class="panel gallery-backup">
        <div class="panel-head"><div><h3>Sicherung der Medien</h3>
            <p class="muted">Alle Galerien und Dateien als ZIP – zusätzlich zur Sicherung beim Hoster. Die Galerie-Medien sind <strong>nicht</strong> im normalen Datensicherungs-Backup enthalten.</p>
        </div></div>
        <div class="toolbar-actions">
            <?php $over = $backupMax > 0 && $mediaBytes > $backupMax; ?>
            <a class="ghost-button<?= $over ? ' is-disabled' : '' ?>" href="<?= e(url('/galerien/sicherung')) ?>"<?= $over ? ' aria-disabled="true" tabindex="-1"' : '' ?>>
                <?= icon('download') ?><span>Alle Medien sichern<?= $mediaBytes > 0 ? ' (' . e(\App\Services\MediaService::humanBytes($mediaBytes)) . ')' : '' ?></span>
            </a>
        </div>
        <?php if ($over): ?>
            <p class="field-hint">Zu groß für eine Gesamt-Sicherung (Limit <?= e(\App\Services\MediaService::humanBytes($backupMax)) ?>). Bitte einzelne Galerien über „Als ZIP" sichern.</p>
        <?php endif; ?>
        <details class="gallery-restore">
            <summary>Sicherung einspielen</summary>
            <form method="post" action="<?= e(url('/galerien/sicherung')) ?>" enctype="multipart/form-data" class="stack" data-confirm="Sicherung jetzt einspielen? Die enthaltenen Galerien werden als NEUE Galerien angelegt (nichts wird überschrieben).">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <label>
                    <span>ZIP-Sicherung (aus „Alle Medien sichern")</span>
                    <input type="file" name="backup_file" accept=".zip,application/zip" required>
                </label>
                <div class="form-actions"><button type="submit" class="ghost-button"><?= icon('upload') ?><span>Einspielen</span></button></div>
            </form>
        </details>
    </section>

    <?php if ($trashedCount > 0): ?>
        <p class="gallery-trash-link">
            <a class="ghost-button compact-action" href="<?= e(url('/galerien/papierkorb')) ?>"><?= icon('trash') ?><span>Papierkorb (<?= (int) $trashedCount ?>)</span></a>
        </p>
    <?php endif; ?>
<?php endif; ?>

<?php
/**
 * @var list<array<string,mixed>> $galleries
 * @var int $trashedCount
 * @var array<string,mixed> $capabilities
 * @var bool $canManage
 * @var bool $canUpload
 * @var string $usageNotice
 * @var bool $canCreate
 * @var int $unassignedCount
 * @var list<array<string,mixed>> $catchAllLinks
 * @var array<string,mixed>|null $freshLink
 * @var int $linkDays
 */
?>
<header class="contacts-header">
    <div>
        <h1>Galerien</h1>
        <p class="muted">Foto- und Video-Sammlungen, zum Beispiel pro Stufentreffen.</p>
    </div>
    <?php if ($canCreate): ?>
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
            <?php if ($canCreate): ?>
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
                    <?php if (trim((string) ($g['announcement_title'] ?? '')) !== ''): ?>
                        <span class="gallery-card-event"><?= icon('calendar') ?><?= e($g['announcement_title']) ?></span>
                    <?php endif; ?>
                    <?php if (trim((string) ($g['visible_group_name'] ?? '')) !== ''): ?>
                        <span class="gallery-card-event"><?= icon('eye') ?>nur „<?= e($g['visible_group_name']) ?>"</span>
                    <?php elseif (trim((string) ($g['owner_group_name'] ?? '')) !== ''): ?>
                        <span class="gallery-card-event"><?= icon('users') ?><?= e($g['owner_group_name']) ?></span>
                    <?php endif; ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($canManage && $unassignedCount > 0): ?>
    <p class="gallery-trash-link">
        <a class="ghost-button compact-action" href="<?= e(url('/galerien/auffang')) ?>"><?= icon('inbox') ?><span>Auffangraum: <?= (int) $unassignedCount ?> noch nicht zugeordnet</span></a>
    </p>
<?php endif; ?>

<?php if ($canManage): ?>
    <?php view_partial('galleries/_link-section', [
        'galleryId' => null, 'links' => $catchAllLinks, 'freshLink' => $freshLink,
        'csrfToken' => $csrfToken, 'linkDays' => $linkDays,
    ]); ?>

    <p class="gallery-trash-link">
        <a class="ghost-button compact-action" href="<?= e(url('/admin/backup')) ?>"><?= icon('download') ?><span>Medien sichern (Verwaltung → Datensicherung)</span></a>
    </p>

    <?php if ($trashedCount > 0): ?>
        <p class="gallery-trash-link">
            <a class="ghost-button compact-action" href="<?= e(url('/galerien/papierkorb')) ?>"><?= icon('trash') ?><span>Papierkorb (<?= (int) $trashedCount ?>)</span></a>
        </p>
    <?php endif; ?>

    <?php if ($freshLink !== null): ?>
        <script src="<?= e(asset_url('/assets/js/vendor-qrcode.js')) ?>" defer></script>
    <?php endif; ?>
    <script src="<?= e(asset_url('/assets/js/gallery.js')) ?>" defer></script>
<?php endif; ?>

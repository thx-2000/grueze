<?php
/**
 * @var list<array<string,mixed>> $galleries
 * @var int $trashedCount
 * @var array<string,mixed> $capabilities
 */
?>
<header class="contacts-header">
    <div>
        <h1>Galerien</h1>
        <p class="muted">Foto- und Video-Sammlungen, zum Beispiel pro Stufentreffen. Vorerst nur für die Verwaltung sichtbar.</p>
    </div>
    <a class="button-link" href="<?= e(url('/galerien/neu')) ?>"><?= icon('plus') ?><span>Neue Galerie</span></a>
</header>

<?php
$missing = [];
if (empty($capabilities['gd']) && empty($capabilities['imagick'])) {
    $missing[] = 'Ohne GD/ImageMagick werden keine Vorschaubilder erzeugt – die Originale werden dann direkt angezeigt (auf dem Handy langsamer).';
}
if (empty($capabilities['heic'])) {
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
        <p class="muted">Noch keine Galerie. <a href="<?= e(url('/galerien/neu')) ?>">Erste Galerie anlegen</a>.</p>
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

<?php if ($trashedCount > 0): ?>
    <p class="gallery-trash-link">
        <a class="ghost-button compact-action" href="<?= e(url('/galerien/papierkorb')) ?>"><?= icon('trash') ?><span>Papierkorb (<?= (int) $trashedCount ?>)</span></a>
    </p>
<?php endif; ?>

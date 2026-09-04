<?php
/**
 * Eine Kachel im Medien-Raster.
 *
 * @var array<string,mixed> $item
 * @var array<string,mixed> $gallery
 * @var bool $manual
 * @var bool $canManage
 * @var bool $canUpload
 * @var int $currentUserId
 */
$isVideo = $item['kind'] === 'video';
$isCover = (int) ($gallery['cover_media_id'] ?? 0) === (int) $item['id'];
$isOwn = $currentUserId > 0 && (int) ($item['uploaded_by'] ?? 0) === $currentUserId;
$canEdit = $canManage || ($isOwn && $canUpload);
$thumbUrl = url('/galerien/datei?id=' . (int) $item['id'] . '&v=thumb');
$fullUrl = url('/galerien/datei?id=' . (int) $item['id'] . '&v=' . ($isVideo ? 'original' : 'web'));
$downloadUrl = url('/galerien/datei?id=' . (int) $item['id'] . '&v=original&dl=1');
$captured = trim((string) ($item['captured_at'] ?? ''));
?>
<li class="media-item<?= $isCover ? ' is-cover' : '' ?>" data-media-item data-media-id="<?= e((string) $item['id']) ?>"
    data-kind="<?= e((string) $item['kind']) ?>" data-full="<?= e($fullUrl) ?>" data-mime="<?= e((string) $item['mime']) ?>"
    data-download="<?= e($downloadUrl) ?>"<?= $manual ? ' draggable="true"' : '' ?>>
    <button type="button" class="media-thumb" data-open-lightbox>
        <?php if ($item['thumb_path']): ?>
            <img loading="lazy" alt="<?= e((string) ($item['caption'] ?? $item['original_name'] ?? '')) ?>" src="<?= e($thumbUrl) ?>">
        <?php else: ?>
            <span class="media-thumb-fallback"><?= icon($isVideo ? 'video' : 'image') ?></span>
        <?php endif; ?>
        <?php if ($isVideo): ?><span class="media-play" aria-hidden="true"><?= icon('play') ?></span><?php endif; ?>
    </button>

    <div class="media-meta">
        <?php if ($canEdit): ?>
            <input type="text" class="media-caption" data-caption placeholder="Bildunterschrift …"
                   maxlength="500" value="<?= e((string) ($item['caption'] ?? '')) ?>" aria-label="Bildunterschrift">
        <?php elseif (trim((string) ($item['caption'] ?? '')) !== ''): ?>
            <span class="media-caption-text"><?= e((string) $item['caption']) ?></span>
        <?php else: ?>
            <span class="media-caption-text muted">—</span>
        <?php endif; ?>
        <?php if ($captured !== ''): ?>
            <span class="media-captured" title="Aufnahmezeit"><?= icon('clock') ?><?= e(format_date(substr($captured, 0, 10))) ?></span>
        <?php endif; ?>
    </div>

    <div class="media-actions">
        <?php if ($manual): ?><span class="media-drag" aria-hidden="true"><?= icon('drag') ?></span><?php endif; ?>
        <?php if ($canManage): ?>
            <button type="button" class="icon-button" data-set-cover title="Als Titelbild"><?= icon('star') ?></button>
        <?php endif; ?>
        <a class="icon-button" href="<?= e($downloadUrl) ?>" title="Herunterladen"><?= icon('download') ?></a>
        <?php if ($canEdit): ?>
            <button type="button" class="icon-button is-danger" data-delete-media title="In den Papierkorb"><?= icon('trash') ?></button>
        <?php endif; ?>
    </div>
</li>

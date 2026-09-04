<?php
/**
 * @var array<string,mixed> $gallery
 * @var list<array<string,mixed>> $items
 * @var list<array<string,mixed>> $events
 * @var list<array<string,mixed>> $announcements
 * @var array<string,mixed> $capabilities
 * @var bool $canManage
 * @var bool $canUpload
 * @var int $currentUserId
 * @var string $usageNotice
 * @var list<array<string,mixed>> $uploadLinks
 * @var array<string,mixed>|null $freshLink
 * @var int $linkDays
 * @var list<array<string,mixed>> $groupChoices
 * @var bool $canPickGroup
 */
$g = $gallery;
$sortMode = (string) $g['sort_mode'];
$manual = $sortMode === 'manual' && $canManage;
$maxImage = (int) config('media.max_image_bytes', 25165824);
$maxVideo = (int) config('media.max_video_bytes', 524288000);
?>
<header class="contact-detail-head gallery-head">
    <div>
        <p class="eyebrow"><a href="<?= e(url('/galerien')) ?>">Galerien</a></p>
        <h1><?= e($g['title']) ?></h1>
        <p class="muted">
            <?php if ($g['gallery_date']): ?><?= e(format_date(substr((string) $g['gallery_date'], 0, 10))) ?> · <?php endif; ?>
            <span data-media-count><?= count($items) ?></span> Medien
            <?php if (trim((string) ($g['event_title'] ?? '')) !== ''): ?>
                · <a href="<?= e(url('/abstimmungen/detail?id=' . (int) $g['event_id'])) ?>"><?= icon('calendar') ?><?= e($g['event_title']) ?></a>
            <?php endif; ?>
            <?php if (trim((string) ($g['announcement_title'] ?? '')) !== ''): ?>
                · <a href="<?= e(url('/termine/detail?id=' . (int) $g['announcement_id'])) ?>"><?= icon('calendar') ?><?= e($g['announcement_title']) ?></a>
            <?php endif; ?>
            <?php if (trim((string) ($g['owner_group_name'] ?? '')) !== ''): ?>
                · <span class="gallery-card-event"><?= icon('users') ?>Gruppe „<?= e($g['owner_group_name']) ?>"</span>
            <?php endif; ?>
            <?php if (trim((string) ($g['visible_group_name'] ?? '')) !== ''): ?>
                · <span class="gallery-card-event"><?= icon('eye') ?>nur für „<?= e($g['visible_group_name']) ?>" sichtbar</span>
            <?php endif; ?>
        </p>
        <?php if (trim((string) ($g['description'] ?? '')) !== ''): ?>
            <p class="gallery-description"><?= nl2br(e($g['description'])) ?></p>
        <?php endif; ?>
    </div>
    <div class="toolbar-actions">
        <?php if ($items !== []): ?>
            <a class="ghost-button" href="<?= e(url('/galerien/zip?id=' . (int) $g['id'])) ?>" data-zip-link><?= icon('download') ?><span>Als ZIP</span></a>
        <?php endif; ?>
        <?php if ($canManage): ?>
            <form method="post" action="<?= e(url('/galerien/loeschen')) ?>" data-confirm="Galerie „<?= e($g['title']) ?>“ in den Papierkorb legen? (Mit allen Medien – rückholbar bis zum endgültigen Löschen.)">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" value="<?= e((string) $g['id']) ?>">
                <button type="submit" class="danger-button"><?= icon('trash') ?><span>In den Papierkorb</span></button>
            </form>
        <?php endif; ?>
    </div>
</header>

<div class="gallery-notice" role="note">
    <?= icon('eye') ?>
    <p><?= e($usageNotice) ?></p>
</div>

<?php if ($canManage): ?>
<details class="panel gallery-settings">
    <summary>Galerie-Details bearbeiten</summary>
    <form method="post" action="<?= e(url('/galerien/speichern')) ?>" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="id" value="<?= e((string) $g['id']) ?>">
        <label>
            <span>Titel <span aria-hidden="true">*</span></span>
            <input type="text" name="title" required maxlength="190" value="<?= e((string) $g['title']) ?>">
        </label>
        <label>
            <span>Beschreibung</span>
            <textarea name="description" rows="3" maxlength="5000"><?= e((string) ($g['description'] ?? '')) ?></textarea>
        </label>
        <div class="form-grid">
            <label>
                <span>Datum der Veranstaltung</span>
                <input type="date" name="gallery_date" value="<?= e(substr((string) ($g['gallery_date'] ?? ''), 0, 10)) ?>">
            </label>
            <label>
                <span>Sortierung der Medien</span>
                <select name="sort_mode">
                    <?php foreach (['captured' => 'Nach Aufnahmezeit', 'uploaded' => 'Nach Upload-Reihenfolge', 'manual' => 'Manuell (Ziehen)'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $sortMode === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="form-grid">
            <label>
                <span>Zu einer Abstimmung (optional)</span>
                <select name="event_id">
                    <option value="">— keine Abstimmung —</option>
                    <?php foreach ($events as $event): ?>
                        <option value="<?= e((string) $event['id']) ?>" <?= (int) ($g['event_id'] ?? 0) === (int) $event['id'] ? 'selected' : '' ?>><?= e($event['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Zu einer Ankündigung (optional)</span>
                <select name="announcement_id">
                    <option value="">— keine Ankündigung —</option>
                    <?php foreach ($announcements as $announcement): ?>
                        <option value="<?= e((string) $announcement['id']) ?>" <?= (int) ($g['announcement_id'] ?? 0) === (int) $announcement['id'] ? 'selected' : '' ?>><?= e($announcement['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <?php view_partial('galleries/_visibility-fields', ['gallery' => $g, 'groupChoices' => $groupChoices, 'canPickGroup' => $canPickGroup]); ?>
        <div class="form-actions">
            <button type="submit" class="button-link"><?= icon('check') ?><span>Speichern</span></button>
        </div>
    </form>
</details>

<?php view_partial('galleries/_link-section', [
    'galleryId' => (int) $g['id'], 'links' => $uploadLinks, 'freshLink' => $freshLink,
    'csrfToken' => $csrfToken, 'linkDays' => $linkDays,
]); ?>
<?php endif; ?>

<?php if ($canUpload): ?>
<section class="panel gallery-upload" data-gallery-upload
         data-gallery-id="<?= e((string) $g['id']) ?>"
         data-upload-url="<?= e(url('/galerien/hochladen')) ?>"
         data-max-image="<?= e((string) $maxImage) ?>"
         data-max-video="<?= e((string) $maxVideo) ?>"
         data-chunk-threshold="<?= e((string) config('media.chunk_threshold_bytes', 15728640)) ?>"
         data-chunk-size="<?= e((string) config('media.chunk_size_bytes', 4194304)) ?>"
         data-chunk-start-url="<?= e(url('/galerien/chunk/start')) ?>"
         data-chunk-part-url="<?= e(url('/galerien/chunk/teil')) ?>"
         data-chunk-finish-url="<?= e(url('/galerien/chunk/abschliessen')) ?>">
    <div class="dropzone" data-dropzone tabindex="0" role="button" aria-label="Dateien zum Hochladen auswählen">
        <?= icon('upload') ?>
        <p><strong>Fotos &amp; Videos hier ablegen</strong><br>oder <button type="button" class="linkish" data-pick>Dateien auswählen</button></p>
        <p class="muted">Mehrere gleichzeitig möglich. Bilder bis <?= e(\App\Services\MediaService::humanBytes($maxImage)) ?>, Videos bis <?= e(\App\Services\MediaService::humanBytes($maxVideo)) ?> (größere Videos werden automatisch in Stücken hochgeladen).</p>
        <input type="file" hidden multiple accept="image/*,video/*" data-file-input>
    </div>
    <ul class="upload-queue" data-upload-queue hidden></ul>
</section>
<?php endif; ?>

<section class="panel gallery-media-panel">
    <?php if ($manual): ?>
        <p class="field-hint"><?= icon('drag') ?> Zum Umsortieren die Kacheln ziehen. (Gilt, solange „Manuell" als Sortierung eingestellt ist.)</p>
    <?php endif; ?>
    <ul class="media-grid<?= $manual ? ' is-sortable' : '' ?>" data-media-grid
        data-can-manage="<?= $canManage ? '1' : '0' ?>"
        data-reorder-url="<?= e(url('/galerien/medien/sortieren')) ?>"
        data-caption-url="<?= e(url('/galerien/medien/beschriftung')) ?>"
        data-delete-url="<?= e(url('/galerien/medien/loeschen')) ?>"
        data-cover-url="<?= e(url('/galerien/cover')) ?>">
        <?php foreach ($items as $item): ?>
            <?php view_partial('galleries/_media-item', [
                'item' => $item, 'gallery' => $g, 'manual' => $manual,
                'canManage' => $canManage, 'canUpload' => $canUpload, 'currentUserId' => $currentUserId,
            ]); ?>
        <?php endforeach; ?>
    </ul>
    <p class="media-empty<?= $items === [] ? '' : ' is-hidden' ?>" data-media-empty>Noch keine Medien in dieser Galerie.</p>
</section>

<div class="lightbox" data-lightbox hidden data-notice="<?= e($usageNotice) ?>">
    <button type="button" class="lightbox-close" data-lb-close aria-label="Schließen"><?= icon('close') ?></button>
    <button type="button" class="lightbox-nav lightbox-prev" data-lb-prev aria-label="Vorheriges"><?= icon('chevron-right') ?></button>
    <button type="button" class="lightbox-nav lightbox-next" data-lb-next aria-label="Nächstes"><?= icon('chevron-right') ?></button>
    <figure class="lightbox-stage" data-lb-stage></figure>
    <figcaption class="lightbox-caption" data-lb-caption></figcaption>
    <p class="lightbox-notice"><?= e($usageNotice) ?></p>
</div>

<?php if ($canManage && $freshLink !== null): ?>
    <script src="<?= e(asset_url('/assets/js/vendor-qrcode.js')) ?>" defer></script>
<?php endif; ?>
<script src="<?= e(asset_url('/assets/js/gallery.js')) ?>" defer></script>

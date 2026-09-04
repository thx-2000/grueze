<?php
/**
 * Öffentliche Seite: Fotos/Videos beisteuern (ohne Login) über einen
 * Weitergabe-Link.
 *
 * @var string $token
 * @var string|null $target      Galerie-Titel, oder null (Auffangraum)
 * @var string $usageNotice
 * @var int $maxImage
 * @var int $maxVideo
 * @var int|null $remaining
 */
?>
<section class="panel stack contribute-card">
    <header class="page-head">
        <p class="eyebrow">Fotos &amp; Videos beisteuern</p>
        <h1><?php if ($target !== null): ?>Für „<?= e($target) ?>"<?php else: ?>Vielen Dank fürs Mitmachen!<?php endif; ?></h1>
        <p class="muted">Lade hier deine Aufnahmen hoch – die Orga ordnet sie zu und macht sie den Teilnehmenden zugänglich. Ein Login brauchst du nicht.</p>
    </header>

    <div class="gallery-notice" role="note">
        <?= icon('eye') ?>
        <p><?= e($usageNotice) ?></p>
    </div>

    <div class="gallery-upload" data-gallery-upload
         data-upload-url="<?= e(url('/beitragen/' . rawurlencode($token))) ?>"
         data-max-image="<?= e((string) $maxImage) ?>"
         data-max-video="<?= e((string) $maxVideo) ?>"
         data-chunk-threshold="<?= e((string) config('media.chunk_threshold_bytes', 15728640)) ?>"
         data-chunk-size="<?= e((string) config('media.chunk_size_bytes', 4194304)) ?>"
         data-chunk-start-url="<?= e(url('/beitragen/' . rawurlencode($token) . '/chunk/start')) ?>"
         data-chunk-part-url="<?= e(url('/beitragen/' . rawurlencode($token) . '/chunk/teil')) ?>"
         data-chunk-finish-url="<?= e(url('/beitragen/' . rawurlencode($token) . '/chunk/abschliessen')) ?>">
        <div class="dropzone" data-dropzone tabindex="0" role="button" aria-label="Fotos oder Videos auswählen">
            <?= icon('upload') ?>
            <p><strong>Fotos &amp; Videos auswählen</strong><br>oder hier ablegen</p>
            <p class="muted">
                Mehrere gleichzeitig möglich.
                <?php if ($remaining !== null): ?>Noch <?= (int) $remaining ?> Uploads über diesen Link möglich.<?php endif; ?>
            </p>
            <button type="button" class="button-link" data-pick><?= icon('image') ?><span>Auswählen</span></button>
            <input type="file" hidden multiple accept="image/*,video/*" data-file-input>
        </div>
        <p class="contribute-count" data-contribute-count hidden><span data-media-count>0</span> hochgeladen – danke!</p>
        <ul class="upload-queue" data-upload-queue hidden></ul>
    </div>

    <p class="detail-hint muted">Fragen? Wende dich an die Person, die dir diesen Link geschickt hat.</p>
</section>

<script src="<?= e(asset_url('/assets/js/gallery.js')) ?>" defer></script>

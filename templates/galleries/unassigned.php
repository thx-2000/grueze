<?php
/**
 * Auffangraum: Medien ohne Galerie-Zuordnung einer Galerie zuweisen.
 *
 * @var list<array<string,mixed>> $items
 * @var list<array<string,mixed>> $galleries
 * @var string $usageNotice
 */
?>
<header class="contact-detail-head">
    <p class="eyebrow"><a href="<?= e(url('/galerien')) ?>">Galerien</a></p>
    <h1>Auffangraum</h1>
    <p class="muted">Fotos und Videos, die über einen Link ohne feste Galerie beigesteuert wurden. Hier einer Galerie zuordnen – oder eine neue daraus machen.</p>
</header>

<div class="gallery-notice" role="note">
    <?= icon('eye') ?>
    <p><?= e($usageNotice) ?></p>
</div>

<?php if ($items === []): ?>
    <section class="panel">
        <p class="completeness-clear"><?= icon('check') ?><span>Der Auffangraum ist leer.</span></p>
    </section>
<?php else: ?>
<form method="post" action="<?= e(url('/galerien/medien/verschieben')) ?>" data-unassigned-form>
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

    <section class="panel gallery-assign-bar">
        <div class="form-grid">
            <label>
                <span>Zuordnen zu</span>
                <select name="target" data-assign-target>
                    <?php foreach ($galleries as $g): ?>
                        <option value="<?= e((string) $g['id']) ?>"><?= e($g['title']) ?></option>
                    <?php endforeach; ?>
                    <option value="new">➜ Neue Galerie …</option>
                </select>
            </label>
            <label data-new-title hidden>
                <span>Titel der neuen Galerie</span>
                <input type="text" name="new_title" maxlength="190">
            </label>
        </div>
        <div class="toolbar-actions">
            <button type="button" class="ghost-button compact-action" data-select-all-media>Alle auswählen</button>
            <button type="submit" class="button-link"><?= icon('check') ?><span>Ausgewählte verschieben</span></button>
        </div>
    </section>

    <section class="panel">
        <ul class="media-grid">
            <?php foreach ($items as $item): ?>
                <?php
                $isVideo = $item['kind'] === 'video';
                $thumbUrl = url('/galerien/datei?id=' . (int) $item['id'] . '&v=thumb');
                ?>
                <li class="media-item">
                    <label class="media-pick">
                        <input type="checkbox" name="media_id[]" value="<?= e((string) $item['id']) ?>" data-media-pick>
                        <span class="media-thumb">
                            <?php if ($item['thumb_path']): ?>
                                <img loading="lazy" alt="" src="<?= e($thumbUrl) ?>">
                            <?php else: ?>
                                <span class="media-thumb-fallback"><?= icon($isVideo ? 'video' : 'image') ?></span>
                            <?php endif; ?>
                            <?php if ($isVideo): ?><span class="media-play" aria-hidden="true"><?= icon('play') ?></span><?php endif; ?>
                        </span>
                    </label>
                    <?php if (trim((string) ($item['captured_at'] ?? '')) !== ''): ?>
                        <span class="media-captured"><?= icon('clock') ?><?= e(format_date(substr((string) $item['captured_at'], 0, 10))) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</form>

<script nonce="<?= e(csp_nonce()) ?>">
(function () {
    var form = document.querySelector('[data-unassigned-form]');
    if (!form) return;
    var target = form.querySelector('[data-assign-target]');
    var newTitle = form.querySelector('[data-new-title]');
    target.addEventListener('change', function () {
        newTitle.hidden = target.value !== 'new';
        var inp = newTitle.querySelector('input');
        if (inp) inp.required = target.value === 'new';
    });
    var toggle = form.querySelector('[data-select-all-media]');
    toggle.addEventListener('click', function () {
        var boxes = form.querySelectorAll('[data-media-pick]');
        var anyOff = Array.prototype.some.call(boxes, function (b) { return !b.checked; });
        boxes.forEach(function (b) { b.checked = anyOff; });
    });
})();
</script>
<?php endif; ?>

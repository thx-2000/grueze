<section class="hero-card narrow">
    <div>
        <p class="eyebrow">Rundmail</p>
        <h2>An wen soll die Nachricht gehen?</h2>
        <p class="muted">Empfängerkreis wählen, dann schreibst du im nächsten Schritt die Nachricht. Es werden immer nur Kontakte mit hinterlegter Mailadresse angeschrieben.</p>
    </div>
</section>

<section class="panel">
    <form method="post" action="<?= e(url('/rundmail')) ?>" class="stack rundmail-picker">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <?php if ($fromFilter): ?>
            <label class="rundmail-option">
                <input type="radio" name="mode" value="filter" checked>
                <span class="rundmail-option-body">
                    <span class="rundmail-option-title">Aktuelle Auswahl aus der Kontaktliste
                        <span class="rundmail-count"><?= e((string) $filterCount) ?></span>
                    </span>
                    <span class="rundmail-option-hint"><?= e($filterSummary) ?></span>
                </span>
            </label>
        <?php endif; ?>

        <label class="rundmail-option">
            <input type="radio" name="mode" value="all" <?= $fromFilter ? '' : 'checked' ?>>
            <span class="rundmail-option-body">
                <span class="rundmail-option-title">Alle Kontakte mit Mailadresse
                    <span class="rundmail-count"><?= e((string) $totalWithEmail) ?></span>
                </span>
            </span>
        </label>

        <label class="rundmail-option">
            <input type="radio" name="mode" value="category">
            <span class="rundmail-option-body">
                <span class="rundmail-option-title">Eine Kategorie</span>
                <span class="rundmail-sub">
                    <select name="category_id">
                        <option value="">Kategorie wählen …</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e((string) $category['id']) ?>">
                                <?= e($category['name']) ?> (<?= e((string) ($categoryCounts[(int) $category['id']] ?? 0)) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </span>
            </span>
        </label>

        <?php if ($tags !== []): ?>
            <label class="rundmail-option">
                <input type="radio" name="mode" value="tags">
                <span class="rundmail-option-body">
                    <span class="rundmail-option-title">Bestimmte Tags</span>
                    <span class="rundmail-option-hint">Angeschrieben wird, wer mindestens einen der gewählten Tags hat.</span>
                    <span class="rundmail-sub tag-picker">
                        <?php foreach ($tags as $tag): ?>
                            <label class="tag-option">
                                <input type="checkbox" name="tag_ids[]" value="<?= e((string) $tag['id']) ?>">
                                <span><?= e($tag['name']) ?> (<?= e((string) ($tagCounts[(int) $tag['id']] ?? 0)) ?>)</span>
                            </label>
                        <?php endforeach; ?>
                    </span>
                </span>
            </label>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit"><?= icon('message-send') ?><span>Weiter zum Schreiben</span></button>
            <a class="ghost-button" href="<?= e(url('/kontakte')) ?>">Abbrechen</a>
        </div>
    </form>
</section>

<script>
    (function () {
        const form = document.querySelector('.rundmail-picker');
        if (!form) return;
        const options = [...form.querySelectorAll('.rundmail-option')];
        const sync = () => {
            options.forEach((opt) => {
                const radio = opt.querySelector('input[type="radio"]');
                opt.classList.toggle('is-active', radio.checked);
                opt.querySelectorAll('.rundmail-sub select, .rundmail-sub input').forEach((el) => {
                    el.disabled = !radio.checked;
                });
            });
        };
        form.querySelectorAll('input[name="mode"]').forEach((r) => r.addEventListener('change', sync));
        // Beim Ändern eines Unterfelds die zugehörige Option aktivieren
        form.querySelectorAll('.rundmail-sub select, .rundmail-sub input').forEach((el) => {
            el.addEventListener('change', () => {
                const radio = el.closest('.rundmail-option').querySelector('input[type="radio"]');
                radio.checked = true;
                sync();
            });
        });
        sync();
    })();
</script>

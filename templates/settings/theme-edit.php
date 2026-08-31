<?php
$slug = (string) ($theme['slug'] ?? '');
$tokens = $theme['tokens'] ?? [];
$groups = [];
foreach ($tokenDefs as $key => $def) {
    $groups[$def['group']][] = $def;
}
?>
<section class="hero-card narrow">
    <div>
        <p class="eyebrow">Theme bearbeiten</p>
        <h2><?= e($theme['name']) ?></h2>
        <p class="muted">
            <?= $theme['based_on'] !== '' ? 'Basiert auf „' . e($theme['based_on']) . '". ' : '' ?>
            Leere Felder fallen auf den Standardwert zurück. Farbwerte als Hex (<code>#ff8800</code>) oder CSS (<code>rgba(…)</code>).
        </p>
    </div>
</section>

<section class="panel">
    <form method="post" action="<?= e(url('/settings/themes/umbenennen')) ?>" class="inline-form">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="slug" value="<?= e($slug) ?>">
        <input type="text" name="name" value="<?= e($theme['name']) ?>" required aria-label="Theme-Name">
        <button type="submit">Umbenennen</button>
    </form>
</section>

<section class="panel">
    <form method="post" action="<?= e(url('/settings/themes/speichern')) ?>" class="stack theme-editor">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="slug" value="<?= e($slug) ?>">

        <?php foreach ($groups as $groupName => $defs): ?>
            <div class="subsection-card">
                <strong><?= e($groupName) ?></strong>
                <div class="form-grid branding-color-grid">
                    <?php foreach ($defs as $def): ?>
                        <?php $val = (string) ($tokens[$def['key']] ?? ''); ?>
                        <label>
                            <span><?= e($def['label']) ?></span>
                            <?php if ($def['type'] === 'color'): ?>
                                <div class="color-input-row">
                                    <span class="color-preview-swatch" data-color-preview style="--swatch: <?= e($val ?: 'transparent') ?>;"></span>
                                    <input type="text" name="token_<?= e($def['key']) ?>" value="<?= e($val) ?>" placeholder="<?= e((string) ($defaults[$def['key']] ?? '')) ?>" data-color-source>
                                </div>
                            <?php else: ?>
                                <input type="text" name="token_<?= e($def['key']) ?>" value="<?= e($val) ?>" placeholder="<?= e((string) ($defaults[$def['key']] ?? '')) ?>">
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="form-actions">
            <button type="submit">Theme speichern</button>
            <a class="ghost-button" href="<?= e(url('/settings/themes')) ?>">Zurück zur Übersicht</a>
        </div>
    </form>
</section>

<script>
    document.querySelectorAll('[data-color-source]').forEach((input) => {
        const swatch = input.closest('.color-input-row')?.querySelector('[data-color-preview]');
        if (!swatch) return;
        const sync = () => swatch.style.setProperty('--swatch', input.value.trim() || 'transparent');
        input.addEventListener('input', sync);
        sync();
    });
</script>

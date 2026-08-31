<?php
$swatchKeys = ['color_bg', 'color_surface', 'color_primary', 'color_accent', 'color_secondary', 'color_text'];
?>
<section class="hero-card narrow">
    <div>
        <p class="eyebrow">Verwaltung</p>
        <h2>Themes</h2>
        <p class="muted">Ein Theme bündelt Farben, Schriften und Eckenradien unter einem Namen. Datei-Themes liegen im Ordner <code>themes/</code> und dienen als Vorlage – zum Anpassen zuerst duplizieren. Eigene Themes lassen sich frei bearbeiten, umbenennen und löschen.</p>
    </div>
</section>

<section class="panel">
    <div class="theme-grid">
        <?php foreach ($themes as $slug => $theme): ?>
            <?php $isActive = $slug === $activeSlug; $tokens = $theme['tokens']; ?>
            <article class="theme-card<?= $isActive ? ' is-active' : '' ?>">
                <div class="theme-swatches" aria-hidden="true">
                    <?php foreach ($swatchKeys as $k): ?>
                        <span style="background: <?= e($tokens[$k] ?? '#ccc') ?>"></span>
                    <?php endforeach; ?>
                </div>
                <div class="theme-card-body">
                    <div class="theme-card-head">
                        <strong><?= e($theme['name']) ?></strong>
                        <?php if ($isActive): ?><span class="theme-badge is-active">Aktiv</span><?php endif; ?>
                        <?php if ($theme['source'] === 'file'): ?><span class="theme-badge">Vorlage</span><?php endif; ?>
                    </div>
                    <?php if (($theme['description'] ?? '') !== ''): ?>
                        <p class="muted"><?= e($theme['description']) ?></p>
                    <?php endif; ?>

                    <div class="theme-card-actions">
                        <?php if (!$isActive): ?>
                            <form method="post" action="<?= e(url('/settings/themes/aktivieren')) ?>">
                                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="slug" value="<?= e($slug) ?>">
                                <button type="submit">Aktivieren</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="<?= e(url('/settings/themes/duplizieren')) ?>">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="slug" value="<?= e($slug) ?>">
                            <button type="submit" class="ghost-button compact-action"><?= icon('copy') ?><span>Duplizieren</span></button>
                        </form>
                        <?php if ($theme['source'] === 'db'): ?>
                            <a class="ghost-button compact-action" href="<?= e(url('/settings/themes/bearbeiten?slug=' . rawurlencode($slug))) ?>"><?= icon('edit') ?><span>Bearbeiten</span></a>
                            <form method="post" action="<?= e(url('/settings/themes/loeschen')) ?>" onsubmit="return confirm('Theme „<?= e(addslashes($theme['name'])) ?>“ wirklich löschen?<?= $isActive ? ' Es ist gerade aktiv – danach wird auf „Hell“ gewechselt.' : '' ?>');">
                                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="slug" value="<?= e($slug) ?>">
                                <button type="submit" class="danger-button icon-button" title="Löschen" aria-label="Löschen"><?= icon('trash') ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

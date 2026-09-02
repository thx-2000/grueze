<?php
$swatchKeys = ['color_bg', 'color_surface', 'color_primary', 'color_accent', 'color_secondary', 'color_text'];
?>
<header class="page-head">
    <p class="eyebrow">Verwaltung</p>
    <h1>Themes</h1>
    <p class="muted">Ein Theme bündelt Farben, Schriften und Eckenradien unter einem Namen. Die
        mitgelieferten Vorlagen (Signalfarbe, Hell, Dunkel) lassen sich nicht direkt ändern – der
        Knopf „Kopieren &amp; bearbeiten" legt eine eigene Kopie an und öffnet sie sofort im
        Editor mit Live-Vorschau, Farbwähler und Kontrasthinweisen. Eigene Themes lassen sich
        frei bearbeiten, umbenennen und löschen.</p>
</header>

<section class="panel">
    <div class="theme-grid">
        <?php foreach ($themes as $slug => $theme): ?>
            <?php $isActive = $slug === $activeSlug; $tokens = $theme['tokens']; $isFile = $theme['source'] === 'file'; ?>
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
                        <?php if ($isFile): ?><span class="theme-badge">Vorlage</span><?php endif; ?>
                    </div>
                    <?php if (($theme['description'] ?? '') !== ''): ?>
                        <p class="muted"><?= e($theme['description']) ?></p>
                    <?php endif; ?>

                    <div class="theme-card-actions">
                        <?php if (!$isActive): ?>
                            <form method="post" action="<?= e(url('/settings/themes/aktivieren')) ?>">
                                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="slug" value="<?= e($slug) ?>">
                                <button type="submit" class="compact-action">Aktivieren</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($isFile): ?>
                            <form method="post" action="<?= e(url('/settings/themes/duplizieren')) ?>">
                                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="slug" value="<?= e($slug) ?>">
                                <button type="submit" class="ghost-button compact-action"><?= icon('edit') ?><span>Kopieren &amp; bearbeiten</span></button>
                            </form>
                        <?php else: ?>
                            <a class="ghost-button compact-action" href="<?= e(url('/settings/themes/bearbeiten?slug=' . rawurlencode($slug))) ?>"><?= icon('edit') ?><span>Bearbeiten</span></a>
                            <form method="post" action="<?= e(url('/settings/themes/duplizieren')) ?>">
                                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="slug" value="<?= e($slug) ?>">
                                <button type="submit" class="ghost-button compact-action"><?= icon('copy') ?><span>Duplizieren</span></button>
                            </form>
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

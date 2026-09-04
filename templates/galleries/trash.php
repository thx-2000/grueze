<?php
/**
 * @var list<array<string,mixed>> $galleries
 * @var int $trashDays
 */
?>
<header class="contact-detail-head">
    <p class="eyebrow"><a href="<?= e(url('/galerien')) ?>">Galerien</a></p>
    <h1>Galerie-Papierkorb</h1>
    <p class="muted">
        <?php if ($trashDays > 0): ?>
            Gelöschte Galerien werden nach <?= (int) $trashDays ?> Tagen endgültig entfernt – samt aller Dateien.
        <?php else: ?>
            Gelöschte Galerien bleiben liegen, bis sie hier endgültig entfernt werden.
        <?php endif; ?>
    </p>
</header>

<?php if ($galleries === []): ?>
    <section class="panel">
        <p class="completeness-clear"><?= icon('check') ?><span>Der Papierkorb ist leer.</span></p>
    </section>
<?php else: ?>
    <section class="panel">
        <ul class="events-list">
            <?php foreach ($galleries as $g): ?>
                <li class="events-row">
                    <span class="events-row-main">
                        <span class="events-row-title"><?= e($g['title']) ?></span>
                        <span class="events-row-meta">
                            <?= (int) $g['media_count'] === 1 ? '1 Medium' : e((string) (int) $g['media_count']) . ' Medien' ?>
                            · gelöscht <?= e(format_date(substr((string) $g['deleted_at'], 0, 10))) ?>
                        </span>
                    </span>
                    <span class="toolbar-actions">
                        <form method="post" action="<?= e(url('/galerien/wiederherstellen')) ?>">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="id" value="<?= e((string) $g['id']) ?>">
                            <button type="submit" class="ghost-button"><?= icon('reset') ?><span>Wiederherstellen</span></button>
                        </form>
                        <form method="post" action="<?= e(url('/galerien/endgueltig-loeschen')) ?>" data-confirm="„<?= e($g['title']) ?>“ mit allen Dateien endgültig löschen? Das lässt sich nicht rückgängig machen.">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="id" value="<?= e((string) $g['id']) ?>">
                            <button type="submit" class="danger-button"><?= icon('trash') ?><span>Endgültig löschen</span></button>
                        </form>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

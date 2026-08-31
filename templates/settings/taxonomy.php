<?php
$renderRows = static function (array $items, string $saveAction, string $deleteAction, string $csrfToken, string $singular): void {
    foreach ($items as $item):
        $count = (int) ($item['contact_count'] ?? 0);
        ?>
        <div class="taxo-row">
            <form method="post" action="<?= e(url($saveAction)) ?>" class="taxo-edit">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">
                <input type="text" name="name" value="<?= e($item['name']) ?>" required aria-label="Name">
                <span class="taxo-count"><?= e((string) $count) ?> <?= $count === 1 ? 'Kontakt' : 'Kontakte' ?></span>
                <button type="submit" class="ghost-button compact-action">Speichern</button>
            </form>
            <form method="post" action="<?= e(url($deleteAction)) ?>" onsubmit="return confirm('<?= e($singular) ?> „<?= e(addslashes($item['name'])) ?>“ wirklich löschen?');">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">
                <button type="submit" class="danger-button icon-button" title="Löschen" aria-label="Löschen"><?= icon('trash') ?></button>
            </form>
        </div>
        <?php
    endforeach;
};
?>
<section class="hero-card narrow">
    <div>
        <p class="eyebrow">Verwaltung</p>
        <h2>Kategorien &amp; Tags</h2>
        <p class="muted">Anlegen, umbenennen und löschen. Das Zuordnen zu Kontakten passiert im Kontaktformular und in der Sammelbearbeitung.</p>
    </div>
</section>

<section class="panel stack">
    <div class="panel-head"><div><h3>Kategorien</h3><p class="muted">Jeder Kontakt kann genau eine Kategorie haben.</p></div></div>

    <?php if ($categories === []): ?>
        <p class="muted">Noch keine Kategorien angelegt.</p>
    <?php else: ?>
        <div class="taxo-list">
            <?php $renderRows($categories, '/verwaltung/kategorien-tags/kategorie', '/verwaltung/kategorien-tags/kategorie/loeschen', $csrfToken, 'Kategorie'); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/verwaltung/kategorien-tags/kategorie')) ?>" class="taxo-add">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="text" name="name" placeholder="Neue Kategorie" required aria-label="Neue Kategorie">
        <button type="submit"><?= icon('plus') ?><span>Kategorie anlegen</span></button>
    </form>
</section>

<section class="panel stack">
    <div class="panel-head"><div><h3>Tags</h3><p class="muted">Ein Kontakt kann mehrere Tags haben.</p></div></div>

    <?php if ($tags === []): ?>
        <p class="muted">Noch keine Tags angelegt.</p>
    <?php else: ?>
        <div class="taxo-list">
            <?php $renderRows($tags, '/verwaltung/kategorien-tags/tag', '/verwaltung/kategorien-tags/tag/loeschen', $csrfToken, 'Tag'); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/verwaltung/kategorien-tags/tag')) ?>" class="taxo-add">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="text" name="name" placeholder="Neuer Tag" required aria-label="Neuer Tag">
        <button type="submit"><?= icon('plus') ?><span>Tag anlegen</span></button>
    </form>
</section>

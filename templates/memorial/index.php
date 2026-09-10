<?php
/**
 * @var array<string, list<array<string,mixed>>> $groups  Bereichs-Label => Einträge
 * @var bool $canManage
 */
$lifespan = static function (array $e): string {
    $born = $e['born_year'] ? (string) $e['born_year'] : '';
    $died = $e['died_year'] ? (string) $e['died_year'] : '';
    if ($born !== '' && $died !== '') {
        return $born . ' – ' . $died;
    }

    return $died !== '' ? '† ' . $died : '';
};

$card = static function (array $e) use ($canManage, $lifespan, $csrfToken): void {
    ?>
    <li class="memorial-card">
        <div class="memorial-portrait" aria-hidden="true">
            <?php if (!empty($e['photo_path'])): ?>
                <img src="<?= e(asset_url('/' . ltrim((string) $e['photo_path'], '/'))) ?>" alt="">
            <?php else: ?>
                <span class="memorial-portrait-fallback"><?= e(mb_substr((string) $e['name'], 0, 1)) ?></span>
            <?php endif; ?>
        </div>
        <div class="memorial-body">
            <p class="memorial-name">
                <?= e((string) $e['name']) ?>
                <?php if ($e['birth_name'] !== ''): ?><span class="birth-name-inline"><?= e((string) $e['birth_name']) ?></span><?php endif; ?>
            </p>
            <?php if (($span = $lifespan($e)) !== ''): ?>
                <p class="memorial-dates"><?= e($span) ?></p>
            <?php endif; ?>
            <?php if (!empty($e['note'])): ?>
                <p class="memorial-note"><?= nl2br(e((string) $e['note'])) ?></p>
            <?php endif; ?>
            <?php if ($canManage): ?>
                <div class="memorial-admin">
                    <a class="ghost-button compact-action" href="<?= e(url('/memoriam/bearbeiten?id=' . (int) $e['id'])) ?>"><?= icon('edit') ?><span>Bearbeiten</span></a>
                    <?php if ($e['contact_id'] !== null && $e['contact_reachable']): ?>
                        <a class="ghost-button compact-action" href="<?= e(url('/contacts/edit?id=' . (int) $e['contact_id'])) ?>"><?= icon('contacts') ?><span>Kontakt</span></a>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('/memoriam/loeschen')) ?>" data-confirm="„<?= e((string) $e['name']) ?>“ von der Gedenkseite entfernen?<?= $e['contact_id'] !== null ? ' Der verknüpfte Kontakt kommt dabei zurück ins Adressbuch.' : '' ?>">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="id" value="<?= (int) $e['id'] ?>">
                        <button type="submit" class="danger-button compact-action"><?= icon('trash') ?><span>Entfernen</span></button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </li>
    <?php
};
$total = array_sum(array_map('count', $groups));
?>
<header class="contact-detail-head">
    <p class="eyebrow">Gedenken</p>
    <h1><?= e(memorial_label()) ?></h1>
    <p class="muted">Menschen aus unserer Mitte, die nicht mehr unter uns sind.</p>
    <?php if ($canManage): ?>
        <div class="toolbar-actions">
            <a class="button-link" href="<?= e(url('/memoriam/neu')) ?>"><?= icon('plus') ?><span>Eintrag hinzufügen</span></a>
        </div>
    <?php endif; ?>
</header>

<?php if ($total === 0): ?>
    <section class="panel">
        <p class="muted">
            Noch kein Eintrag.
            <?php if ($canManage): ?>
                Über <a href="<?= e(url('/memoriam/neu')) ?>">Eintrag hinzufügen</a> oder direkt aus einem Kontakt heraus („Als verstorben eintragen").
            <?php endif; ?>
        </p>
    </section>
<?php else: ?>
    <?php foreach ($groups as $label => $entries): ?>
        <section class="panel memorial-group">
            <?php if ((string) $label !== ''): ?>
                <div class="panel-head"><div><h2><?= e((string) $label) ?></h2></div></div>
            <?php endif; ?>
            <ul class="memorial-grid">
                <?php foreach ($entries as $entry) { $card($entry); } ?>
            </ul>
        </section>
    <?php endforeach; ?>
<?php endif; ?>

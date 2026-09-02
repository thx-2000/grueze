<?php
$activeCategory = '';
if ($categoryId !== '') {
    foreach ($categories as $category) {
        if ((string) $category['id'] === $categoryId) {
            $activeCategory = (string) $category['name'];
        }
    }
}
$gapCount = count($gaps);
$whichLabel = ['all' => 'Alle Lücken', 'email' => 'Nur ohne Mailadresse', 'phone' => 'Nur ohne Handynummer'][$which] ?? 'Alle Lücken';

$queryFor = static function (array $overrides) use ($categoryId, $which, $numbered): string {
    $params = array_merge([
        'category_id' => $categoryId,
        'which' => $which,
        'numbered' => $numbered ? '1' : '0',
    ], $overrides);

    return url('/vollstaendigkeit?' . http_build_query(array_filter($params, static fn ($v): bool => $v !== '' && $v !== null)));
};
?>
<header class="contacts-header">
    <div>
        <h1>Vollständigkeit</h1>
        <p class="muted">Wo fehlen noch Angaben?<?= $activeCategory !== '' ? ' Kategorie: ' . e($activeCategory) . '.' : '' ?></p>
    </div>
</header>

<section class="completeness-overview" aria-label="Überblick">
    <div class="completeness-tile">
        <span class="completeness-value"><?= e((string) $stats['total']) ?></span>
        <span class="completeness-label">Kontakte<?= $activeCategory !== '' ? ' in dieser Kategorie' : ' gesamt' ?></span>
    </div>
    <a class="completeness-tile<?= $stats['without_email'] > 0 ? ' is-gap' : '' ?><?= $which === 'email' ? ' is-active' : '' ?>" href="<?= e($queryFor(['which' => $which === 'email' ? 'all' : 'email'])) ?>">
        <span class="completeness-value"><?= e((string) $stats['without_email']) ?></span>
        <span class="completeness-label">ohne Mailadresse</span>
    </a>
    <a class="completeness-tile<?= $stats['without_phone'] > 0 ? ' is-gap' : '' ?><?= $which === 'phone' ? ' is-active' : '' ?>" href="<?= e($queryFor(['which' => $which === 'phone' ? 'all' : 'phone'])) ?>">
        <span class="completeness-value"><?= e((string) $stats['without_phone']) ?></span>
        <span class="completeness-label">ohne Handynummer</span>
    </a>
</section>

<section class="panel">
    <form method="get" action="<?= e(url('/vollstaendigkeit')) ?>" class="filter-bar">
        <label class="filter-field">
            <span class="visually-hidden">Kategorie</span>
            <select name="category_id">
                <option value="">Alle Kontakte</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e((string) $category['id']) ?>" <?= $categoryId === (string) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="filter-field">
            <span class="visually-hidden">Was zeigen</span>
            <select name="which">
                <option value="all" <?= $which === 'all' ? 'selected' : '' ?>>Alle Lücken</option>
                <option value="email" <?= $which === 'email' ? 'selected' : '' ?>>Nur ohne Mailadresse</option>
                <option value="phone" <?= $which === 'phone' ? 'selected' : '' ?>>Nur ohne Handynummer</option>
            </select>
        </label>
        <button type="submit" class="filter-apply">Anzeigen</button>
    </form>
</section>

<section class="panel completeness-list-panel">
    <div class="panel-head">
        <div>
            <h2><?= e($whichLabel) ?></h2>
            <p class="muted"><?= e((string) $gapCount) ?> <?= $gapCount === 1 ? 'Person' : 'Personen' ?> mit fehlenden Angaben.</p>
        </div>
    </div>

    <?php if ($gaps === []): ?>
        <p class="completeness-clear">
            <?= icon('check') ?>
            <span>Keine Lücken in dieser Auswahl – alles gepflegt.</span>
        </p>
    <?php else: ?>
        <ul class="completeness-list">
            <?php foreach ($gaps as $gap): ?>
                <li class="completeness-row">
                    <div class="completeness-person">
                        <strong><?= e($gap['name']) ?></strong>
                        <?php if ($gap['geburtsname'] !== ''): ?><span class="birth-name-inline">(ehem. <?= e($gap['geburtsname']) ?>)</span><?php endif; ?>
                        <span class="completeness-chips">
                            <?php if ($gap['missing_email']): ?><span class="status-chip is-warn">Mail fehlt</span><?php endif; ?>
                            <?php if ($gap['missing_phone']): ?><span class="status-chip is-warn">Tel. fehlt</span><?php endif; ?>
                        </span>
                        <?php if ($gap['category_name'] !== ''): ?><span class="muted completeness-cat"><?= e($gap['category_name']) ?></span><?php endif; ?>
                    </div>
                    <div class="completeness-actions">
                        <a class="ghost-button" href="<?= e(url('/contacts/edit?id=' . $gap['id'])) ?>"><?= icon('edit') ?><span>Bearbeiten</span></a>
                        <?php if ($gap['email'] !== ''): ?>
                            <form method="post" action="<?= e(url('/mail/compose')) ?>">
                                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="selected_contacts[]" value="<?= e((string) $gap['id']) ?>">
                                <button type="submit" class="ghost-button"><?= icon('mail') ?><span>Schreiben</span></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="panel">
    <details class="admin-drawer">
        <summary><span><?= icon('copy') ?></span><span>Namen weitergeben (Kopiervorlage)</span></summary>
        <div class="admin-drawer-body stack">
            <div class="panel-head">
                <div>
                    <h2>Namensliste</h2>
                    <p class="muted">Zum Weitergeben, damit alle prüfen können, ob noch jemand ganz fehlt.<?= $activeCategory !== '' ? ' Kategorie: ' . e($activeCategory) . '.' : '' ?></p>
                </div>
                <button type="button" class="ghost-button" data-copy="#nameListField"><?= icon('copy') ?><span>Kopieren</span></button>
            </div>

            <div class="completeness-namelist-tools">
                <a class="<?= $numbered ? 'button-link' : 'ghost-button' ?>" href="<?= e($queryFor(['numbered' => '1'])) ?>">Nummeriert</a>
                <a class="<?= $numbered ? 'ghost-button' : 'button-link' ?>" href="<?= e($queryFor(['numbered' => '0'])) ?>">Ohne Nummern</a>
            </div>

            <form method="post" action="<?= e(url('/vollstaendigkeit/teilen')) ?>" class="stack">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <label>
                    <span class="visually-hidden">Namensliste</span>
                    <textarea name="name_list" id="nameListField" rows="12" spellcheck="false"><?= e($nameListText) ?></textarea>
                </label>
                <?php if ($canShare): ?>
                    <label>
                        <span>Einleitungstext (optional)</span>
                        <textarea name="intro" rows="3" placeholder="Hallo zusammen, bitte schaut die Liste durch …"></textarea>
                    </label>
                    <p class="field-hint">Im nächsten Schritt wählst du den Empfängerkreis und siehst die Nachricht vor dem Senden.</p>
                    <div class="toolbar-actions">
                        <button type="submit" class="button-link"><?= icon('mail') ?><span>Als Nachricht an eine Gruppe</span></button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </details>
</section>

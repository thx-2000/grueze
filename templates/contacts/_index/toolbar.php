<?php
/**
 * Filterleiste des Adressbuchs: Suche, Kategorie, „Mehr Filter" (Sortierung,
 * Tags, Gruppen, fehlende Angaben) sowie die Werkzeuge darunter (Rundmail an
 * die Liste, Export, Datenpflege).
 *
 * @var array<string,mixed> $filters
 * @var array<int,array<string,mixed>> $categories
 * @var array<int,array<string,mixed>> $tags
 * @var array<int,array<string,mixed>> $groups
 * @var list<int> $activeTagIds
 * @var list<int> $activeGroupIds
 * @var string $currentSort
 * @var string $currentDirection
 * @var bool $advancedFilterActive
 * @var bool $hasActiveFilter
 * @var bool $canManage
 * @var bool $canSendRegularMail
 * @var int $duplicateCount
 * @var int $retiredCount
 */
?>
<section class="panel addressbook-filter">
    <form method="get" action="<?= e(url('/kontakte')) ?>" class="filter-bar">
        <label class="filter-field filter-field--search">
            <span class="visually-hidden">Suche</span>
            <?= icon('search') ?>
            <input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Name oder Geburtsname">
        </label>
        <label class="filter-field">
            <span class="visually-hidden">Kategorie</span>
            <select name="category_id">
                <option value="">Alle Kategorien</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e((string) $category['id']) ?>" <?= (string) ($filters['category_id'] ?? '') === (string) $category['id'] ? 'selected' : '' ?>>
                        <?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="filter-apply">Filtern</button>

        <details class="filter-more"<?= $advancedFilterActive ? ' open' : '' ?>>
            <summary><?= icon('sliders') ?><span>Mehr Filter</span></summary>
            <div class="filter-more-body">
                <div class="filter-more-grid">
                    <label>
                        <span>Sortierung</span>
                        <select name="sort">
                            <option value="vorname" <?= $currentSort === 'vorname' ? 'selected' : '' ?>>Vorname</option>
                            <option value="nachname" <?= $currentSort === 'nachname' ? 'selected' : '' ?>>Nachname</option>
                            <option value="category_name" <?= $currentSort === 'category_name' ? 'selected' : '' ?>>Kategorie</option>
                            <option value="ort" <?= $currentSort === 'ort' ? 'selected' : '' ?>>Ort</option>
                            <option value="geburtstag" <?= $currentSort === 'geburtstag' ? 'selected' : '' ?>>Geburtstag</option>
                            <option value="tags" <?= $currentSort === 'tags' ? 'selected' : '' ?>>Tags</option>
                            <option value="groups" <?= $currentSort === 'groups' ? 'selected' : '' ?>>Gruppen</option>
                            <option value="created_at" <?= $currentSort === 'created_at' ? 'selected' : '' ?>>Angelegt</option>
                        </select>
                    </label>
                    <label>
                        <span>Richtung</span>
                        <select name="direction">
                            <option value="asc" <?= $currentDirection === 'asc' ? 'selected' : '' ?>>A bis Z</option>
                            <option value="desc" <?= $currentDirection === 'desc' ? 'selected' : '' ?>>Z bis A</option>
                        </select>
                    </label>
                    <div class="filter-tags" role="group" aria-label="Nach Tags filtern">
                        <span>Tags</span>
                        <div class="tag-picker">
                            <?php foreach ($tags as $tag): ?>
                                <?php $selected = in_array((int) $tag['id'], $activeTagIds, true); ?>
                                <label class="tag-option<?= $selected ? ' is-selected' : '' ?>">
                                    <input type="checkbox" name="tag_ids[]" value="<?= e((string) $tag['id']) ?>" <?= $selected ? 'checked' : '' ?>>
                                    <span><?= e($tag['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                            <?php if ($tags === []): ?>
                                <p class="field-hint">Noch keine Tags angelegt.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($groups !== []): ?>
                        <div class="filter-tags" role="group" aria-label="Nach Gruppen filtern">
                            <span>Gruppen</span>
                            <div class="tag-picker">
                                <?php foreach ($groups as $group): ?>
                                    <?php $selected = in_array((int) $group['id'], $activeGroupIds, true); ?>
                                    <label class="tag-option<?= $selected ? ' is-selected' : '' ?>">
                                        <input type="checkbox" name="group_ids[]" value="<?= e((string) $group['id']) ?>" <?= $selected ? 'checked' : '' ?>>
                                        <span><?= e($group['name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="filter-tags" role="group" aria-label="Fehlende Angaben">
                        <span>Fehlende Angaben</span>
                        <label class="inline-toggle">
                            <input type="checkbox" name="without_email" value="1" <?= ($filters['without_email'] ?? '') === '1' ? 'checked' : '' ?>>
                            <span>Nur Personen ohne Mailadresse</span>
                        </label>
                        <label class="inline-toggle">
                            <input type="checkbox" name="without_phone" value="1" <?= ($filters['without_phone'] ?? '') === '1' ? 'checked' : '' ?>>
                            <span>Nur Personen ohne Handynummer</span>
                        </label>
                    </div>
                </div>
                <div class="filter-more-actions">
                    <button type="submit">Filter anwenden</button>
                    <?php if ($hasActiveFilter): ?>
                        <a class="ghost-button" href="<?= e(url('/kontakte')) ?>"><?= icon('reset') ?><span>Zurücksetzen</span></a>
                    <?php endif; ?>
                </div>
            </div>
        </details>
    </form>

    <?php if ($hasActiveFilter): ?>
        <p class="filter-active-note">
            <?= icon('sliders') ?>
            <span>Gefilterte Ansicht.</span>
            <a href="<?= e(url('/kontakte')) ?>">Alle Kontakte zeigen</a>
        </p>
    <?php endif; ?>

    <?php
    $careCount = (int) ($duplicateCount ?? 0) + (int) ($retiredCount ?? 0);
    $showCare = $canManage || can('contacts.delete');
    ?>
    <?php if ($canSendRegularMail || can('contacts.export') || $showCare): ?>
        <div class="addressbook-tools">
            <?php if ($canSendRegularMail): ?>
                <a class="ghost-button" href="<?= e(url('/rundmail?' . http_build_query(array_merge($filters, ['from' => 'filter'])))) ?>"><?= icon('mail') ?><span>Rundmail an diese Liste</span></a>
            <?php endif; ?>

            <?php if (can('contacts.export')): ?>
                <details class="tool-menu">
                    <summary class="ghost-button"><?= icon('upload') ?><span>Exportieren</span></summary>
                    <div class="tool-menu-body">
                        <a href="<?= e(url('/contacts/export?' . http_build_query($filters))) ?>">Als CSV-Tabelle</a>
                        <a href="<?= e(url('/contacts/vcard?' . http_build_query($filters))) ?>">Als vCard (Adressbuch)</a>
                    </div>
                </details>
            <?php endif; ?>

            <?php if ($showCare): ?>
                <details class="tool-menu">
                    <summary class="ghost-button"><?= icon('check') ?><span>Datenpflege<?= $careCount > 0 ? ' (' . $careCount . ')' : '' ?></span></summary>
                    <div class="tool-menu-body">
                        <?php if ($canManage): ?>
                            <a href="<?= e(url('/vollstaendigkeit' . (($filters['category_id'] ?? '') !== '' ? '?category_id=' . rawurlencode((string) $filters['category_id']) : ''))) ?>">Vollständigkeit prüfen</a>
                        <?php endif; ?>
                        <?php if ($canManage && ($duplicateCount ?? 0) > 0): ?>
                            <a href="<?= e(url('/kontakte/dubletten')) ?>">Mögliche Doppel-Einträge (<?= (int) $duplicateCount ?>)</a>
                        <?php endif; ?>
                        <?php if (can('contacts.delete')): ?>
                            <a href="<?= e(url('/kontakte/archiv')) ?>">Archiv &amp; Papierkorb<?= $retiredCount > 0 ? ' (' . (int) $retiredCount . ')' : '' ?></a>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php
$contactCount = count($contacts);
$emailCount = 0;
$phoneCount = 0;
$activeTagIds = array_map('intval', (array) ($filters['tag_ids'] ?? []));
foreach ($contacts as $contact) {
    $emailCount += count($contact['emails']);
    $phoneCount += count($contact['phones']);
}
?>
<section class="hero-card">
    <div class="hero-row">
        <div>
            <p class="eyebrow">Kontaktverwaltung</p>
            <h2>Alle Kontakte an einem Ort</h2>
            <p class="muted">Schnell filtern, markieren, exportieren oder für Mailings verwenden.</p>
        </div>
        <?php if (can('contacts.manage')): ?>
            <a class="button-link" href="<?= e(url('/contacts/create')) ?>"><?= icon('plus') ?><span>Neuen Kontakt anlegen</span></a>
        <?php endif; ?>
    </div>

    <div class="stats-grid">
        <article class="stat-card">
            <?= icon('contacts') ?>
            <span class="stat-label">Kontakte</span>
            <strong><?= e((string) $contactCount) ?></strong>
        </article>
        <article class="stat-card">
            <?= icon('mail') ?>
            <span class="stat-label">E-Mail-Adressen</span>
            <strong><?= e((string) $emailCount) ?></strong>
        </article>
        <article class="stat-card">
            <?= icon('user') ?>
            <span class="stat-label">Telefonnummern</span>
            <strong><?= e((string) $phoneCount) ?></strong>
        </article>
    </div>

    <form method="get" action="<?= e(url('/')) ?>" class="filter-grid">
        <label>
            <span>Suche</span>
            <input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Name oder Geburtsname">
        </label>
        <label>
            <span>Kategorie</span>
            <select name="category_id">
                <option value="">Alle Kategorien</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e((string) $category['id']) ?>" <?= (string) ($filters['category_id'] ?? '') === (string) $category['id'] ? 'selected' : '' ?>>
                        <?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Sortierung</span>
            <select name="sort">
                <option value="nachname" <?= ($filters['sort'] ?? 'nachname') === 'nachname' ? 'selected' : '' ?>>Name</option>
                <option value="category_name" <?= ($filters['sort'] ?? '') === 'category_name' ? 'selected' : '' ?>>Kategorie</option>
            </select>
        </label>
        <label>
            <span>Richtung</span>
            <select name="direction">
                <option value="asc" <?= ($filters['direction'] ?? 'asc') === 'asc' ? 'selected' : '' ?>>A bis Z</option>
                <option value="desc" <?= ($filters['direction'] ?? '') === 'desc' ? 'selected' : '' ?>>Z bis A</option>
            </select>
        </label>
        <div class="filter-tags">
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
        <div class="filter-actions">
            <button type="submit">Filtern</button>
            <?php if (can('contacts.export')): ?>
                <a class="ghost-button" href="<?= e(url('/contacts/export?' . http_build_query($filters))) ?>">CSV exportieren</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="panel">
        <div class="panel-head">
        <div>
            <h3>Kontaktliste</h3>
            <p class="muted">Auswahl, Kopieren und Mailing starten direkt aus der Übersicht.</p>
        </div>
        <div class="selection-status" id="selectionStatus">Noch nichts ausgewählt</div>
    </div>

    <form id="contactSelectionForm" method="post" action="<?= e(url('/mail/compose')) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <div class="bulk-layout">
            <div class="bulk-card">
                <span class="bulk-title">Schnellauswahl</span>
                <div class="toolbar-actions">
                    <button type="button" data-select="all"><?= icon('check-double') ?><span>Alle auswählen</span></button>
                    <button type="button" class="ghost-button" data-select="none"><?= icon('reset') ?><span>Auswahl löschen</span></button>
                </div>
                <?php if ($categories !== []): ?>
                    <div class="quick-category-list">
                        <?php foreach ($categories as $category): ?>
                            <button type="button" class="ghost-button" data-select-category="<?= e((string) $category['id']) ?>"><?= e($category['name']) ?> auswählen</button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($tags !== []): ?>
                    <div class="quick-category-list">
                        <?php foreach ($tags as $tag): ?>
                            <button type="button" class="ghost-button" data-select-tag="<?= e((string) $tag['id']) ?>"><?= e($tag['name']) ?> markieren</button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bulk-card accent">
                <span class="bulk-title">Aktionen für Auswahl</span>
                <div class="toolbar-actions">
                    <?php if (can('contacts.copy_emails')): ?>
                        <button type="button" id="copyEmailsButton"><?= icon('copy') ?><span>E-Mail-Adressen kopieren</span></button>
                    <?php endif; ?>
                    <?php if (can('mail.send')): ?>
                        <button type="submit" class="button-link"><?= icon('edit') ?><span>E-Mail verfassen</span></button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="contacts-grid">
            <?php foreach ($contacts as $contact): ?>
                <article class="contact-card" data-category-id="<?= e((string) ($contact['category_id'] ?? '')) ?>" data-tag-ids="<?= e(implode(',', array_map(static fn (array $tag): string => (string) $tag['id'], $contact['tags'] ?? []))) ?>">
                    <label class="contact-select">
                        <input type="checkbox" name="selected_contacts[]" value="<?= e((string) $contact['id']) ?>" data-contact-checkbox>
                        <span>Auswählen</span>
                    </label>
                    <div class="contact-head">
                        <div>
                            <h3><?= e($contact['vorname'] . ' ' . $contact['nachname']) ?></h3>
                            <?php if (!empty($contact['geburtsname'])): ?><p class="muted">Geburtsname: <?= e($contact['geburtsname']) ?></p><?php endif; ?>
                        </div>
                        <span class="tag"><?= e($contact['category_name'] ?: 'Ohne Kategorie') ?></span>
                    </div>
                    <div class="tag-cluster">
                        <?php foreach ($contact['tags'] as $tag): ?>
                            <span class="tag tag-secondary"><?= e($tag['name']) ?></span>
                        <?php endforeach; ?>
                        <?php if (!empty($contact['linked_user'])): ?>
                            <span class="tag tag-account<?= (int) $contact['linked_user']['is_active'] === 1 ? ' is-active' : '' ?>">
                                <?= e($contact['linked_user']['role_name']) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="contact-body">
                        <div class="contact-meta-list">
                            <p><?= icon('location') ?><span><strong>Adresse</strong><?= e($contact['strasse']) ?>, <?= e($contact['plz']) ?> <?= e($contact['ort']) ?></span></p>
                            <p><?= icon('globe') ?><span><?= e($contact['land'] ?: 'Deutschland') ?></span></p>
                            <p class="muted"><?= icon('cake') ?><span><?= e($contact['geburtstag'] ?: 'Kein Geburtstag hinterlegt') ?></span></p>
                        </div>

                        <div>
                            <strong>E-Mail-Adressen</strong>
                            <ul class="mini-list">
                                <?php foreach ($contact['emails'] as $email): ?>
                                    <li data-email="<?= e($email['email']) ?>"><a href="mailto:<?= e($email['email']) ?>"><?= e(($email['label'] ? $email['label'] . ': ' : '') . $email['email']) ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <div>
                            <strong>Telefonnummern</strong>
                            <ul class="mini-list">
                                <?php foreach ($contact['phones'] as $phone): ?>
                                    <li><a href="tel:<?= e($phone['phone']) ?>"><?= e($phone['label'] . ': ' . $phone['phone']) ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <?php if (!empty($contact['linked_user'])): ?>
                            <div class="account-summary">
                                <strong>Login</strong>
                                <p><?= e($contact['linked_user']['email']) ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($contact['notizen'])): ?><p class="note"><?= e($contact['notizen']) ?></p><?php endif; ?>
                    </div>

                    <?php if (can('contacts.manage')): ?>
                        <div class="card-actions">
                            <a class="ghost-button" href="<?= e(url('/contacts/edit?id=' . $contact['id'])) ?>"><?= icon('edit') ?><span>Bearbeiten</span></a>
                            <?php if (can('contacts.delete')): ?>
                                <form method="post" action="<?= e(url('/contacts/delete')) ?>" onsubmit="return confirm('Kontakt wirklich löschen?');">
                                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                    <input type="hidden" name="id" value="<?= e((string) $contact['id']) ?>">
                                    <button type="submit" class="danger-button"><?= icon('trash') ?><span>Löschen</span></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </form>
</section>

<?php if (can('categories.manage')): ?>
    <section class="panel narrow stack">
        <div>
            <h3>Kategorie ergänzen</h3>
            <form method="post" action="<?= e(url('/categories/store')) ?>" class="inline-form">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <input type="text" name="name" placeholder="Neue Kategorie" required>
                <button type="submit">Speichern</button>
            </form>
        </div>
        <div>
            <h3>Tag ergänzen</h3>
            <form method="post" action="<?= e(url('/tags/store')) ?>" class="inline-form">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <input type="text" name="name" placeholder="Neuer Tag" required>
                <button type="submit">Speichern</button>
            </form>
        </div>
    </section>
<?php endif; ?>

<?php
/** @var array<string,mixed> $group */
/** @var list<int> $memberIds */
/** @var list<array<string,mixed>> $contacts */
$members = $group['members'];

// Kontakte für den Mitglieder-Picker nach Kategorie gruppieren.
$byCategory = [];
foreach ($contacts as $contact) {
    $byCategory[(string) ($contact['category_name'] ?: 'Ohne Kategorie')][] = $contact;
}
ksort($byCategory);
?>
<p class="detail-backlink"><a href="<?= e(url('/verwaltung/gruppen')) ?>"><?= icon('chevron-right') ?>Zurück zu den Gruppen</a></p>

<header class="page-head">
    <p class="eyebrow">Gruppe</p>
    <h1><?= e($group['name']) ?></h1>
    <p class="muted">
        <?= count($members) ?> <?= count($members) === 1 ? 'Mitglied' : 'Mitglieder' ?>
        <?php if ((int) $group['is_open'] === 1): ?> · offen zum Selbst-Beitritt<?php endif; ?>
    </p>
</header>

<form method="post" action="<?= e(url('/verwaltung/gruppen/speichern')) ?>" class="contact-detail-form">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="id" value="<?= e((string) $group['id']) ?>">

    <section class="detail-card">
        <h2>Eckdaten</h2>
        <div class="form-grid">
            <label class="full-width"><span>Name <span class="required-marker" aria-hidden="true">*</span></span><input type="text" name="name" value="<?= e($group['name']) ?>" required></label>
            <label class="full-width"><span>Beschreibung</span><textarea name="description" rows="2" maxlength="500"><?= e((string) ($group['description'] ?? '')) ?></textarea></label>
            <label class="checkbox-row full-width">
                <input type="checkbox" name="is_open" value="1" <?= (int) $group['is_open'] === 1 ? 'checked' : '' ?>>
                <span>Offene Gruppe – jede angemeldete Person darf selbst bei- und austreten</span>
            </label>
        </div>
        <div class="toolbar-actions">
            <button type="submit">Speichern</button>
        </div>
    </section>
</form>

<section class="detail-card">
    <h2>Mitglieder</h2>
    <?php if ($members === []): ?>
        <p class="field-hint">Noch niemand in der Gruppe. Wähle die Personen aus dem Adressbuch.</p>
    <?php else: ?>
        <ul class="group-member-list">
            <?php foreach ($members as $member): ?>
                <li>
                    <span><?= e(trim($member['vorname'] . ' ' . $member['nachname'])) ?></span>
                    <?php if (trim((string) ($member['email'] ?? '')) === ''): ?>
                        <span class="status-chip is-warn">keine Mail</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/verwaltung/gruppen/mitglieder')) ?>" data-participant-picker>
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="id" value="<?= e((string) $group['id']) ?>">

        <details class="admin-drawer"<?= $members === [] ? ' open' : '' ?>>
            <summary><span><?= icon('contacts') ?></span><span>Mitglieder wählen</span></summary>
            <div class="admin-drawer-body">
                <div class="participant-picker-tools">
                    <button type="button" class="linkish" data-pick="all">Alle</button>
                    <button type="button" class="linkish" data-pick="none">Keine</button>
                    <?php foreach ($byCategory as $catName => $catContacts): ?>
                        <button type="button" class="linkish" data-pick-category="<?= e($catName) ?>">+ <?= e($catName) ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="participant-list">
                    <?php foreach ($byCategory as $catName => $catContacts): ?>
                        <p class="participant-group"><?= e($catName) ?></p>
                        <?php foreach ($catContacts as $contact): ?>
                            <label class="participant-option" data-category="<?= e($catName) ?>">
                                <input type="checkbox" name="contact_ids[]" value="<?= e((string) $contact['id']) ?>" <?= in_array((int) $contact['id'], $memberIds, true) ? 'checked' : '' ?>>
                                <span><?= e(trim($contact['vorname'] . ' ' . $contact['nachname'])) ?><?php if (($contact['emails'] ?? []) === []): ?> <span class="status-chip is-warn">keine Mail</span><?php endif; ?></span>
                            </label>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
                <div class="toolbar-actions">
                    <button type="submit">Mitgliederkreis speichern</button>
                </div>
            </div>
        </details>
    </form>
</section>

<section class="detail-card detail-danger">
    <h2>Gruppe löschen</h2>
    <p class="muted">Entfernt die Gruppe und alle Mitgliedszuordnungen. Die Kontakte selbst bleiben unberührt.</p>
    <form method="post" action="<?= e(url('/verwaltung/gruppen/loeschen')) ?>" data-confirm="Gruppe „<?= e($group['name']) ?>“ endgültig löschen?">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="id" value="<?= e((string) $group['id']) ?>">
        <button type="submit" class="danger-button"><?= icon('trash') ?><span>Löschen</span></button>
    </form>
</section>

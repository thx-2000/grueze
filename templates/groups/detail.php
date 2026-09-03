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
        <p class="field-hint">Die <strong>Gruppenleitung</strong> darf diese Gruppe verwalten (Mitglieder, Nachricht, Abstimmungen) – auch ohne globales Recht.</p>
        <ul class="group-member-rows">
            <?php foreach ($members as $member): ?>
                <?php $isLead = ($member['role'] ?? 'member') === 'lead'; ?>
                <li>
                    <span class="group-member-name">
                        <?= e(trim($member['vorname'] . ' ' . $member['nachname'])) ?>
                        <?php if ($isLead): ?><span class="events-status is-open">Leitung</span><?php endif; ?>
                        <?php if (trim((string) ($member['email'] ?? '')) === ''): ?><span class="status-chip is-warn">keine Mail</span><?php endif; ?>
                    </span>
                    <form method="post" action="<?= e(url('/verwaltung/gruppen/leitung')) ?>">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="id" value="<?= e((string) $group['id']) ?>">
                        <input type="hidden" name="contact_id" value="<?= e((string) $member['contact_id']) ?>">
                        <input type="hidden" name="role" value="<?= $isLead ? 'member' : 'lead' ?>">
                        <button type="submit" class="linkish"><?= $isLead ? 'Leitung entfernen' : 'Zur Leitung machen' ?></button>
                    </form>
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

<section class="detail-card">
    <h2>Gruppen-Nachricht</h2>
    <?php if ((int) ($group['mail_locked'] ?? 0) === 1): ?>
        <p class="muted">Der Gruppen-Versand ist zurzeit <strong>gesperrt</strong>. Mitglieder und Team können nicht an die Gruppe schreiben (Admins schon).</p>
        <div class="toolbar-actions">
            <form method="post" action="<?= e(url('/verwaltung/gruppen/sperre')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" value="<?= e((string) $group['id']) ?>">
                <input type="hidden" name="lock" value="0">
                <button type="submit" class="ghost-button"><?= icon('unlock') ?><span>Versand wieder freigeben</span></button>
            </form>
        </div>
    <?php else: ?>
        <p class="muted">Jedes Mitglied darf der Gruppe eine Nachricht schicken (weiche Grenze: 2 pro Person und Tag). Bei Missbrauch hier stoppen.</p>
        <div class="toolbar-actions">
            <a class="ghost-button" href="<?= e(url('/gruppen/nachricht?id=' . (int) $group['id'])) ?>"><?= icon('mail') ?><span>Nachricht schreiben</span></a>
            <a class="ghost-button" href="<?= e(url('/gruppen/abstimmungen?id=' . (int) $group['id'])) ?>"><?= icon('check') ?><span>Abstimmungen</span></a>
            <form method="post" action="<?= e(url('/verwaltung/gruppen/sperre')) ?>" data-confirm="Gruppen-Versand für „<?= e($group['name']) ?>“ sperren?">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" value="<?= e((string) $group['id']) ?>">
                <input type="hidden" name="lock" value="1">
                <button type="submit" class="ghost-button"><?= icon('lock') ?><span>Versand sperren</span></button>
            </form>
        </div>
    <?php endif; ?>
</section>

<?php if (!empty($canDelete)): ?>
    <section class="detail-card detail-danger">
        <h2>Gruppe löschen</h2>
        <p class="muted">Entfernt die Gruppe und alle Mitgliedszuordnungen. Die Kontakte selbst bleiben unberührt.</p>
        <form method="post" action="<?= e(url('/verwaltung/gruppen/loeschen')) ?>" data-confirm="Gruppe „<?= e($group['name']) ?>“ endgültig löschen?">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= e((string) $group['id']) ?>">
            <button type="submit" class="danger-button"><?= icon('trash') ?><span>Löschen</span></button>
        </form>
    </section>
<?php endif; ?>

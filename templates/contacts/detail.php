<?php
$editing = $contact !== null;
$oldInput = $_SESSION['_old'] ?? [];
$hasOld = $oldInput !== [];
$history = $history ?? [];

$defaults = [
    'vorname' => old('vorname'),
    'nachname' => old('nachname'),
    'geburtsname' => old('geburtsname'),
    'geschlecht' => old('geschlecht'),
    'category_id' => old('category_id'),
    'geburtstag' => old('geburtstag'),
    'strasse' => old('strasse'),
    'plz' => old('plz'),
    'ort' => old('ort'),
    'land' => old('land', config('defaults.country', 'Deutschland')),
    'notizen' => old('notizen'),
    'tag_ids' => old('tag_ids', []),
    'emails' => old('emails', [['email' => '', 'label' => '']]),
    'phones' => old('phones', [['phone' => '', 'label' => 'Mobil']]),
];
$values = $editing ? $contact : $defaults;
if ($editing) {
    if ($hasOld) {
        foreach (['vorname', 'nachname', 'geburtsname', 'geschlecht', 'category_id', 'geburtstag', 'strasse', 'plz', 'ort', 'land', 'notizen'] as $field) {
            $values[$field] = $defaults[$field];
        }
    }
    $values['emails'] = old('emails', $contact['emails'] ?: [['email' => '', 'label' => '']]);
    $values['phones'] = old('phones', $contact['phones'] ?: [['phone' => '', 'label' => 'Mobil']]);
    $values['tag_ids'] = $hasOld
        ? (array) ($oldInput['tag_ids'] ?? [])
        : array_map(static fn (array $tag): int => (int) $tag['id'], $contact['tags'] ?? []);
}

// Alt-Importdaten enthalten teils "mailto:"/"tel:"-Präfixe – für die Anzeige säubern.
foreach (($values['emails'] ?? []) as $i => $em) {
    $values['emails'][$i]['email'] = preg_replace('/^\s*mailto:\s*/i', '', (string) ($em['email'] ?? ''));
}
foreach (($values['phones'] ?? []) as $i => $ph) {
    $values['phones'][$i]['phone'] = preg_replace('/^\s*tel:\s*/i', '', (string) ($ph['phone'] ?? ''));
}

$linkedUser = $editing ? ($contact['linked_user'] ?? null) : null;
$loginEnabled = can('users.manage') && ($hasOld ? array_key_exists('login_enabled', $oldInput) : $linkedUser !== null);
$loginEmail = preg_replace('/^\s*mailto:\s*/i', '', $hasOld
    ? (string) ($oldInput['login_email'] ?? '')
    : (string) ($linkedUser['email'] ?? ($values['emails'][0]['email'] ?? '')));
$roleId = $hasOld ? (string) ($oldInput['role_id'] ?? '') : (string) ($linkedUser['role_id'] ?? '');

$fullName = trim(($values['vorname'] ?? '') . ' ' . ($values['nachname'] ?? ''));
$hasEmail = $editing && ($contact['emails'] ?? []) !== [];
$hasPhone = $editing && ($contact['phones'] ?? []) !== [];

$actionLabel = static fn (string $a): string => match ($a) {
    'created' => 'angelegt', 'deleted' => 'gelöscht', default => 'geändert',
};
?>
<p class="detail-backlink"><a href="<?= e(url('/kontakte')) ?>"><?= icon('chevron-right') ?>Zurück zum Adressbuch</a></p>

<header class="contact-detail-head">
    <p class="eyebrow"><?= $editing ? 'Kontakt' : 'Neuer Kontakt' ?></p>
    <h1><?= $fullName !== '' ? e($fullName) : 'Kontakt anlegen' ?><?php if ($editing && ($bn = format_birth_name($contact)) !== ''): ?>
        <span class="birth-name-inline"><?= e($bn) ?></span>
    <?php endif; ?></h1>
    <?php if ($editing): ?>
        <div class="contact-detail-meta">
            <?php if ($hasEmail && $hasPhone): ?>
                <span class="status-chip is-ok">vollständig</span>
            <?php else: ?>
                <?php if (!$hasEmail): ?><span class="status-chip is-warn">Mail fehlt</span><?php endif; ?>
                <?php if (!$hasPhone): ?><span class="status-chip is-warn">Tel. fehlt</span><?php endif; ?>
            <?php endif; ?>
            <?php if (trim((string) ($contact['category_name'] ?? '')) !== ''): ?>
                <span class="table-pill"><?= e($contact['category_name']) ?></span>
            <?php endif; ?>
            <?php if (!empty($contact['created_at'])): ?>
                <span class="muted">im Adressbuch seit <?= e(format_date(substr((string) $contact['created_at'], 0, 10))) ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</header>

<form method="post" action="<?= e(url($editing ? '/contacts/update' : '/contacts/store')) ?>" enctype="multipart/form-data" class="contact-detail-form" data-detail-form>
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= e((string) $contact['id']) ?>"><?php endif; ?>

    <section class="detail-card">
        <h2>Stammdaten</h2>
        <p class="field-hint">Mit <span aria-hidden="true">*</span> markierte Felder sind Pflicht.</p>
        <div class="form-grid">
            <label><span>Vorname <span class="required-marker" aria-hidden="true">*</span></span><input type="text" name="vorname" value="<?= e($values['vorname'] ?? '') ?>" required></label>
            <label><span>Nachname <span class="required-marker" aria-hidden="true">*</span></span><input type="text" name="nachname" value="<?= e($values['nachname'] ?? '') ?>" required></label>
            <label><span>Geburtsname</span><input type="text" name="geburtsname" value="<?= e($values['geburtsname'] ?? '') ?>"></label>
            <label>
                <span>Geschlecht</span>
                <select name="geschlecht">
                    <option value="">Nicht gesetzt</option>
                    <option value="m" <?= ($values['geschlecht'] ?? '') === 'm' ? 'selected' : '' ?>>Männlich (Lieber)</option>
                    <option value="w" <?= ($values['geschlecht'] ?? '') === 'w' ? 'selected' : '' ?>>Weiblich (Liebe)</option>
                </select>
            </label>
            <label><span>Geburtstag</span><input type="date" name="geburtstag" value="<?= e($values['geburtstag'] ?? '') ?>"></label>
            <label>
                <span>Kategorie</span>
                <select name="category_id">
                    <option value="">Keine Kategorie</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e((string) $category['id']) ?>" <?= (string) ($values['category_id'] ?? '') === (string) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="full-width" role="group" aria-label="Tags">
                <span>Tags</span>
                <div class="tag-picker">
                    <?php foreach ($tags as $tag): ?>
                        <?php $selected = in_array((int) $tag['id'], array_map('intval', (array) ($values['tag_ids'] ?? [])), true); ?>
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
        </div>
    </section>

    <section class="detail-card">
        <h2>Adresse</h2>
        <div class="form-grid">
            <label class="full-width"><span>Straße</span><input type="text" name="strasse" value="<?= e($values['strasse'] ?? '') ?>"></label>
            <label><span>PLZ</span><input type="text" name="plz" value="<?= e($values['plz'] ?? '') ?>"></label>
            <label><span>Ort</span><input type="text" name="ort" value="<?= e($values['ort'] ?? '') ?>"></label>
            <label><span>Land</span><input type="text" name="land" value="<?= e($values['land'] ?? '') ?>"></label>
            <label class="full-width">
                <span>Profilbild</span>
                <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp" aria-describedby="photoHint">
                <small class="field-hint" id="photoHint">JPG, PNG oder WEBP bis 2 MB.<?php if ($editing && !empty($contact['photo_path'])): ?> Aktuell ist ein Bild hinterlegt – ein neues ersetzt es.<?php endif; ?></small>
            </label>
        </div>
    </section>

    <section class="detail-card">
        <h2>Kontaktwege</h2>
        <div class="repeater-block">
            <div class="section-head">
                <h3>E-Mail-Adressen</h3>
                <button type="button" class="ghost-button icon-button" data-add-row="emails" aria-label="Weitere E-Mail-Adresse hinzufügen"><?= icon('plus') ?><span class="visually-hidden">Weitere E-Mail-Adresse hinzufügen</span></button>
            </div>
            <div id="emailsRepeater">
                <?php foreach (($values['emails'] ?? []) as $index => $email): ?>
                    <div class="repeater-row">
                        <input type="text" name="emails[<?= e((string) $index) ?>][label]" value="<?= e($email['label'] ?? '') ?>" placeholder="Label, z. B. privat" aria-label="Label für E-Mail-Adresse <?= e((string) ($index + 1)) ?>">
                        <input type="text" inputmode="email" name="emails[<?= e((string) $index) ?>][email]" value="<?= e($email['email'] ?? '') ?>" placeholder="name@example.com" aria-label="E-Mail-Adresse <?= e((string) ($index + 1)) ?>">
                        <button type="button" class="danger-button icon-button" data-remove-row aria-label="E-Mail-Adresse entfernen"><?= icon('trash') ?><span class="visually-hidden">E-Mail-Adresse entfernen</span></button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="repeater-block">
            <div class="section-head">
                <h3>Telefonnummern</h3>
                <button type="button" class="ghost-button icon-button" data-add-row="phones" aria-label="Weitere Telefonnummer hinzufügen"><?= icon('plus') ?><span class="visually-hidden">Weitere Telefonnummer hinzufügen</span></button>
            </div>
            <div id="phonesRepeater">
                <?php foreach (($values['phones'] ?? []) as $index => $phone): ?>
                    <div class="repeater-row">
                        <select name="phones[<?= e((string) $index) ?>][label]" aria-label="Art der Telefonnummer <?= e((string) ($index + 1)) ?>">
                            <?php foreach ($phoneLabels as $label): ?>
                                <option value="<?= e($label) ?>" <?= ($phone['label'] ?? '') === $label ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="phones[<?= e((string) $index) ?>][phone]" value="<?= e($phone['phone'] ?? '') ?>" placeholder="+49 …" aria-label="Telefonnummer <?= e((string) ($index + 1)) ?>">
                        <button type="button" class="danger-button icon-button" data-remove-row aria-label="Telefonnummer entfernen"><?= icon('trash') ?><span class="visually-hidden">Telefonnummer entfernen</span></button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="detail-card">
        <h2>Notizen</h2>
        <p class="detail-notes-hint" id="notesHint"><?= icon('lock') ?><span>Nur intern – für Mitglieder unsichtbar, auch für die Person selbst. Folgt zusätzlich der Rollen-Sichtbarkeit.</span></p>
        <textarea name="notizen" rows="4" aria-label="Notizen" aria-describedby="notesHint"><?= e($values['notizen'] ?? '') ?></textarea>
    </section>

    <?php if (can('users.manage')): ?>
        <section class="detail-card">
            <h2>Login &amp; Rolle</h2>
            <?php if ($linkedUser): ?>
                <p class="field-hint">Verknüpft: <?= e($linkedUser['email']) ?> als <?= e(role_label((string) $linkedUser['role_name'])) ?> ·
                    <?= (int) $linkedUser['is_active'] === 1 ? 'Login aktiv' : 'Login deaktiviert' ?>.</p>
            <?php endif; ?>
            <label class="toggle-row">
                <input type="checkbox" name="login_enabled" value="1" <?= $loginEnabled ? 'checked' : '' ?>>
                <span>Diesen Kontakt mit Login freischalten</span>
            </label>
            <div class="form-grid">
                <label>
                    <span>Login-E-Mail</span>
                    <input type="email" name="login_email" value="<?= e($loginEmail) ?>" placeholder="wird auch für Passwort-Reset verwendet">
                </label>
                <label>
                    <span>Rolle</span>
                    <select name="role_id">
                        <option value="">Rolle wählen</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= e((string) $role['id']) ?>" <?= $roleId === (string) $role['id'] ? 'selected' : '' ?>>
                                <?= e((string) (($role['label'] ?? '') !== '' ? $role['label'] : $role['name'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <?php if (!$linkedUser): ?>
                <p class="field-hint">Beim ersten Freischalten wird ein Erstpasswort erzeugt und nach dem Speichern einmalig eingeblendet.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <div class="detail-save-bar"<?= $editing ? ' hidden' : '' ?> data-save-bar>
        <span class="detail-save-hint"><?= $editing ? 'Ungespeicherte Änderungen.' : 'Neuen Kontakt anlegen.' ?></span>
        <div class="detail-save-actions">
            <?php if ($editing): ?>
                <button type="button" class="ghost-button" data-detail-reset>Verwerfen</button>
            <?php endif; ?>
            <button type="submit"><?= $editing ? 'Änderungen speichern' : 'Kontakt speichern' ?></button>
        </div>
    </div>
</form>

<?php if ($editing && can('audit.view')): ?>
    <section class="detail-card history-card">
        <h2>Änderungsverlauf</h2>
        <p class="muted">Nur für die Verwaltung sichtbar. Alte Werte bleiben hier nachvollziehbar.</p>
        <?php if ($history === []): ?>
            <p class="field-hint">Noch keine Änderungen aufgezeichnet.</p>
        <?php else: ?>
            <ol class="history-list">
                <?php foreach ($history as $entry): ?>
                    <li class="history-entry">
                        <p class="history-when">
                            <?= e(format_datetime($entry['created_at'])) ?> ·
                            <?= e($entry['user_name']) ?> ·
                            <?= e($actionLabel((string) $entry['action'])) ?>
                        </p>
                        <?php if (!empty($entry['changes'])): ?>
                            <ul class="history-changes">
                                <?php foreach ($entry['changes'] as $field => $change): ?>
                                    <li>
                                        <span class="history-field"><?= e((string) $field) ?></span>
                                        <span class="history-from"><?= e((string) ($change['from'] ?? '—')) ?></span>
                                        <span class="history-arrow" aria-hidden="true">→</span>
                                        <span class="history-to"><?= e((string) ($change['to'] ?? '—')) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php elseif (trim((string) ($entry['details'] ?? '')) !== ''): ?>
                            <p class="history-note"><?= e((string) $entry['details']) ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($editing && can('contacts.delete')): ?>
    <section class="detail-card detail-danger">
        <h2>Kontakt löschen</h2>
        <p class="muted">Entfernt den Kontakt dauerhaft. Ein verknüpfter Login wird deaktiviert.</p>
        <form method="post" action="<?= e(url('/contacts/delete')) ?>" onsubmit="return confirm('Kontakt „<?= e($fullName) ?>“ wirklich löschen?');">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= e((string) $contact['id']) ?>">
            <button type="submit" class="danger-button"><?= icon('trash') ?><span>Kontakt löschen</span></button>
        </form>
    </section>
<?php endif; ?>

<template id="emailRowTemplate">
    <div class="repeater-row">
        <input type="text" data-name="label" placeholder="Label, z. B. privat" aria-label="Label für weitere E-Mail-Adresse">
        <input type="text" inputmode="email" data-name="email" placeholder="name@example.com" aria-label="Weitere E-Mail-Adresse">
        <button type="button" class="danger-button icon-button" data-remove-row aria-label="E-Mail-Adresse entfernen"><?= icon('trash') ?><span class="visually-hidden">E-Mail-Adresse entfernen</span></button>
    </div>
</template>

<template id="phoneRowTemplate">
    <div class="repeater-row">
        <select data-name="label" aria-label="Art der weiteren Telefonnummer">
            <?php foreach ($phoneLabels as $label): ?>
                <option value="<?= e($label) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" data-name="phone" placeholder="+49 …" aria-label="Weitere Telefonnummer">
        <button type="button" class="danger-button icon-button" data-remove-row aria-label="Telefonnummer entfernen"><?= icon('trash') ?><span class="visually-hidden">Telefonnummer entfernen</span></button>
    </div>
</template>

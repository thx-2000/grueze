<?php
$editing = $contact !== null;
$oldInput = $_SESSION['_old'] ?? [];
$hasOld = $oldInput !== [];
$defaults = [
    'vorname' => old('vorname'),
    'nachname' => old('nachname'),
    'geburtsname' => old('geburtsname'),
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
        foreach (['vorname', 'nachname', 'geburtsname', 'category_id', 'geburtstag', 'strasse', 'plz', 'ort', 'land', 'notizen'] as $field) {
            $values[$field] = $defaults[$field];
        }
    }
    $values['emails'] = old('emails', $contact['emails'] ?: [['email' => '', 'label' => '']]);
    $values['phones'] = old('phones', $contact['phones'] ?: [['phone' => '', 'label' => 'Mobil']]);
    $values['tag_ids'] = $hasOld
        ? (array) ($oldInput['tag_ids'] ?? [])
        : array_map(static fn (array $tag): int => (int) $tag['id'], $contact['tags'] ?? []);
}

$linkedUser = $editing ? ($contact['linked_user'] ?? null) : null;
$loginEnabled = can('users.manage') && ($hasOld ? array_key_exists('login_enabled', $oldInput) : $linkedUser !== null);
$loginEmail = $hasOld
    ? (string) ($oldInput['login_email'] ?? '')
    : (string) ($linkedUser['email'] ?? ($values['emails'][0]['email'] ?? ''));
$roleId = $hasOld ? (string) ($oldInput['role_id'] ?? '') : (string) ($linkedUser['role_id'] ?? '');
?>
<section class="hero-card">
    <div class="hero-row">
        <div>
            <p class="eyebrow"><?= $editing ? 'Kontakt bearbeiten' : 'Neuer Kontakt' ?></p>
            <h2><?= $editing ? 'Kontaktdaten aktualisieren' : 'Kontakt anlegen' ?></h2>
            <p class="muted">Stammdaten, Kontaktwege und Notizen in einem Schritt pflegen.</p>
        </div>
        <a class="ghost-button" href="<?= e(url('/')) ?>">Zurück zur Übersicht</a>
    </div>

    <form method="post" action="<?= e(url($editing ? '/contacts/update' : '/contacts/store')) ?>" enctype="multipart/form-data" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= e((string) $contact['id']) ?>"><?php endif; ?>

        <section class="subsection-card">
            <div class="section-head">
                <div>
                    <h3>Persönliche Daten</h3>
                    <p class="muted">Name, Zuordnung und Basisinformationen.</p>
                </div>
            </div>
            <div class="form-grid">
                <label><span>Vorname</span><input type="text" name="vorname" value="<?= e($values['vorname'] ?? '') ?>" required></label>
                <label><span>Nachname</span><input type="text" name="nachname" value="<?= e($values['nachname'] ?? '') ?>" required></label>
                <label><span>Geburtsname</span><input type="text" name="geburtsname" value="<?= e($values['geburtsname'] ?? '') ?>"></label>
                <label>
                    <span>Kategorie</span>
                    <select name="category_id">
                        <option value="">Keine Kategorie</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e((string) $category['id']) ?>" <?= (string) ($values['category_id'] ?? '') === (string) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><span>Geburtstag</span><input type="date" name="geburtstag" value="<?= e($values['geburtstag'] ?? '') ?>"></label>
                <label><span>Land</span><input type="text" name="land" value="<?= e($values['land'] ?? '') ?>"></label>
                <div class="full-width">
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

        <section class="subsection-card">
            <div class="section-head">
                <div>
                    <h3>Adresse und Profil</h3>
                    <p class="muted">Postanschrift, Bild und ergänzende Hinweise.</p>
                </div>
            </div>
            <div class="form-grid">
                <label class="full-width"><span>Straße</span><input type="text" name="strasse" value="<?= e($values['strasse'] ?? '') ?>"></label>
                <label><span>PLZ</span><input type="text" name="plz" value="<?= e($values['plz'] ?? '') ?>"></label>
                <label><span>Ort</span><input type="text" name="ort" value="<?= e($values['ort'] ?? '') ?>"></label>
                <label class="full-width">
                    <span>Profilbild</span>
                    <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp">
                    <small class="field-hint">Erlaubt sind JPG, PNG und WEBP bis 2 MB.</small>
                </label>
                <label class="full-width"><span>Notizen</span><textarea name="notizen" rows="4"><?= e($values['notizen'] ?? '') ?></textarea></label>
            </div>
        </section>

        <div class="repeaters">
            <section class="repeater-block">
                <div class="section-head">
                    <div>
                        <h3>E-Mail-Adressen</h3>
                        <p class="muted">Mehrere Adressen mit Label pflegen.</p>
                    </div>
                    <button
                        type="button"
                        class="ghost-button icon-button"
                        data-add-row="emails"
                        title="Weitere E-Mail-Adresse hinzufügen"
                        aria-label="Weitere E-Mail-Adresse hinzufügen"
                    ><?= icon('plus') ?><span class="visually-hidden">Weitere E-Mail-Adresse hinzufügen</span></button>
                </div>
                <div id="emailsRepeater">
                    <?php foreach (($values['emails'] ?? []) as $index => $email): ?>
                        <div class="repeater-row">
                            <input type="text" name="emails[<?= e((string) $index) ?>][label]" value="<?= e($email['label'] ?? '') ?>" placeholder="Label, z. B. privat">
                            <input type="email" name="emails[<?= e((string) $index) ?>][email]" value="<?= e($email['email'] ?? '') ?>" placeholder="name@example.com">
                            <button
                                type="button"
                                class="danger-button icon-button"
                                data-remove-row
                                title="E-Mail-Adresse entfernen"
                                aria-label="E-Mail-Adresse entfernen"
                            ><?= icon('trash') ?><span class="visually-hidden">E-Mail-Adresse entfernen</span></button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="repeater-block">
                <div class="section-head">
                    <div>
                        <h3>Telefonnummern</h3>
                        <p class="muted">Mobil, Festnetz oder weitere Nummern übersichtlich erfassen.</p>
                    </div>
                    <button
                        type="button"
                        class="ghost-button icon-button"
                        data-add-row="phones"
                        title="Weitere Telefonnummer hinzufügen"
                        aria-label="Weitere Telefonnummer hinzufügen"
                    ><?= icon('plus') ?><span class="visually-hidden">Weitere Telefonnummer hinzufügen</span></button>
                </div>
                <div id="phonesRepeater">
                    <?php foreach (($values['phones'] ?? []) as $index => $phone): ?>
                        <div class="repeater-row">
                            <select name="phones[<?= e((string) $index) ?>][label]">
                                <?php foreach ($phoneLabels as $label): ?>
                                    <option value="<?= e($label) ?>" <?= ($phone['label'] ?? '') === $label ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="phones[<?= e((string) $index) ?>][phone]" value="<?= e($phone['phone'] ?? '') ?>" placeholder="+49 ...">
                            <button
                                type="button"
                                class="danger-button icon-button"
                                data-remove-row
                                title="Telefonnummer entfernen"
                                aria-label="Telefonnummer entfernen"
                            ><?= icon('trash') ?><span class="visually-hidden">Telefonnummer entfernen</span></button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <?php if (can('users.manage')): ?>
            <section class="subsection-card">
                <div class="section-head">
                    <div>
                        <h3>Login und Rolle</h3>
                        <p class="muted">Ein Kontakt kann optional einen eigenen Zugang bekommen.</p>
                    </div>
                    <?php if ($linkedUser): ?>
                        <div class="account-badge<?= (int) $linkedUser['is_active'] === 1 ? ' is-active' : '' ?>">
                            <?= (int) $linkedUser['is_active'] === 1 ? 'Login aktiv' : 'Login deaktiviert' ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="account-panel">
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
                                        <?= e($role['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <p class="field-hint">Beim ersten Anlegen wird automatisch ein Erstpasswort erzeugt und nach dem Speichern eingeblendet.</p>
                    <?php if ($linkedUser): ?>
                        <p class="field-hint">Aktuell verknüpft: <?= e($linkedUser['email']) ?> als <?= e($linkedUser['role_name']) ?>.</p>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit"><?= $editing ? 'Änderungen speichern' : 'Kontakt speichern' ?></button>
            <?php if ($editing && can('contacts.delete')): ?>
                <button
                    type="submit"
                    class="danger-button"
                    formaction="<?= e(url('/contacts/delete')) ?>"
                    formmethod="post"
                    onclick="return confirm('Kontakt wirklich löschen?');"
                    title="Kontakt löschen"
                    aria-label="Kontakt löschen"
                ><?= icon('trash') ?><span>Kontakt löschen</span></button>
            <?php endif; ?>
            <a class="ghost-button" href="<?= e(url('/')) ?>">Zurück</a>
        </div>
    </form>
</section>

<template id="emailRowTemplate">
    <div class="repeater-row">
        <input type="text" data-name="label" placeholder="Label, z. B. privat">
        <input type="email" data-name="email" placeholder="name@example.com">
        <button
            type="button"
            class="danger-button icon-button"
            data-remove-row
            title="E-Mail-Adresse entfernen"
            aria-label="E-Mail-Adresse entfernen"
        ><?= icon('trash') ?><span class="visually-hidden">E-Mail-Adresse entfernen</span></button>
    </div>
</template>

<template id="phoneRowTemplate">
    <div class="repeater-row">
        <select data-name="label">
            <?php foreach ($phoneLabels as $label): ?>
                <option value="<?= e($label) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" data-name="phone" placeholder="+49 ...">
        <button
            type="button"
            class="danger-button icon-button"
            data-remove-row
            title="Telefonnummer entfernen"
            aria-label="Telefonnummer entfernen"
        ><?= icon('trash') ?><span class="visually-hidden">Telefonnummer entfernen</span></button>
    </div>
</template>

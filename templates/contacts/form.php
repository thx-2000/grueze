<?php
$editing = $contact !== null;
$values = $contact ?? [
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
    'emails' => old('emails', [['email' => '', 'label' => '']]),
    'phones' => old('phones', [['phone' => '', 'label' => 'Mobil']]),
];
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
                <label class="full-width"><span>Straße</span><input type="text" name="strasse" value="<?= e($values['strasse'] ?? '') ?>" required></label>
                <label><span>PLZ</span><input type="text" name="plz" value="<?= e($values['plz'] ?? '') ?>" required></label>
                <label><span>Ort</span><input type="text" name="ort" value="<?= e($values['ort'] ?? '') ?>" required></label>
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
                    <button type="button" class="ghost-button" data-add-row="emails">Weitere Adresse</button>
                </div>
                <div id="emailsRepeater">
                    <?php foreach (($values['emails'] ?? []) as $index => $email): ?>
                        <div class="repeater-row">
                            <input type="text" name="emails[<?= e((string) $index) ?>][label]" value="<?= e($email['label'] ?? '') ?>" placeholder="Label, z. B. privat">
                            <input type="email" name="emails[<?= e((string) $index) ?>][email]" value="<?= e($email['email'] ?? '') ?>" placeholder="name@example.com">
                            <button type="button" class="danger-button" data-remove-row>Entfernen</button>
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
                    <button type="button" class="ghost-button" data-add-row="phones">Weitere Nummer</button>
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
                            <button type="button" class="danger-button" data-remove-row>Entfernen</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <div class="form-actions">
            <button type="submit"><?= $editing ? 'Änderungen speichern' : 'Kontakt speichern' ?></button>
            <a class="ghost-button" href="<?= e(url('/')) ?>">Zurück</a>
        </div>
    </form>
</section>

<template id="emailRowTemplate">
    <div class="repeater-row">
        <input type="text" data-name="label" placeholder="Label, z. B. privat">
        <input type="email" data-name="email" placeholder="name@example.com">
        <button type="button" class="danger-button" data-remove-row>Entfernen</button>
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
        <button type="button" class="danger-button" data-remove-row>Entfernen</button>
    </div>
</template>

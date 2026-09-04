<?php
/** @var bool $invalid */
$invalid = $invalid ?? true;
?>
<section class="vote-page">
<?php if ($invalid): ?>
    <header class="vote-head">
        <p class="eyebrow">Daten-Check</p>
        <h1>Link nicht gültig</h1>
    </header>
    <p class="vote-alert">
        <span>Dieser Link ist abgelaufen oder wurde zurückgezogen. Bitte wende dich an die Person,
        die dir den Link geschickt hat – sie kann einen neuen erzeugen.</span>
    </p>
</section>
<?php return; ?>
<?php endif; ?>

<?php
$phoneLabels = $phoneLabels ?? [];
$saved = !empty($saved);
$oldInput = $_SESSION['_old'] ?? [];
$hasOld = $oldInput !== [];

$field = static function (string $key, $fallback = '') use ($hasOld, $oldInput, $contact) {
    if ($hasOld) {
        return (string) ($oldInput[$key] ?? '');
    }

    return (string) ($contact[$key] ?? $fallback);
};

$emails = $hasOld ? (array) ($oldInput['emails'] ?? []) : ($contact['emails'] ?? []);
if ($emails === []) {
    $emails = [['email' => '', 'label' => '']];
}
$phones = $hasOld ? (array) ($oldInput['phones'] ?? []) : ($contact['phones'] ?? []);
if ($phones === []) {
    $phones = [['phone' => '', 'label' => $phoneLabels[0] ?? 'Mobil']];
}
foreach ($emails as $i => $entry) {
    $emails[$i]['email'] = preg_replace('/^\s*mailto:\s*/i', '', (string) ($entry['email'] ?? ''));
}
foreach ($phones as $i => $entry) {
    $phones[$i]['phone'] = preg_replace('/^\s*tel:\s*/i', '', (string) ($entry['phone'] ?? ''));
}
$vorname = trim((string) ($contact['vorname'] ?? ''));
?>
    <header class="vote-head">
        <p class="eyebrow">Daten-Check</p>
        <h1><?= $vorname !== '' ? 'Hallo ' . e($vorname) . '!' : 'Deine Angaben' ?></h1>
        <p class="vote-description">Bitte schau kurz durch, ob deine Angaben noch stimmen, und ändere, was nicht mehr passt. Am Ende auf „Speichern".</p>
    </header>

    <?php if ($saved): ?>
        <p class="vote-hello"><?= icon('check') ?> <strong>Gespeichert – danke fürs Aktuell-Halten.</strong> Du kannst über denselben Link jederzeit noch etwas ändern, solange er gültig ist.</p>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/meine-daten')) ?>" class="contact-detail-form" data-detail-form>
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="token" value="<?= e((string) $token) ?>">

        <section class="detail-card">
            <h2>Zur Person</h2>
            <p class="field-hint">Mit <span aria-hidden="true">*</span> markierte Felder sind Pflicht.</p>
            <div class="form-grid">
                <label><span>Vorname <span class="required-marker" aria-hidden="true">*</span></span><input type="text" name="vorname" value="<?= e($field('vorname')) ?>" required></label>
                <label><span>Nachname <span class="required-marker" aria-hidden="true">*</span></span><input type="text" name="nachname" value="<?= e($field('nachname')) ?>" required></label>
                <label><span>Geburtsname</span><input type="text" name="geburtsname" value="<?= e($field('geburtsname')) ?>"></label>
                <label>
                    <span>Anrede</span>
                    <select name="geschlecht">
                        <?php $g = $field('geschlecht'); ?>
                        <option value="">Neutral – „Hallo …"</option>
                        <option value="w" <?= $g === 'w' ? 'selected' : '' ?>>„Liebe …"</option>
                        <option value="m" <?= $g === 'm' ? 'selected' : '' ?>>„Lieber …"</option>
                    </select>
                </label>
                <label><span>Geburtstag</span><input type="date" name="geburtstag" value="<?= e($field('geburtstag')) ?>"></label>
                <label><span>Beruf/Tätigkeit</span><input type="text" name="beruf" value="<?= e($field('beruf')) ?>" maxlength="160"></label>
                <label><span>Webseite</span><input type="text" name="webseite" value="<?= e($field('webseite')) ?>" inputmode="url" placeholder="https://…"></label>
            </div>
        </section>

        <section class="detail-card">
            <h2>Adresse</h2>
            <div class="form-grid">
                <label class="full-width"><span>Straße</span><input type="text" name="strasse" value="<?= e($field('strasse')) ?>"></label>
                <label><span>PLZ</span><input type="text" name="plz" value="<?= e($field('plz')) ?>"></label>
                <label><span>Ort</span><input type="text" name="ort" value="<?= e($field('ort')) ?>"></label>
                <label><span>Land</span><input type="text" name="land" value="<?= e($field('land', config('defaults.country', 'Deutschland'))) ?>"></label>
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
                    <?php foreach ($emails as $index => $email): ?>
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
                    <?php foreach ($phones as $index => $phone): ?>
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

        <div class="detail-save-bar" data-save-bar>
            <span class="detail-save-hint">Wenn alles stimmt, einfach speichern.</span>
            <div class="detail-save-actions">
                <button type="submit"><?= icon('check') ?><span>Speichern</span></button>
            </div>
        </div>
    </form>

    <p class="field-hint">Gültig bis <?= e(format_date(substr((string) ($expiresAt ?? ''), 0, 10))) ?>. Interne Notizen und die Kategorie sieht und ändert hier niemand – die pflegt weiterhin das Team.</p>

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
</section>

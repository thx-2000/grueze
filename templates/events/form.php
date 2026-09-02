<?php
// Nur für „Neuer Termin". Bearbeiten passiert auf der Detailseite.
$today = (new DateTimeImmutable('now'))->format('Y-m-d');
$kind = $kind ?? null;

$kindMeta = [
    'date_poll' => ['Datumsabstimmung', 'Mehrere Termine zur Auswahl – die Teilnehmer stimmen ab, danach legst du das Ergebnis fest.'],
    'fixed_date' => ['Fester Termin', 'Datum steht schon fest – du sammelst nur Zusagen (Ja / Vielleicht / Nein).'],
    'poll' => ['Abstimmung ohne Datum', 'Eine Frage mit mehreren Antwortmöglichkeiten – z. B. „Wohin fahren wir?".'],
];
?>
<p class="detail-backlink"><a href="<?= e(url('/termine')) ?>"><?= icon('chevron-right') ?>Zurück zu den Terminen</a></p>

<?php if ($kind === null): ?>
    <header class="contact-detail-head">
        <p class="eyebrow">Neuer Termin</p>
        <h1>Was soll es sein?</h1>
    </header>
    <div class="kind-picker">
        <?php foreach ($kindMeta as $key => [$label, $desc]): ?>
            <a class="kind-card" href="<?= e(url('/termine/neu?typ=' . $key)) ?>">
                <strong><?= e($label) ?></strong>
                <span><?= e($desc) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php return; ?>
<?php endif; ?>

<?php [$kindLabel, $kindDesc] = $kindMeta[$kind]; ?>
<header class="contact-detail-head">
    <p class="eyebrow">Neuer Termin · <?= e($kindLabel) ?></p>
    <h1>Termin anlegen</h1>
    <p class="muted"><?= e($kindDesc) ?></p>
</header>

<form method="post" action="<?= e(url('/termine')) ?>" class="contact-detail-form">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="kind" value="<?= e($kind) ?>">

    <section class="detail-card">
        <h2>Worum geht es?</h2>
        <div class="form-grid">
            <label class="full-width"><span>Titel <span class="required-marker" aria-hidden="true">*</span></span><input type="text" name="title" value="<?= e(old('title')) ?>" required placeholder="<?= $kind === 'poll' ? 'z. B. Wohin fahren wir?' : 'z. B. Stufentreffen 2026' ?>"></label>
            <label class="full-width"><span>Beschreibung</span><textarea name="description" rows="3"><?= e(old('description')) ?></textarea></label>
        </div>
    </section>

    <?php if ($kind === 'poll'): ?>
        <section class="detail-card">
            <h2>Antwortmöglichkeiten</h2>
            <p class="field-hint">Mindestens zwei. Die Teilnehmer geben je Möglichkeit Ja / Vielleicht / Nein.</p>
            <div class="text-options" data-text-options>
                <?php for ($i = 0; $i < 3; $i++): ?>
                    <div class="text-option-row">
                        <input type="text" name="option_label[]" value="" aria-label="Antwortmöglichkeit <?= $i + 1 ?>" placeholder="Möglichkeit <?= $i + 1 ?>">
                        <button type="button" class="danger-button icon-button" data-remove-text aria-label="Zeile entfernen"><?= icon('x') ?></button>
                    </div>
                <?php endfor; ?>
            </div>
            <button type="button" class="ghost-button" data-add-text><?= icon('plus') ?><span>Weitere Möglichkeit</span></button>
            <template id="textOptionTemplate">
                <div class="text-option-row">
                    <input type="text" name="option_label[]" value="" aria-label="Antwortmöglichkeit" placeholder="Weitere Möglichkeit">
                    <button type="button" class="danger-button icon-button" data-remove-text aria-label="Zeile entfernen"><?= icon('x') ?></button>
                </div>
            </template>
        </section>
    <?php elseif ($kind === 'fixed_date'): ?>
        <section class="detail-card">
            <h2>Termin</h2>
            <div class="form-grid">
                <label><span>Datum <span class="required-marker" aria-hidden="true">*</span></span><input type="date" name="option_date[]" value="" min="<?= e($today) ?>" required></label>
                <label><span>Uhrzeit</span><input type="text" name="option_time[]" value="" placeholder="z. B. 18:00"></label>
            </div>
        </section>
    <?php else: ?>
        <section class="detail-card">
            <h2>Datumsvorschläge</h2>
            <p class="field-hint">Ein oder mehrere Termine zur Auswahl. Uhrzeit optional – auch mehrere Uhrzeiten am selben Tag.</p>
            <div class="date-options" data-date-options>
                <?php for ($i = 0; $i < 3; $i++): ?>
                    <div class="date-option-row">
                        <input type="date" name="option_date[]" value="" aria-label="Datum <?= $i + 1 ?>" min="<?= e($today) ?>">
                        <input type="text" name="option_time[]" value="" aria-label="Uhrzeit <?= $i + 1 ?>" placeholder="Uhrzeit (optional)">
                        <button type="button" class="danger-button icon-button" data-remove-date aria-label="Zeile entfernen"><?= icon('x') ?></button>
                    </div>
                <?php endfor; ?>
            </div>
            <button type="button" class="ghost-button" data-add-date><?= icon('plus') ?><span>Weiterer Vorschlag</span></button>
            <template id="dateOptionTemplate">
                <div class="date-option-row">
                    <input type="date" name="option_date[]" value="" aria-label="Datum" min="<?= e($today) ?>">
                    <input type="text" name="option_time[]" value="" aria-label="Uhrzeit" placeholder="Uhrzeit (optional)">
                    <button type="button" class="danger-button icon-button" data-remove-date aria-label="Zeile entfernen"><?= icon('x') ?></button>
                </div>
            </template>
        </section>
    <?php endif; ?>

    <section class="detail-card">
        <h2>Eckdaten (optional)</h2>
        <div class="form-grid">
            <label><span>Ort</span><input type="text" name="location" value="<?= e(old('location')) ?>"></label>
            <?php if ($kind !== 'poll'): ?>
                <label><span>Uhrzeit</span><input type="text" name="time_note" value="<?= e(old('time_note')) ?>" placeholder="z. B. ab 18 Uhr"></label>
                <label><span>Kosten</span><input type="text" name="cost_note" value="<?= e(old('cost_note')) ?>"></label>
                <label><span>Mitbringen</span><input type="text" name="bring_note" value="<?= e(old('bring_note')) ?>"></label>
            <?php endif; ?>
        </div>
    </section>

    <div class="detail-save-bar" data-save-bar>
        <span class="detail-save-hint">Termin anlegen.</span>
        <div class="detail-save-actions">
            <a class="ghost-button" href="<?= e(url('/termine/neu')) ?>">Zurück</a>
            <button type="submit">Termin anlegen</button>
        </div>
    </div>
</form>

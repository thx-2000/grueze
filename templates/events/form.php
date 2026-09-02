<?php
// Nur für „Neuer Termin". Bearbeiten passiert auf der Detailseite.
$today = (new DateTimeImmutable('now'))->format('Y-m-d');
?>
<p class="detail-backlink"><a href="<?= e(url('/termine')) ?>"><?= icon('chevron-right') ?>Zurück zu den Terminen</a></p>

<header class="contact-detail-head">
    <p class="eyebrow">Neuer Termin</p>
    <h1>Termin anlegen</h1>
    <p class="muted">Erst Titel und Datumsvorschläge – Teilnehmerkreis und Links kommen danach.</p>
</header>

<form method="post" action="<?= e(url('/termine')) ?>" class="contact-detail-form">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

    <section class="detail-card">
        <h2>Worum geht es?</h2>
        <div class="form-grid">
            <label class="full-width"><span>Titel <span class="required-marker" aria-hidden="true">*</span></span><input type="text" name="title" value="<?= e(old('title')) ?>" required placeholder="z. B. Stufentreffen 2026"></label>
            <label class="full-width"><span>Beschreibung</span><textarea name="description" rows="3"><?= e(old('description')) ?></textarea></label>
        </div>
    </section>

    <section class="detail-card">
        <h2>Datumsvorschläge</h2>
        <p class="field-hint">Ein oder mehrere Termine zur Auswahl. Uhrzeit optional – auch mehrere Uhrzeiten am selben Tag sind möglich.</p>
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
    </section>

    <section class="detail-card">
        <h2>Eckdaten (optional)</h2>
        <div class="form-grid">
            <label><span>Ort</span><input type="text" name="location" value="<?= e(old('location')) ?>"></label>
            <label><span>Uhrzeit</span><input type="text" name="time_note" value="<?= e(old('time_note')) ?>" placeholder="z. B. ab 18 Uhr"></label>
            <label><span>Kosten</span><input type="text" name="cost_note" value="<?= e(old('cost_note')) ?>"></label>
            <label><span>Mitbringen</span><input type="text" name="bring_note" value="<?= e(old('bring_note')) ?>"></label>
        </div>
    </section>

    <div class="detail-save-bar" data-save-bar>
        <span class="detail-save-hint">Termin anlegen.</span>
        <div class="detail-save-actions">
            <a class="ghost-button" href="<?= e(url('/termine')) ?>">Abbrechen</a>
            <button type="submit">Termin anlegen</button>
        </div>
    </div>

    <template id="dateOptionTemplate">
        <div class="date-option-row">
            <input type="date" name="option_date[]" value="" aria-label="Datum" min="<?= e($today) ?>">
            <input type="text" name="option_time[]" value="" aria-label="Uhrzeit" placeholder="Uhrzeit (optional)">
            <button type="button" class="danger-button icon-button" data-remove-date aria-label="Zeile entfernen"><?= icon('x') ?></button>
        </div>
    </template>
</form>

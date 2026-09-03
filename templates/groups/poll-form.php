<?php
/** @var array<string,mixed> $group */
/** @var string|null $kind */
/** @var string $today */
$groupId = (int) $group['id'];
$memberCount = count($group['members']);
?>
<p class="detail-backlink"><a href="<?= e(url('/gruppen/abstimmungen?id=' . $groupId)) ?>"><?= icon('chevron-right') ?>Zurück zu den Abstimmungen</a></p>

<?php if ($kind === null): ?>
    <header class="page-head">
        <p class="eyebrow">Gruppe · <?= e($group['name']) ?></p>
        <h1>Neue Abstimmung</h1>
    </header>
    <div class="kind-picker">
        <a class="kind-card" href="<?= e(url('/gruppen/abstimmung/neu?id=' . $groupId . '&typ=poll')) ?>">
            <strong>Meinungsabstimmung</strong>
            <span>Eine Frage mit mehreren Antwortmöglichkeiten – jede:r stimmt mit Ja / Vielleicht / Nein ab.</span>
        </a>
        <a class="kind-card" href="<?= e(url('/gruppen/abstimmung/neu?id=' . $groupId . '&typ=date_poll')) ?>">
            <strong>Terminfindung</strong>
            <span>Mehrere Datumsvorschläge – die Gruppe stimmt ab, danach legt die Leitung den Termin fest.</span>
        </a>
    </div>
    <?php return; ?>
<?php endif; ?>

<?php $isDate = $kind === 'date_poll'; ?>
<header class="page-head">
    <p class="eyebrow">Gruppe · <?= e($group['name']) ?></p>
    <h1><?= $isDate ? 'Neue Terminfindung' : 'Neue Abstimmung' ?></h1>
    <p class="muted">Nur die <?= e((string) $memberCount) ?> Mitglieder dieser Gruppe sehen und beantworten sie.</p>
</header>

<form method="post" action="<?= e(url('/gruppen/abstimmung')) ?>" class="contact-detail-form">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="id" value="<?= e((string) $groupId) ?>">
    <input type="hidden" name="kind" value="<?= e($kind) ?>">

    <section class="detail-card">
        <h2><?= $isDate ? 'Worum geht es?' : 'Frage' ?></h2>
        <div class="form-grid">
            <label class="full-width"><span><?= $isDate ? 'Titel' : 'Worüber wird abgestimmt?' ?> <span class="required-marker" aria-hidden="true">*</span></span><input type="text" name="title" value="<?= e(old('title')) ?>" required></label>
            <label class="full-width"><span>Erläuterung (optional)</span><textarea name="description" rows="2"><?= e(old('description')) ?></textarea></label>
            <?php if ($isDate): ?>
                <label class="full-width"><span>Ort (optional)</span><input type="text" name="location" value="<?= e(old('location')) ?>"></label>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($isDate): ?>
        <section class="detail-card">
            <h2>Datumsvorschläge</h2>
            <p class="field-hint">Ein oder mehrere Termine zur Auswahl. Uhrzeit optional.</p>
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
    <?php else: ?>
        <section class="detail-card">
            <h2>Antwortmöglichkeiten</h2>
            <p class="field-hint">Zu jeder Möglichkeit stimmt jede:r mit Ja, Vielleicht oder Nein ab.</p>
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
    <?php endif; ?>

    <section class="detail-card">
        <h2>Frist &amp; Ergebnis (optional)</h2>
        <p class="field-hint">Mit einer Frist schließt die Abstimmung von selbst. 48&nbsp;Stunden vorher wird an alle erinnert, die noch nicht abgestimmt haben.</p>
        <div class="form-grid">
            <label><span>Abstimmung endet am</span><input type="datetime-local" name="closes_at" value="<?= e(old('closes_at')) ?>" min="<?= e(date('Y-m-d\TH:i')) ?>"></label>
            <label>
                <span>Ergebnis danach mailen an</span>
                <select name="result_recipients">
                    <?php
                    $choices = [
                        '' => 'Niemanden automatisch',
                        'voted' => 'Alle, die abgestimmt haben',
                        'invited' => 'Alle in der Gruppe',
                        'orga' => 'Nur das Orga-Team',
                        'admin' => 'Nur die Admins',
                    ];
                    $current = (string) old('result_recipients');
                    ?>
                    <?php foreach ($choices as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $current === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <?php if ($isDate): ?>
            <p class="field-hint">Bei einer Terminfindung geht die Ergebnis-Mail erst raus, wenn die Leitung den Termin festgelegt hat.</p>
        <?php endif; ?>
    </section>

    <div class="detail-save-bar" data-save-bar>
        <span class="detail-save-hint"><?= $isDate ? 'Terminfindung anlegen.' : 'Abstimmung anlegen.' ?></span>
        <div class="detail-save-actions">
            <a class="ghost-button" href="<?= e(url('/gruppen/abstimmung/neu?id=' . $groupId)) ?>">Zurück</a>
            <button type="submit"><?= $isDate ? 'Terminfindung anlegen' : 'Abstimmung anlegen' ?></button>
        </div>
    </div>
</form>

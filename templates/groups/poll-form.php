<?php
/** @var array<string,mixed> $group */
?>
<p class="detail-backlink"><a href="<?= e(url('/gruppen/abstimmungen?id=' . (int) $group['id'])) ?>"><?= icon('chevron-right') ?>Zurück zu den Abstimmungen</a></p>

<header class="page-head">
    <p class="eyebrow">Gruppe · <?= e($group['name']) ?></p>
    <h1>Neue Abstimmung</h1>
    <p class="muted">Nur die <?= e((string) count($group['members'])) ?> Mitglieder dieser Gruppe sehen und beantworten die Abstimmung.</p>
</header>

<form method="post" action="<?= e(url('/gruppen/abstimmung')) ?>" class="contact-detail-form">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="id" value="<?= e((string) $group['id']) ?>">

    <section class="detail-card">
        <h2>Frage</h2>
        <div class="form-grid">
            <label class="full-width"><span>Worüber wird abgestimmt? <span class="required-marker" aria-hidden="true">*</span></span><input type="text" name="title" value="<?= e(old('title')) ?>" required></label>
            <label class="full-width"><span>Erläuterung (optional)</span><textarea name="description" rows="2"><?= e(old('description')) ?></textarea></label>
        </div>
    </section>

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
    </section>

    <div class="detail-save-bar" data-save-bar>
        <span class="detail-save-hint">Abstimmung anlegen.</span>
        <div class="detail-save-actions">
            <a class="ghost-button" href="<?= e(url('/gruppen/abstimmungen?id=' . (int) $group['id'])) ?>">Abbrechen</a>
            <button type="submit">Abstimmung anlegen</button>
        </div>
    </div>
</form>

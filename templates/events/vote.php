<?php
$personName = trim($p['vorname'] . ' ' . $p['nachname']);
$ownName = trim((string) ($ownName ?? ''));
$foreign = $ownName !== '' && mb_strtolower($ownName) !== mb_strtolower($personName);
$closed = $p['status'] === 'archived';
$decidedId = (int) ($p['decided_option_id'] ?? 0);

$answerOptions = ['yes' => 'Ja', 'maybe' => 'Vielleicht', 'no' => 'Nein'];
$optionTitle = static function (array $option): string {
    $label = format_weekday_date($option['option_date']);
    $time = trim((string) ($option['option_time'] ?? ''));

    return $time !== '' ? $label . ', ' . $time : $label;
};
?>
<section class="vote-page">
    <header class="vote-head">
        <p class="eyebrow">Terminabstimmung</p>
        <h1><?= e($p['title']) ?></h1>
        <?php if (trim((string) ($p['description'] ?? '')) !== ''): ?>
            <p class="vote-description"><?= nl2br(e($p['description'])) ?></p>
        <?php endif; ?>
    </header>

    <?php
    $eckdaten = array_filter([
        'Ort' => $p['location'] ?? '',
        'Uhrzeit' => $p['time_note'] ?? '',
        'Kosten' => $p['cost_note'] ?? '',
        'Mitbringen' => $p['bring_note'] ?? '',
    ], static fn ($v): bool => trim((string) $v) !== '');
    ?>
    <?php if ($eckdaten !== []): ?>
        <dl class="vote-eckdaten">
            <?php foreach ($eckdaten as $label => $value): ?>
                <div><dt><?= e($label) ?></dt><dd><?= e((string) $value) ?></dd></div>
            <?php endforeach; ?>
        </dl>
    <?php endif; ?>

    <?php if ($foreign): ?>
        <p class="vote-alert is-foreign"><?= icon('eye-off') ?><span>Dieser Link gehört zu <strong><?= e($personName) ?></strong>. Du bist als <?= e($ownName) ?> angemeldet – wenn du hier abstimmst, änderst du die Eintragung einer anderen Person.</span></p>
    <?php else: ?>
        <p class="vote-hello">Hallo <strong><?= e($p['vorname']) ?></strong> – dieser Link ist für dich. Wenn du nicht <?= e($personName) ?> bist, ändere bitte nichts.</p>
    <?php endif; ?>

    <?php if ($closed): ?>
        <p class="vote-alert"><span>Diese Abstimmung ist abgeschlossen.</span></p>
    <?php endif; ?>

    <?php if ($p['options'] === []): ?>
        <p class="muted">Es gibt noch keine Datumsvorschläge.</p>
    <?php else: ?>
        <form method="post" action="<?= e(url('/abstimmen')) ?>" class="vote-form">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="token" value="<?= e($p['token']) ?>">

            <?php foreach ($p['options'] as $option): ?>
                <?php $current = $p['answers'][(int) $option['id']] ?? ''; ?>
                <fieldset class="vote-option<?= (int) $option['id'] === $decidedId ? ' is-decided' : '' ?>">
                    <legend><?= e($optionTitle($option)) ?><?= (int) $option['id'] === $decidedId ? ' · festgelegt' : '' ?></legend>
                    <div class="vote-choices">
                        <?php foreach ($answerOptions as $value => $label): ?>
                            <label class="vote-choice is-<?= e($value) ?>">
                                <input type="radio" name="answer[<?= e((string) $option['id']) ?>]" value="<?= e($value) ?>" <?= $current === $value ? 'checked' : '' ?> <?= $closed ? 'disabled' : '' ?>>
                                <span><?= e($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            <?php endforeach; ?>

            <?php if (!$closed): ?>
                <button type="submit" class="vote-submit">Rückmeldung speichern</button>
                <p class="field-hint">Du kannst deine Antwort jederzeit über denselben Link ändern.</p>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</section>

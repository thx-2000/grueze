<?php
$personName = trim($p['vorname'] . ' ' . $p['nachname']);
$ownName = trim((string) ($ownName ?? ''));
$foreign = $ownName !== '' && mb_strtolower($ownName) !== mb_strtolower($personName);
$closed = in_array($p['status'], ['closed', 'archived'], true);
$closesAt = trim((string) ($p['closes_at'] ?? ''));
$decidedId = (int) ($p['decided_option_id'] ?? 0);
$kind = (string) ($p['kind'] ?? 'date_poll');
$isFixed = $kind === 'fixed_date';

$answerOptions = ['yes' => 'Ja', 'maybe' => 'Vielleicht', 'no' => 'Nein'];
$optionTitle = static fn (array $option): string => event_option_label($option);
?>
<section class="vote-page">
    <header class="vote-head">
        <p class="eyebrow"><?= $kind === 'poll' ? 'Abstimmung' : ($isFixed ? 'Bitte um Rückmeldung' : 'Terminabstimmung') ?></p>
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
    <?php elseif ($closesAt !== ''): ?>
        <p class="vote-deadline"><?= icon('clock') ?><span>Rückmeldung bis <strong><?= e(format_deadline($closesAt)) ?></strong> · <?= e(time_until_hint($closesAt)) ?></span></p>
    <?php endif; ?>

    <?php if ($kind !== 'poll' && $decidedId > 0 && trim((string) ($p['ical_uid'] ?? '')) !== ''): ?>
        <p><a class="ghost-button" href="<?= e(url('/termine/termin.ics') . '?k=' . $p['ical_uid']) ?>"><?= icon('calendar') ?><span>In den Kalender</span></a></p>
    <?php endif; ?>

    <?php if ($p['options'] === []): ?>
        <p class="muted">Es gibt noch keine Auswahlmöglichkeiten.</p>
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

            <label class="vote-note">
                <span>Anmerkung (optional)</span>
                <textarea name="note" rows="2" maxlength="500" placeholder="z. B. „kann erst ab 20 Uhr"<?= $closed ? ' disabled' : '' ?>><?= e((string) ($p['note'] ?? '')) ?></textarea>
            </label>

            <?php if (!$closed): ?>
                <button type="submit" class="vote-submit">Rückmeldung speichern</button>
                <p class="field-hint">Du kannst deine Antwort jederzeit über denselben Link ändern.</p>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</section>

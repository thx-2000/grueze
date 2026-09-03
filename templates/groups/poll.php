<?php
/** @var array<string,mixed> $event */
/** @var int|null $myParticipantId */
/** @var array<int,string> $myAnswers */
/** @var bool $canManage */
$options = $event['options'];
$tally = $event['tally'];
$isOpen = $event['status'] === 'open';
$canVote = $isOpen && $myParticipantId !== null;
$closesAt = trim((string) ($event['closes_at'] ?? ''));
$answerLabels = ['yes' => 'Ja', 'maybe' => 'Vielleicht', 'no' => 'Nein'];
$statusLabel = ['open' => 'Abstimmung läuft', 'closed' => 'Abstimmung beendet', 'decided' => 'Entschieden', 'archived' => 'Archiviert'];
$groupId = (int) ($event['group_id'] ?? 0);
?>
<p class="detail-backlink"><a href="<?= e(url('/gruppen/abstimmungen?id=' . $groupId)) ?>"><?= icon('chevron-right') ?>Zurück zu den Abstimmungen</a></p>

<header class="page-head">
    <p class="eyebrow">Abstimmung · Gruppe <?= e((string) ($event['group_name'] ?? '')) ?></p>
    <h1><?= e($event['title']) ?></h1>
    <div class="contact-detail-meta">
        <span class="events-status is-<?= e($event['status']) ?>"><?= e($statusLabel[$event['status']] ?? $event['status']) ?></span>
        <span class="muted">von <?= e($event['creator_name']) ?></span>
    </div>
</header>

<?php if (trim((string) ($event['description'] ?? '')) !== ''): ?>
    <section class="detail-card"><p><?= nl2br(e($event['description'])) ?></p></section>
<?php endif; ?>

<?php if ($isOpen && $closesAt !== ''): ?>
    <p class="vote-deadline"><?= icon('clock') ?><span>Endet <strong><?= e(format_deadline($closesAt)) ?></strong> · <?= e(time_until_hint($closesAt)) ?></span></p>
<?php endif; ?>

<?php if ($myParticipantId === null && !$canManage): ?>
    <section class="detail-card"><p class="muted">Du gehörst nicht zum Teilnehmerkreis dieser Abstimmung.</p></section>
<?php elseif ($myParticipantId === null): ?>
    <section class="detail-card"><p class="muted">Diese Abstimmung ist nicht für dich – du siehst sie als Verantwortliche:r für die Gruppe „<?= e((string) ($event['group_name'] ?? '')) ?>".</p></section>
<?php endif; ?>

<form method="post" action="<?= e(url('/gruppen/abstimmung/stimme')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="id" value="<?= e((string) $event['id']) ?>">

    <section class="detail-card">
        <h2><?= $isOpen ? 'Deine Rückmeldung' : 'Ergebnis' ?></h2>
        <ul class="poll-options">
            <?php foreach ($options as $option): ?>
                <?php
                $oid = (int) $option['id'];
                $counts = $tally[$oid] ?? ['yes' => 0, 'maybe' => 0, 'no' => 0];
                $mine = $myAnswers[$oid] ?? '';
                ?>
                <li class="poll-option">
                    <p class="poll-option-label"><?= e(event_option_label($option)) ?></p>
                    <p class="poll-option-tally">
                        <span class="is-yes"><?= (int) $counts['yes'] ?>&times; Ja</span>
                        <span class="is-maybe"><?= (int) $counts['maybe'] ?>&times; Vielleicht</span>
                        <span class="is-no"><?= (int) $counts['no'] ?>&times; Nein</span>
                    </p>
                    <?php if ($canVote): ?>
                        <div class="poll-choices">
                            <?php foreach ($answerLabels as $value => $label): ?>
                                <label class="vote-choice is-<?= e($value) ?>">
                                    <input type="radio" name="answer[<?= e((string) $oid) ?>]" value="<?= e($value) ?>" <?= $mine === $value ? 'checked' : '' ?>>
                                    <span><?= e($label) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($mine !== ''): ?>
                        <p class="poll-option-mine">Deine Stimme: <strong><?= e($answerLabels[$mine]) ?></strong></p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($canVote): ?>
            <div class="toolbar-actions">
                <button type="submit"><?= icon('check') ?><span>Rückmeldung speichern</span></button>
            </div>
            <p class="field-hint">Du kannst deine Antwort bis zum Ende der Abstimmung ändern.</p>
        <?php endif; ?>
    </section>
</form>

<section class="detail-card">
    <h2>Teilnahme</h2>
    <p class="muted"><?= (int) $event['answered_count'] ?> von <?= count($event['participants']) ?> Mitgliedern haben abgestimmt.</p>
</section>

<?php if ($canManage && $isOpen): ?>
    <section class="detail-card detail-danger">
        <h2>Abstimmung schließen</h2>
        <p class="muted">Danach kann niemand mehr abstimmen. Ein eingestelltes Ergebnis-Mail geht anschließend automatisch raus.</p>
        <form method="post" action="<?= e(url('/gruppen/abstimmung/schliessen')) ?>" data-confirm="Abstimmung „<?= e($event['title']) ?>“ jetzt schließen?">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= e((string) $event['id']) ?>">
            <button type="submit" class="ghost-button"><?= icon('lock') ?><span>Jetzt schließen</span></button>
        </form>
    </section>
<?php endif; ?>

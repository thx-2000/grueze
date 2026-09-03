<?php
/** @var array<string,mixed> $group */
/** @var int $recipientCount */
/** @var int $noEmailCount */
/** @var int $leadCount */
/** @var int $sentToday */
/** @var int $softLimit */
/** @var bool $isAdmin */
$overSoftLimit = !$isAdmin && $sentToday >= $softLimit;
$otherLeads = max(0, ($leadCount ?? 0) - 1);
?>
<p class="detail-backlink"><a href="<?= e(url('/gruppen')) ?>"><?= icon('chevron-right') ?>Zurück zu meinen Gruppen</a></p>

<header class="page-head">
    <p class="eyebrow">Gruppe · <?= e($group['name']) ?></p>
    <h1>Nachricht an die Gruppe</h1>
    <p class="muted">
        Geht an <?= e((string) $recipientCount) ?> <?= $recipientCount === 1 ? 'Person' : 'Personen' ?> mit Mailadresse.
        <?php if ($noEmailCount > 0): ?><?= e((string) $noEmailCount) ?> ohne Mailadresse werden übersprungen.<?php endif; ?>
    </p>
</header>

<?php if ($overSoftLimit): ?>
    <div class="group-notice">
        <p><strong>Du hast heute schon <?= e((string) $sentToday) ?> Gruppen-Mails geschickt.</strong></p>
        <p>Die weiche Grenze liegt bei <?= e((string) $softLimit) ?>. Diese Nachricht geht trotzdem raus –
        aber das Orga-Team bekommt einen Hinweis. Bitte nur senden, wenn es wirklich nötig ist.</p>
    </div>
<?php endif; ?>

<section class="panel stack">
    <form method="post" action="<?= e(url('/gruppen/nachricht')) ?>" class="form-grid" data-confirm="Nachricht jetzt an alle <?= e((string) $recipientCount) ?> Empfänger schicken?">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="id" value="<?= e((string) $group['id']) ?>">
        <label class="full-width">
            <span>Betreff <span class="required-marker" aria-hidden="true">*</span></span>
            <input type="text" name="subject" maxlength="160" required value="<?= e(old('subject', $prefillSubject ?? '')) ?>">
        </label>
        <label class="full-width">
            <span>Nachricht <span class="required-marker" aria-hidden="true">*</span></span>
            <textarea name="message" rows="10" required><?= e(old('message', $prefillMessage ?? '')) ?></textarea>
        </label>
        <fieldset class="full-width reply-to-choice">
            <legend>Antworten gehen an</legend>
            <label class="radio-line">
                <input type="radio" name="reply_to" value="self" <?= old('reply_to') === 'leads' ? '' : 'checked' ?>>
                <span>nur an mich</span>
            </label>
            <label class="radio-line">
                <input type="radio" name="reply_to" value="leads" <?= old('reply_to') === 'leads' ? 'checked' : '' ?>>
                <span>an mich und die <?= ($leadCount ?? 0) === 1 ? 'Gruppenleitung' : 'gesamte Gruppenleitung' ?><?php if (($leadCount ?? 0) > 1): ?> (<?= e((string) $leadCount) ?> Personen)<?php endif; ?></span>
            </label>
        </fieldset>
        <div class="form-actions full-width">
            <a class="ghost-button" href="<?= e(url('/gruppen')) ?>">Abbrechen</a>
            <button type="submit"><?= icon('mail') ?><span>An die Gruppe senden</span></button>
        </div>
    </form>
</section>

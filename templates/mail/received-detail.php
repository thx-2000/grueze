<?php
/**
 * @var array<string,mixed> $entry
 * @var string $renderedSubject
 * @var string $renderedBody
 * @var bool $cooldownActive
 */
?>
<p class="detail-backlink"><a href="<?= e(url('/meine-nachrichten')) ?>"><?= icon('chevron-right') ?>Zurück zu den erhaltenen Mails</a></p>

<header class="contact-detail-head">
    <p class="eyebrow">Erhaltene Mail</p>
    <h1><?= e($renderedSubject ?: '(ohne Betreff)') ?></h1>
    <div class="contact-detail-meta">
        <span class="muted"><?= e(format_datetime((string) $entry['created_at'])) ?></span>
        <span class="muted">von <?= e((string) ($entry['current_sender_name'] ?: $entry['sender_name']) ?: 'unbekannt') ?></span>
        <?php if (($entry['own_status'] ?? 'gesendet') !== 'gesendet'): ?>
            <span class="status-chip is-warn">kam damals nicht an<?= ($entry['own_error'] ?? null) ? ' – ' . e((string) $entry['own_error']) : '' ?></span>
        <?php endif; ?>
    </div>
</header>

<section class="detail-card">
    <h2>Nachricht</h2>
    <p class="field-hint">So etwa sah die Mail in deinem Postfach aus – mit deiner Anrede und dem aktuellen Mail-Fuß.</p>
    <div class="mail-footer-preview"><?= nl2br(e($renderedBody)) ?></div>
</section>

<section class="detail-card">
    <div class="panel-head">
        <div>
            <h2>Nochmal an mich</h2>
            <p class="muted">Schickt diese Nachricht erneut an deine Login-Mailadresse.</p>
        </div>
        <form method="post" action="<?= e(url('/meine-nachrichten/erneut-an-mich')) ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= e((string) $entry['id']) ?>">
            <button type="submit" class="button-link"<?= $cooldownActive ? ' disabled' : '' ?>>
                <?= icon('mail') ?><span>An mich senden</span>
            </button>
        </form>
    </div>
    <?php if ($cooldownActive): ?>
        <p class="field-hint">Gerade eben schon eine Nachricht angefordert – bitte einen Moment warten.</p>
    <?php endif; ?>
</section>

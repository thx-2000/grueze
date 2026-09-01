<?php
$total = count($contacts);
$processed = (int) ($job['offset'] ?? 0);
$memberContactMode = (bool) ($memberContactMode ?? false);
?>
<section class="hero-card">
    <div class="hero-row">
        <div>
            <p class="eyebrow"><?= $memberContactMode ? 'Kontaktaufnahme läuft' : 'Versand läuft' ?></p>
            <h2><?= $memberContactMode ? 'Nachricht wird verschickt' : 'Mailing wird verschickt' ?></h2>
            <p class="muted">Der Versand erfolgt einzeln und personalisiert. Du kannst auf dieser Seite den Fortschritt verfolgen.</p>
        </div>
        <div class="selection-status" id="mailStatusBadge" role="status" aria-live="polite"><?= e((string) $processed) ?> / <?= e((string) $total) ?> verarbeitet</div>
    </div>
</section>

<section class="panel" data-mail-status-page>
    <div class="panel-head">
        <div>
            <h3>Versandfortschritt</h3>
            <p class="muted">Bitte die Seite offen lassen, bis alle Empfänger abgearbeitet sind.</p>
        </div>
    </div>

    <?php $percentNow = $total > 0 ? (string) round(($processed / $total) * 100, 2) : '0'; ?>
    <div id="mailProgress" class="progress-panel">
        <div class="progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= e($percentNow) ?>" aria-label="Versandfortschritt">
            <div id="mailProgressBar" class="progress-bar" style="width: <?= e($percentNow) ?>%"></div>
        </div>
        <p id="mailProgressText" role="status" aria-live="polite"><?= e((string) $processed) ?> von <?= e((string) $total) ?> gesendet</p>
        <div id="mailResults" class="results-list">
            <?php foreach (($job['results'] ?? []) as $entry): ?>
                <div><?= e(($entry['ok'] ? 'OK' : 'Fehler') . ': ' . $entry['name'] . ($entry['error'] ? ' (' . $entry['error'] . ')' : '')) ?></div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="form-actions top-gap">
        <a class="ghost-button" href="<?= e(url('/kontakte')) ?>">Zur Kontaktübersicht</a>
        <?php if ($canViewLog): ?>
            <a class="button-link" id="mailLogLink" href="<?= e(url('/logs/mail')) ?>">Versandprotokoll öffnen</a>
        <?php endif; ?>
    </div>
</section>

<section class="panel">
    <div class="panel-head">
        <div>
            <h3><?= $memberContactMode ? 'Kontaktierte Person' : 'Empfänger dieses Mailings' ?></h3>
            <p class="muted"><?= $memberContactMode ? 'Die Zieladresse bleibt in diesem Modus bewusst verborgen.' : 'Die Liste dient hier nur als Übersicht.' ?></p>
        </div>
    </div>
    <div class="recipient-grid">
        <?php foreach ($contacts as $contact): ?>
            <article class="recipient-chip">
                <strong><?= e($contact['vorname'] . ' ' . $contact['nachname']) ?></strong>
                <span><?= e($memberContactMode ? 'Adresse verborgen' : ($contact['emails'][0]['email'] ?? 'Keine Adresse')) ?></span>
            </article>
        <?php endforeach; ?>
    </div>
</section>

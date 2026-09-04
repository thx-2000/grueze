<?php
/** @var array<string,mixed> $job */
$total = count((array) $job['candidates']);
$processed = (int) ($job['offset'] ?? 0);
$percentNow = $total > 0 ? (string) round(($processed / $total) * 100, 2) : '0';
?>
<header class="page-head page-head--split">
    <div>
        <p class="eyebrow">Sammel-Einladung läuft</p>
        <h1>Einladungen werden verschickt</h1>
        <p class="muted">Bitte die Seite offen lassen, bis alle abgearbeitet sind.</p>
    </div>
    <span class="selection-status" id="inviteStatusBadge" role="status" aria-live="polite"><?= e((string) $processed) ?> / <?= e((string) $total) ?> verarbeitet</span>
</header>

<section class="panel" data-invite-status-page data-batch-url="<?= e(url('/verwaltung/einladungen/batch')) ?>">
    <div class="panel-head">
        <div>
            <h3>Fortschritt</h3>
        </div>
    </div>

    <div class="progress-panel">
        <div class="progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= e($percentNow) ?>" aria-label="Einladungs-Fortschritt">
            <div id="inviteProgressBar" class="progress-bar" style="width: <?= e($percentNow) ?>%"></div>
        </div>
        <p id="inviteProgressText" role="status" aria-live="polite"><?= e((string) $processed) ?> von <?= e((string) $total) ?> verschickt</p>
        <div id="inviteResults" class="results-list">
            <?php foreach (($job['results'] ?? []) as $entry): ?>
                <div><?= e(($entry['ok'] ? 'OK' : 'Fehler') . ': ' . $entry['name'] . ($entry['error'] ? ' (' . $entry['error'] . ')' : '')) ?></div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="form-actions top-gap">
        <a class="ghost-button" href="<?= e(url('/verwaltung/registrierung')) ?>">Zur Selbst-Registrierung</a>
    </div>
</section>

<script nonce="<?= e(csp_nonce()) ?>">
(function () {
    var page = document.querySelector('[data-invite-status-page]');
    if (!page) return;
    var batchUrl = page.getAttribute('data-batch-url');
    var bar = document.getElementById('inviteProgressBar');
    var text = document.getElementById('inviteProgressText');
    var badge = document.getElementById('inviteStatusBadge');
    var results = document.getElementById('inviteResults');

    async function runBatch() {
        var response = await fetch(batchUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: new URLSearchParams({ _csrf: window.APP.csrfToken }),
        });
        var data = await response.json();
        if (!data.ok) { return; }

        var percent = data.total > 0 ? (data.processed / data.total) * 100 : 0;
        bar.style.width = percent + '%';
        text.textContent = data.processed + ' von ' + data.total + ' verschickt';
        if (badge) badge.textContent = data.processed + ' / ' + data.total + ' verarbeitet';
        results.replaceChildren.apply(results, data.results.map(function (entry) {
            var line = document.createElement('div');
            var status = entry.ok ? 'OK' : 'Fehler';
            line.textContent = status + ': ' + entry.name + (entry.error ? ' (' + entry.error + ')' : '');
            return line;
        }));

        if (!data.done) {
            await runBatch();
        }
    }

    runBatch();
})();
</script>

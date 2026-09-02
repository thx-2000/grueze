<?php
$statusLabel = ['open' => 'Abstimmung läuft', 'decided' => 'Termin steht', 'archived' => 'Archiviert'];
$kindLabel = ['date_poll' => 'Datumsabstimmung', 'fixed_date' => 'Fester Termin', 'poll' => 'Abstimmung'];
?>
<header class="contacts-header">
    <div>
        <h1>Termine</h1>
        <p class="muted"><?= $showArchive ? 'Abgeschlossene und archivierte Termine.' : 'Terminfindung und Abstimmungen.' ?></p>
    </div>
    <?php if (!$showArchive): ?>
        <a class="button-link" href="<?= e(url('/termine/neu')) ?>"><?= icon('plus') ?><span>Neuer Termin</span></a>
    <?php endif; ?>
</header>

<nav class="events-tabs" aria-label="Ansicht">
    <a class="<?= $showArchive ? '' : 'is-active' ?>" href="<?= e(url('/termine')) ?>">Aktuell</a>
    <a class="<?= $showArchive ? 'is-active' : '' ?>" href="<?= e(url('/termine?archiv=1')) ?>">Archiv</a>
</nav>

<?php if ($events === []): ?>
    <section class="panel">
        <p class="muted">
            <?= $showArchive ? 'Noch nichts im Archiv.' : 'Noch keine Termine.' ?>
            <?php if (!$showArchive): ?><a href="<?= e(url('/termine/neu')) ?>">Ersten Termin anlegen</a>.<?php endif; ?>
        </p>
    </section>
<?php else: ?>
    <section class="panel events-list-panel">
        <ul class="events-list">
            <?php foreach ($events as $event): ?>
                <?php
                $optionCount = count($event['options']);
                $decidedOption = null;
                foreach ($event['options'] as $option) {
                    if ((int) $option['id'] === (int) ($event['decided_option_id'] ?? 0)) {
                        $decidedOption = $option;
                    }
                }
                ?>
                <li class="events-row">
                    <a class="events-row-main" href="<?= e(url('/termine/detail?id=' . (int) $event['id'])) ?>">
                        <span class="events-row-title"><?= e($event['title']) ?></span>
                        <span class="events-row-meta">
                            <span class="events-status is-<?= e($event['status']) ?>"><?= e($statusLabel[$event['status']] ?? $event['status']) ?></span>
                            · <?= e($kindLabel[$event['kind']] ?? 'Termin') ?>
                            <?php if ($decidedOption !== null): ?>
                                · <?= e(event_option_label($decidedOption)) ?>
                            <?php elseif ($optionCount > 0 && $event['kind'] !== 'poll'): ?>
                                · <?= e((string) $optionCount) ?> <?= $optionCount === 1 ? 'Vorschlag' : 'Vorschläge' ?>
                            <?php endif; ?>
                            · <?= e((string) $event['answered_count']) ?>/<?= e((string) $event['participant_count']) ?> geantwortet
                        </span>
                    </a>
                    <?= icon('chevron-right') ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

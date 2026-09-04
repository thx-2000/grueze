<?php
$statusLabel = ['open' => 'Abstimmung läuft', 'closed' => 'Abstimmung beendet', 'decided' => 'Termin steht', 'archived' => 'Archiviert'];
$kindLabel = ['date_poll' => 'Datumsabstimmung', 'fixed_date' => 'Fester Termin (alt)', 'poll' => 'Abstimmung'];
?>
<header class="contacts-header">
    <div>
        <h1>Abstimmungen</h1>
        <p class="muted"><?= $showArchive ? 'Abgeschlossene und archivierte Abstimmungen.' : 'Terminfindung und Abstimmungen.' ?></p>
    </div>
    <?php if (!$showArchive): ?>
        <a class="button-link" href="<?= e(url('/abstimmungen/neu')) ?>"><?= icon('plus') ?><span>Neue Abstimmung</span></a>
    <?php endif; ?>
</header>

<nav class="events-tabs" aria-label="Ansicht">
    <a class="<?= $showArchive ? '' : 'is-active' ?>" href="<?= e(url('/abstimmungen')) ?>">Aktuell</a>
    <a class="<?= $showArchive ? 'is-active' : '' ?>" href="<?= e(url('/abstimmungen?archiv=1')) ?>">Archiv</a>
</nav>

<?php if ($events === []): ?>
    <section class="panel">
        <p class="muted">
            <?= $showArchive ? 'Noch nichts im Archiv.' : 'Noch keine Abstimmungen.' ?>
            <?php if (!$showArchive): ?><a href="<?= e(url('/abstimmungen/neu')) ?>">Erste Abstimmung anlegen</a>.<?php endif; ?>
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
                    <a class="events-row-main" href="<?= e(url('/abstimmungen/detail?id=' . (int) $event['id'])) ?>">
                        <span class="events-row-title"><?= e($event['title']) ?><?php if (trim((string) ($event['group_name'] ?? '')) !== ''): ?> <span class="events-group-tag"><?= icon('contacts') ?><?= e($event['group_name']) ?></span><?php endif; ?></span>
                        <span class="events-row-meta">
                            <span class="events-status is-<?= e($event['status']) ?>"><?= e($statusLabel[$event['status']] ?? $event['status']) ?></span>
                            · <?= e($kindLabel[$event['kind']] ?? 'Abstimmung') ?>
                            <?php if ($decidedOption !== null): ?>
                                · <?= e(event_option_label($decidedOption)) ?>
                            <?php elseif ($optionCount > 0 && $event['kind'] !== 'poll'): ?>
                                · <?= e((string) $optionCount) ?> <?= $optionCount === 1 ? 'Vorschlag' : 'Vorschläge' ?>
                            <?php endif; ?>
                            · <?= e((string) $event['answered_count']) ?>/<?= e((string) $event['participant_count']) ?> geantwortet
                            <?php if ($event['status'] === 'open' && trim((string) ($event['closes_at'] ?? '')) !== ''): ?>
                                · Frist <?= e(time_until_hint($event['closes_at'])) ?>
                            <?php endif; ?>
                        </span>
                    </a>
                    <?= icon('chevron-right') ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

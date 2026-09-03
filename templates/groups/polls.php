<?php
/** @var array<string,mixed> $group */
/** @var list<array<string,mixed>> $polls */
/** @var bool $canCreate */
$statusLabel = ['open' => 'läuft', 'closed' => 'beendet', 'decided' => 'entschieden', 'archived' => 'archiviert'];
?>
<p class="detail-backlink"><a href="<?= e(url('/gruppen')) ?>"><?= icon('chevron-right') ?>Zurück zu meinen Gruppen</a></p>

<header class="page-head page-head--split">
    <div>
        <p class="eyebrow">Gruppe · <?= e($group['name']) ?></p>
        <h1>Abstimmungen</h1>
        <p class="muted">Nur für die <?= e((string) count($group['members'])) ?> Mitglieder dieser Gruppe sichtbar.</p>
    </div>
    <?php if ($canCreate): ?>
        <a class="button-link" href="<?= e(url('/gruppen/abstimmung/neu?id=' . (int) $group['id'])) ?>"><?= icon('plus') ?><span>Neue Abstimmung</span></a>
    <?php endif; ?>
</header>

<?php if ($polls === []): ?>
    <section class="panel">
        <p class="muted">Noch keine Abstimmung in dieser Gruppe.</p>
    </section>
<?php else: ?>
    <section class="panel events-list-panel">
        <ul class="events-list">
            <?php foreach ($polls as $poll): ?>
                <li class="events-row">
                    <a class="events-row-main" href="<?= e(url('/gruppen/abstimmung?id=' . (int) $poll['id'])) ?>">
                        <span class="events-row-title"><?= e($poll['title']) ?></span>
                        <span class="events-row-meta">
                            <span class="events-status is-<?= e($poll['status']) ?>"><?= e($statusLabel[$poll['status']] ?? $poll['status']) ?></span>
                            · <?= ($poll['kind'] ?? 'poll') === 'date_poll' ? 'Terminfindung' : 'Abstimmung' ?>
                            · <?= e((string) $poll['answered_count']) ?>/<?= e((string) $poll['participant_count']) ?> haben abgestimmt
                            <?php if ($poll['status'] === 'open' && trim((string) ($poll['closes_at'] ?? '')) !== ''): ?>
                                · Frist <?= e(time_until_hint($poll['closes_at'])) ?>
                            <?php endif; ?>
                        </span>
                    </a>
                    <?= icon('chevron-right') ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

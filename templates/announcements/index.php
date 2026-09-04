<?php
/**
 * @var list<array<string,mixed>> $announcements
 * @var bool $showPast
 * @var bool $canManage
 */
?>
<header class="contacts-header">
    <div>
        <h1>Termine</h1>
        <p class="muted"><?= $showPast ? 'Vergangene Termine.' : 'Kommende Termine und Ankündigungen vom Orga-Team.' ?></p>
    </div>
    <?php if ($canManage && !$showPast): ?>
        <a class="button-link" href="<?= e(url('/termine/neu')) ?>"><?= icon('plus') ?><span>Neue Ankündigung</span></a>
    <?php endif; ?>
</header>

<nav class="events-tabs" aria-label="Ansicht">
    <a class="<?= $showPast ? '' : 'is-active' ?>" href="<?= e(url('/termine')) ?>">Anstehend</a>
    <a class="<?= $showPast ? 'is-active' : '' ?>" href="<?= e(url('/termine?archiv=1')) ?>">Vergangen</a>
</nav>

<?php if ($announcements === []): ?>
    <section class="panel">
        <p class="muted">
            <?= $showPast ? 'Nichts Vergangenes.' : 'Noch keine Ankündigung.' ?>
            <?php if ($canManage && !$showPast): ?><a href="<?= e(url('/termine/neu')) ?>">Erste Ankündigung anlegen</a>.<?php endif; ?>
        </p>
    </section>
<?php else: ?>
    <section class="panel events-list-panel">
        <ul class="events-list">
            <?php foreach ($announcements as $a): ?>
                <?php
                $startsAt = trim((string) ($a['starts_at'] ?? ''));
                $endsAt = trim((string) ($a['ends_at'] ?? ''));
                $dateRange = '';
                if ($startsAt !== '' && $endsAt !== '' && $endsAt !== $startsAt) {
                    $dateRange = format_date($startsAt) . ' – ' . format_date($endsAt);
                } elseif ($startsAt !== '') {
                    $dateRange = format_date($startsAt);
                }
                ?>
                <li class="events-row">
                    <a class="events-row-main" href="<?= e(url('/termine/detail?id=' . (int) $a['id'])) ?>">
                        <span class="events-row-title">
                            <?= e((string) $a['title']) ?>
                            <?php if ($canManage && $a['audience_mode'] === 'restricted'): ?> <span class="events-group-tag"><?= icon('eye') ?>eingeschränkt</span><?php endif; ?>
                        </span>
                        <span class="events-row-meta">
                            <?php if ($dateRange !== ''): ?><?= e($dateRange) ?><?php else: ?>ohne festes Datum<?php endif; ?>
                            <?php if (trim((string) ($a['location'] ?? '')) !== ''): ?> · <?= e((string) $a['location']) ?><?php endif; ?>
                        </span>
                    </a>
                    <?= icon('chevron-right') ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

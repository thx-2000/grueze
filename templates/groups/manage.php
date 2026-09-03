<?php
/** @var list<array<string,mixed>> $groups */
?>
<header class="page-head">
    <p class="eyebrow">Verwaltung</p>
    <h1>Gruppen</h1>
    <p class="muted">Personengruppen quer zu Kategorie und Tag – etwa ein Kurs, ein Ausschuss oder ein Fahrgemeinschafts-Kreis. Grundlage für Gruppen-Mail und -Abstimmung.</p>
</header>

<section class="panel stack">
    <div class="panel-head"><div><h3>Alle Gruppen</h3></div></div>

    <?php if ($groups === []): ?>
        <p class="muted">Noch keine Gruppe angelegt.</p>
    <?php else: ?>
        <ul class="events-list">
            <?php foreach ($groups as $group): ?>
                <?php $count = (int) $group['member_count']; ?>
                <li class="events-row">
                    <a class="events-row-main" href="<?= e(url('/verwaltung/gruppen/detail?id=' . (int) $group['id'])) ?>">
                        <span class="events-row-title"><?= e($group['name']) ?></span>
                        <span class="events-row-meta">
                            <?php if ((int) $group['is_open'] === 1): ?><span class="events-status is-open">offen</span> · <?php endif; ?>
                            <?= e((string) $count) ?> <?= $count === 1 ? 'Mitglied' : 'Mitglieder' ?>
                            <?php if (trim((string) ($group['description'] ?? '')) !== ''): ?>
                                · <?= e(mb_strimwidth((string) $group['description'], 0, 80, '…')) ?>
                            <?php endif; ?>
                        </span>
                    </a>
                    <?= icon('chevron-right') ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/verwaltung/gruppen')) ?>" class="taxo-add">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="text" name="name" placeholder="Neue Gruppe" required aria-label="Name der neuen Gruppe">
        <button type="submit"><?= icon('plus') ?><span>Gruppe anlegen</span></button>
    </form>
</section>

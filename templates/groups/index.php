<?php
/** @var int $contactId */
/** @var list<array<string,mixed>> $myGroups */
/** @var list<array<string,mixed>> $openGroups */
/** @var bool $canManage */
?>
<header class="page-head">
    <p class="eyebrow">Gruppen</p>
    <h1>Meine Gruppen</h1>
    <p class="muted">Kurse, Ausschüsse oder feste Runden, in denen du mitmachst.</p>
</header>

<?php if ($contactId <= 0): ?>
    <section class="panel">
        <p class="muted">Mit deinem Zugang ist noch kein Eintrag im Adressbuch verknüpft. Sobald das Orga-Team das verbindet, erscheinen deine Gruppen hier.</p>
    </section>
<?php else: ?>

    <section class="panel stack">
        <div class="panel-head"><div><h3>Dabei</h3></div></div>
        <?php if ($myGroups === []): ?>
            <p class="muted">Du bist zurzeit in keiner Gruppe.</p>
        <?php else: ?>
            <ul class="group-cards">
                <?php foreach ($myGroups as $group): ?>
                    <?php $count = (int) $group['member_count']; ?>
                    <li class="group-card">
                        <div class="group-card-head">
                            <h4><?= e($group['name']) ?></h4>
                            <span class="muted"><?= e((string) $count) ?> <?= $count === 1 ? 'Mitglied' : 'Mitglieder' ?></span>
                        </div>
                        <?php if (trim((string) ($group['description'] ?? '')) !== ''): ?>
                            <p class="group-card-desc"><?= e($group['description']) ?></p>
                        <?php endif; ?>
                        <?php if (($group['my_role'] ?? 'member') === 'lead'): ?>
                            <p class="group-card-lead"><?= icon('key') ?><span>Du bist Gruppenleitung</span></p>
                        <?php endif; ?>
                        <div class="group-card-actions">
                            <a class="compact-action" href="<?= e(url('/gruppen/abstimmungen?id=' . (int) $group['id'])) ?>"><?= icon('check') ?><span>Abstimmungen</span></a>
                            <?php if (($group['my_role'] ?? 'member') === 'lead' || !empty($canManage)): ?>
                                <a class="compact-action" href="<?= e(url('/verwaltung/gruppen/detail?id=' . (int) $group['id'])) ?>"><?= icon('sliders') ?><span>Verwalten</span></a>
                            <?php endif; ?>
                            <?php if ((int) ($group['mail_locked'] ?? 0) === 1): ?>
                                <span class="field-hint">Gruppen-Versand ist gesperrt.</span>
                            <?php else: ?>
                                <a class="compact-action" href="<?= e(url('/gruppen/nachricht?id=' . (int) $group['id'])) ?>"><?= icon('mail') ?><span>Nachricht an die Gruppe</span></a>
                            <?php endif; ?>
                            <?php if ((int) $group['is_open'] === 1): ?>
                                <form method="post" action="<?= e(url('/gruppen/verlassen')) ?>" data-confirm="Gruppe „<?= e($group['name']) ?>“ verlassen?">
                                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                    <input type="hidden" name="id" value="<?= e((string) $group['id']) ?>">
                                    <button type="submit" class="ghost-button compact-action">Austreten</button>
                                </form>
                            <?php else: ?>
                                <span class="field-hint">Vom Orga-Team verwaltet.</span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <?php if ($openGroups !== []): ?>
        <section class="panel stack">
            <div class="panel-head"><div><h3>Offene Gruppen</h3><p class="muted">Hier kannst du selbst beitreten.</p></div></div>
            <ul class="group-cards">
                <?php foreach ($openGroups as $group): ?>
                    <?php $count = (int) $group['member_count']; ?>
                    <li class="group-card">
                        <div class="group-card-head">
                            <h4><?= e($group['name']) ?></h4>
                            <span class="muted"><?= e((string) $count) ?> <?= $count === 1 ? 'Mitglied' : 'Mitglieder' ?></span>
                        </div>
                        <?php if (trim((string) ($group['description'] ?? '')) !== ''): ?>
                            <p class="group-card-desc"><?= e($group['description']) ?></p>
                        <?php endif; ?>
                        <form method="post" action="<?= e(url('/gruppen/beitreten')) ?>">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="id" value="<?= e((string) $group['id']) ?>">
                            <button type="submit" class="compact-action"><?= icon('plus') ?><span>Beitreten</span></button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

<?php endif; ?>

<?php if ($canManage): ?>
    <p class="muted"><a href="<?= e(url('/verwaltung/gruppen')) ?>">Alle Gruppen verwalten →</a></p>
<?php endif; ?>

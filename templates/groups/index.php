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

    <?php
    $leadGroups = array_values(array_filter($myGroups, static fn (array $g): bool => ($g['my_role'] ?? 'member') === 'lead'));
    $memberGroups = array_values(array_filter($myGroups, static fn (array $g): bool => ($g['my_role'] ?? 'member') !== 'lead'));
    ?>

    <?php if ($leadGroups !== []): ?>
        <section class="panel stack">
            <div class="panel-head"><div>
                <h3>Gruppen, die du leitest</h3>
                <p class="muted">Anschreiben und Abstimmungen starten – direkt von hier.</p>
            </div></div>
            <ul class="group-cards">
                <?php foreach ($leadGroups as $group): ?>
                    <?php $count = (int) $group['member_count']; $pending = (int) ($group['pending_requests'] ?? 0); ?>
                    <li class="group-card group-card--lead">
                        <div class="group-card-head">
                            <h4><?= e($group['name']) ?></h4>
                            <span class="muted"><?= e((string) $count) ?> <?= $count === 1 ? 'Mitglied' : 'Mitglieder' ?></span>
                        </div>
                        <?php if (trim((string) ($group['description'] ?? '')) !== ''): ?>
                            <p class="group-card-desc"><?= e($group['description']) ?></p>
                        <?php endif; ?>
                        <?php if ($pending > 0): ?>
                            <a class="group-card-alert" href="<?= e(url('/verwaltung/gruppen/detail?id=' . (int) $group['id'])) ?>">
                                <?= icon('key') ?><span><?= $pending ?> <?= $pending === 1 ? 'Beitrittsanfrage' : 'Beitrittsanfragen' ?> – bearbeiten</span>
                            </a>
                        <?php endif; ?>
                        <div class="group-card-actions group-card-actions--primary">
                            <?php if ((int) ($group['mail_locked'] ?? 0) === 1): ?>
                                <span class="field-hint">Gruppen-Versand ist gesperrt.</span>
                            <?php else: ?>
                                <a class="button-link" href="<?= e(url('/gruppen/nachricht?id=' . (int) $group['id'])) ?>"><?= icon('mail') ?><span>Nachricht schreiben</span></a>
                            <?php endif; ?>
                            <a class="button-link" href="<?= e(url('/gruppen/abstimmung/neu?id=' . (int) $group['id'])) ?>"><?= icon('check') ?><span>Abstimmung starten</span></a>
                        </div>
                        <div class="group-card-actions">
                            <a class="compact-action" href="<?= e(url('/gruppen/abstimmungen?id=' . (int) $group['id'])) ?>">Laufende Abstimmungen</a>
                            <a class="compact-action" href="<?= e(url('/verwaltung/gruppen/detail?id=' . (int) $group['id'])) ?>"><?= icon('sliders') ?><span>Mitglieder</span></a>
                            <a class="compact-action" href="<?= e(url('/hilfe/gruppenleitung.html')) ?>" target="_blank" rel="noopener"><?= icon('help') ?><span>Kurzanleitung</span></a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <section class="panel stack">
        <div class="panel-head"><div><h3>Dabei</h3></div></div>
        <?php if ($memberGroups === [] && $leadGroups === []): ?>
            <p class="muted">Du bist zurzeit in keiner Gruppe.</p>
        <?php elseif ($memberGroups === []): ?>
            <p class="muted">Sonst bist du in keiner weiteren Gruppe.</p>
        <?php else: ?>
            <ul class="group-cards">
                <?php foreach ($memberGroups as $group): ?>
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
                            <p class="group-card-lead"><?= icon('key') ?><span>Du bist Gruppenleitung<?php if ((int) ($group['pending_requests'] ?? 0) > 0): ?> · <?= (int) $group['pending_requests'] ?> Beitrittsanfrage<?= (int) $group['pending_requests'] === 1 ? '' : 'n' ?><?php endif; ?> · <a href="<?= e(url('/hilfe/gruppenleitung.html')) ?>" target="_blank" rel="noopener">Kurzanleitung</a></span></p>
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

    <?php if (!empty($closedGroups)): ?>
        <section class="panel stack">
            <div class="panel-head"><div><h3>Andere Gruppen</h3><p class="muted">Diese Gruppen verwaltet ein Team – du kannst um Aufnahme bitten.</p></div></div>
            <ul class="group-cards">
                <?php foreach ($closedGroups as $group): ?>
                    <?php $count = (int) $group['member_count']; $pending = (int) ($group['request_pending'] ?? 0) === 1; ?>
                    <li class="group-card">
                        <div class="group-card-head">
                            <h4><?= e($group['name']) ?></h4>
                            <span class="muted"><?= e((string) $count) ?> <?= $count === 1 ? 'Mitglied' : 'Mitglieder' ?></span>
                        </div>
                        <?php if (trim((string) ($group['description'] ?? '')) !== ''): ?>
                            <p class="group-card-desc"><?= e($group['description']) ?></p>
                        <?php endif; ?>
                        <?php if ($pending): ?>
                            <div class="group-card-actions">
                                <span class="field-hint">Anfrage läuft.</span>
                                <form method="post" action="<?= e(url('/gruppen/beitritt-zuruecknehmen')) ?>">
                                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                    <input type="hidden" name="id" value="<?= e((string) $group['id']) ?>">
                                    <button type="submit" class="ghost-button compact-action">Zurückziehen</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <form method="post" action="<?= e(url('/gruppen/beitritt-anfragen')) ?>" class="group-request-form">
                                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="id" value="<?= e((string) $group['id']) ?>">
                                <input type="text" name="message" maxlength="500" placeholder="Kurze Nachricht (optional)" aria-label="Nachricht an die Gruppenleitung">
                                <button type="submit" class="compact-action"><?= icon('mail') ?><span>Beitritt anfragen</span></button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

<?php endif; ?>

<?php if ($canManage): ?>
    <p class="muted"><a href="<?= e(url('/verwaltung/gruppen')) ?>">Alle Gruppen verwalten →</a></p>
<?php endif; ?>

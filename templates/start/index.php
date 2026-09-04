<?php
$firstName = trim((string) ($currentUser['name'] ?? ''));
$firstName = $firstName !== '' ? explode(' ', $firstName)[0] : '';
$canManage = can('contacts.manage');
$canEvents = can('events.manage');
$canAnnouncements = can('announcements.manage');
$canMail = can('mail.send');
$isMemberView = !$canManage && !$canMail;

$board = $board ?? [];
$myOpenVotes = $myOpenVotes ?? [];
$leadGroups = $leadGroups ?? [];
$birthdays = $birthdays ?? [];

// Deutscher Wochentag + Datum ohne Intl-Abhängigkeit.
$weekdays = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
$months = [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
$now = new DateTimeImmutable('now');
$todayLong = $weekdays[(int) $now->format('w')] . ', ' . (int) $now->format('j') . '. ' . $months[(int) $now->format('n')];
?>
<section class="start-hero">
    <h1><?= $firstName !== '' ? 'Hallo, ' . e($firstName) : 'Willkommen' ?></h1>
    <p class="start-date"><?= e($todayLong) ?></p>
</section>

<form method="get" action="<?= e(url('/search')) ?>" class="start-search" role="search">
    <label for="startSearch" class="visually-hidden">Kontakt suchen</label>
    <?= icon('search') ?>
    <input type="search" id="startSearch" name="q" placeholder="Kontakt suchen – Name, Geburtsname, Ort …" autocomplete="off" autofocus>
    <button type="submit">Suchen</button>
</form>

<div class="start-actions">
    <?php if ($canManage): ?>
        <a class="button-link" href="<?= e(url('/contacts/create')) ?>"><?= icon('plus') ?><span>Person hinzufügen</span></a>
    <?php endif; ?>
    <?php if ($canAnnouncements): ?>
        <a class="ghost-button" href="<?= e(url('/termine/neu')) ?>"><?= icon('calendar') ?><span>Neue Ankündigung</span></a>
    <?php endif; ?>
    <?php if ($canEvents): ?>
        <a class="ghost-button" href="<?= e(url('/abstimmungen/neu')) ?>"><?= icon('poll') ?><span>Neue Abstimmung</span></a>
    <?php endif; ?>
    <?php if ($canMail): ?>
        <a class="ghost-button" href="<?= e(url('/rundmail')) ?>"><?= icon('mail') ?><span>Nachricht schreiben</span></a>
    <?php endif; ?>
    <?php if ($isMemberView): ?>
        <a class="button-link" href="<?= e(url('/account')) ?>"><?= icon('user') ?><span>Meine Daten</span></a>
        <a class="ghost-button" href="<?= e(url('/orga-team')) ?>"><?= icon('mail') ?><span>Orga-Team schreiben</span></a>
    <?php endif; ?>
</div>

<?php if (!empty($showBoard)): ?>
    <section class="panel start-board" aria-labelledby="startBoardTitle">
        <div class="start-board-head">
            <h2 id="startBoardTitle">Steht an</h2>
            <p class="muted">
                <?php if ($board === []): ?>
                    Nichts Offenes – alle Rückmeldungen da, Kontaktdaten gepflegt.
                <?php else: ?>
                    <?= count($board) === 1 ? 'Eine Sache wartet auf dich.' : count($board) . ' Dinge warten auf dich.' ?>
                <?php endif; ?>
            </p>
        </div>

        <?php if ($board === []): ?>
            <p class="start-board-clear"><?= icon('check') ?><span>Alles erledigt.</span></p>
        <?php else: ?>
            <ul class="start-widget-list">
                <?php foreach ($board as $item): ?>
                    <li>
                        <a href="<?= e($item['href']) ?>">
                            <span class="start-widget-main">
                                <?= e($item['label']) ?>
                                <?php if (!empty($item['urgent'])): ?><span class="status-chip is-warn">bald fällig</span><?php endif; ?>
                            </span>
                            <span class="start-widget-meta"><?= e($item['meta']) ?></span>
                            <?= icon('chevron-right') ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($canManage): ?>
            <p class="start-board-foot">
                <a href="<?= e(url('/kontakte')) ?>">Ganzes Adressbuch ansehen</a>
                · <?= e((string) ($stats['total'] ?? 0)) ?> Kontakte
            </p>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($myOpenVotes !== []): ?>
    <section class="panel start-widget" aria-labelledby="startVotesTitle">
        <div class="start-board-head">
            <h2 id="startVotesTitle">Deine offenen Abstimmungen</h2>
            <p class="muted">Hier fehlt noch deine Rückmeldung – oder du kannst sie ändern.</p>
        </div>
        <ul class="start-widget-list">
            <?php foreach ($myOpenVotes as $ev): ?>
                <?php $href = (int) ($ev['group_id'] ?? 0) > 0
                    ? url('/gruppen/abstimmung?id=' . (int) $ev['id'])
                    : url('/abstimmen?token=' . $ev['token']); ?>
                <li>
                    <a href="<?= e($href) ?>">
                        <span class="start-widget-main">
                            <?= e($ev['title']) ?>
                            <?php if ((int) ($ev['has_answered'] ?? 0) === 1): ?>
                                <span class="status-chip is-ok">geantwortet</span>
                            <?php else: ?>
                                <span class="status-chip is-warn">offen</span>
                            <?php endif; ?>
                        </span>
                        <span class="start-widget-meta">
                            <?php if (trim((string) ($ev['closes_at'] ?? '')) !== ''): ?>Frist <?= e(time_until_hint($ev['closes_at'])) ?><?php else: ?>ohne Frist<?php endif; ?>
                        </span>
                        <?= icon('chevron-right') ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<?php if ($leadGroups !== []): ?>
    <section class="panel start-widget" aria-labelledby="startGroupsTitle">
        <div class="start-board-head">
            <h2 id="startGroupsTitle">Deine Gruppen</h2>
            <p class="muted">Gruppen, die du leitest.</p>
        </div>
        <ul class="start-widget-list">
            <?php foreach ($leadGroups as $g): ?>
                <?php $pending = (int) ($g['pending_requests'] ?? 0); ?>
                <li>
                    <a href="<?= e(url('/gruppen')) ?>">
                        <span class="start-widget-main">
                            <?= e($g['name']) ?>
                            <?php if ($pending > 0): ?><span class="status-chip is-warn"><?= $pending ?> <?= $pending === 1 ? 'Anfrage' : 'Anfragen' ?></span><?php endif; ?>
                        </span>
                        <span class="start-widget-meta"><?= (int) ($g['member_count'] ?? 0) ?> <?= (int) ($g['member_count'] ?? 0) === 1 ? 'Mitglied' : 'Mitglieder' ?></span>
                        <?= icon('chevron-right') ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="start-board-foot"><a href="<?= e(url('/gruppen')) ?>">Zu den Gruppen – anschreiben, abstimmen</a></p>
    </section>
<?php endif; ?>

<?php if ($birthdays !== []): ?>
    <section class="panel start-widget" aria-labelledby="startBirthdaysTitle">
        <div class="start-board-head">
            <h2 id="startBirthdaysTitle">Geburtstage diese Woche</h2>
            <p class="muted">Die nächsten sieben Tage.</p>
        </div>
        <ul class="start-widget-list">
            <?php foreach ($birthdays as $b): ?>
                <li>
                    <a href="<?= e(url(can('contacts.manage') ? '/contacts/edit?id=' . $b['id'] : '/kontakte?q=' . rawurlencode($b['vorname'] . ' ' . $b['nachname']))) ?>">
                        <span class="start-widget-main"><?= e(trim($b['vorname'] . ' ' . $b['nachname'])) ?></span>
                        <span class="start-widget-meta">
                            <?php if ($b['in_days'] === 0): ?><strong>heute</strong>
                            <?php elseif ($b['in_days'] === 1): ?>morgen
                            <?php else: ?>in <?= (int) $b['in_days'] ?> Tagen<?php endif; ?>
                            · <?= e(format_date($b['geburtstag'])) ?><?php if ($b['turning'] !== null): ?> · wird <?= (int) $b['turning'] ?><?php endif; ?>
                        </span>
                        <?= icon('chevron-right') ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

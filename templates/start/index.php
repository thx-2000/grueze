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
$unreadAnnouncements = $unreadAnnouncements ?? [];
$pins = $pins ?? [];
$birthdays = $birthdays ?? [];
$announcementLinkIcon = ['extern' => 'globe', 'dokument' => 'file', 'abstimmung' => 'poll'];

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

<?php if ($pins !== []): ?>
    <section class="panel start-widget" aria-labelledby="startPinsTitle">
        <div class="start-board-head">
            <h2 id="startPinsTitle">Meine Kacheln</h2>
            <div class="view-toggle" role="group" aria-label="Ansicht umschalten">
                <button type="button" class="view-toggle-button is-active" data-pins-view="tiles" aria-pressed="true">Kacheln</button>
                <button type="button" class="view-toggle-button" data-pins-view="list" aria-pressed="false">Liste</button>
            </div>
        </div>
        <p class="field-hint">Zum Umsortieren ziehen.</p>
        <div class="pins-grid" data-pins-root data-reorder-url="<?= e(url('/start/kacheln-sortieren')) ?>">
            <?php foreach ($pins as $pin): ?>
                <article class="pins-tile" draggable="true" data-pin-item data-pin-id="<?= e((string) $pin['id']) ?>">
                    <span class="pins-tile-handle" aria-hidden="true"><?= icon('drag') ?></span>
                    <a class="pins-tile-link" href="<?= e(url((string) $pin['path'])) ?>">
                        <span class="pins-tile-icon"><?= icon(trim((string) ($pin['icon'] ?? '')) !== '' ? (string) $pin['icon'] : 'star') ?></span>
                        <span class="pins-tile-body">
                            <span class="pins-tile-title"><?= e((string) $pin['label']) ?></span>
                            <?php if (trim((string) ($pin['description'] ?? '')) !== ''): ?>
                                <span class="pins-tile-desc"><?= e((string) $pin['description']) ?></span>
                            <?php endif; ?>
                        </span>
                    </a>
                    <form method="post" action="<?= e(url('/start/entpinnen')) ?>" class="pins-tile-remove">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="id" value="<?= e((string) $pin['id']) ?>">
                        <input type="hidden" name="back" value="/">
                        <button type="submit" aria-label="„<?= e((string) $pin['label']) ?>“ von der Startseite lösen"><?= icon('close') ?></button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($unreadAnnouncements !== []): ?>
    <section class="panel start-widget" aria-labelledby="startInboxTitle">
        <div class="start-board-head">
            <h2 id="startInboxTitle">Neue Hinweise</h2>
            <p class="muted"><?= count($unreadAnnouncements) === 1 ? 'Ein neuer Hinweis vom Orga-Team.' : count($unreadAnnouncements) . ' neue Hinweise vom Orga-Team.' ?></p>
        </div>
        <div class="stack">
            <?php foreach ($unreadAnnouncements as $a): ?>
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
                <article class="detail-card">
                    <h3><?= e((string) $a['title']) ?></h3>
                    <?php if ($dateRange !== '' || trim((string) ($a['location'] ?? '')) !== ''): ?>
                        <p class="muted">
                            <?php if ($dateRange !== ''): ?><?= icon('calendar') ?> <?= e($dateRange) ?><?php endif; ?>
                            <?php if (trim((string) ($a['location'] ?? '')) !== ''): ?> · <?= icon('location') ?> <?= e((string) $a['location']) ?><?php endif; ?>
                        </p>
                    <?php endif; ?>
                    <?php if (trim((string) ($a['info'] ?? '')) !== ''): ?>
                        <div class="gallery-description"><?= nl2br(e((string) $a['info'])) ?></div>
                    <?php endif; ?>
                    <?php if (($a['links'] ?? []) !== []): ?>
                        <ul class="tight-list">
                            <?php foreach ($a['links'] as $link): ?>
                                <li><a href="<?= e((string) $link['url']) ?>" <?= $link['kind'] === 'extern' ? 'target="_blank" rel="noopener"' : '' ?>><?= icon($announcementLinkIcon[$link['kind']] ?? 'link') ?> <?= e((string) $link['label']) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <div class="card-actions">
                        <a class="ghost-button" href="<?= e(url('/termine/detail?id=' . (int) $a['id'])) ?>"><?= icon('chevron-right') ?><span>Mehr dazu</span></a>
                        <form method="post" action="<?= e(url('/termine/gelesen')) ?>">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="id" value="<?= e((string) $a['id']) ?>">
                            <button type="submit"><?= icon('check') ?><span>Als gelesen markieren</span></button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if (count($unreadAnnouncements) > 1): ?>
            <form method="post" action="<?= e(url('/termine/alle-gelesen')) ?>" class="start-board-foot">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <button type="submit" class="linkish">Alle als gelesen markieren</button>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>

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
                        <span class="start-widget-main"><?= contact_avatar($b, 'sm') ?><span><?= e(trim($b['vorname'] . ' ' . $b['nachname'])) ?></span></span>
                        <span class="start-widget-meta">
                            <?php if ($b['in_days'] === 0): ?><strong>heute</strong>
                            <?php elseif ($b['in_days'] === 1): ?>morgen
                            <?php else: ?>in <?= (int) $b['in_days'] ?> Tagen<?php endif; ?>
                            · <?= e(format_birthday($b['geburtstag'], (bool) $b['geburtstag_jahr_unbekannt'])) ?><?php if ($b['turning'] !== null): ?> · wird <?= (int) $b['turning'] ?><?php endif; ?>
                        </span>
                        <?= icon('chevron-right') ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

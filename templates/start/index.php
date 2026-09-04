<?php
$firstName = trim((string) ($currentUser['name'] ?? ''));
$firstName = $firstName !== '' ? explode(' ', $firstName)[0] : '';
$canManage = can('contacts.manage');
$canMail = can('mail.send');

// Deutscher Wochentag + Datum ohne Intl-Abhängigkeit.
$weekdays = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
$months = [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
$now = new DateTimeImmutable('now');
$todayLong = $weekdays[(int) $now->format('w')] . ', ' . (int) $now->format('j') . '. ' . $months[(int) $now->format('n')];

// „Steht an": Datenlücken als verlinkte Aufgabe. Nur für Rollen, die die
// Vollständigkeits-Seite auch öffnen dürfen – sonst führen die Links ins Leere.
$todos = [];
if ($canManage && ($stats['without_email'] ?? 0) > 0) {
    $todos[] = [
        'count' => (int) $stats['without_email'],
        'label' => 'Personen ohne Mailadresse – Lücken schließen',
        'href' => url('/vollstaendigkeit?which=email'),
    ];
}
if ($canManage && ($stats['without_phone'] ?? 0) > 0) {
    $todos[] = [
        'count' => (int) $stats['without_phone'],
        'label' => 'Personen ohne Handynummer',
        'href' => url('/vollstaendigkeit?which=phone'),
    ];
}
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

<?php $isMemberView = !$canManage && !$canMail; ?>
<div class="start-actions">
    <?php if ($canManage): ?>
        <a class="button-link" href="<?= e(url('/contacts/create')) ?>"><?= icon('plus') ?><span>Person hinzufügen</span></a>
    <?php endif; ?>
    <?php if ($canMail): ?>
        <a class="ghost-button" href="<?= e(url('/rundmail')) ?>"><?= icon('mail') ?><span>Nachricht schreiben</span></a>
    <?php endif; ?>
    <?php if ($isMemberView): ?>
        <a class="button-link" href="<?= e(url('/account')) ?>"><?= icon('user') ?><span>Meine Daten</span></a>
        <a class="ghost-button" href="<?= e(url('/orga-team')) ?>"><?= icon('mail') ?><span>Orga-Team schreiben</span></a>
    <?php endif; ?>
</div>

<?php if ($canManage): ?>
<section class="panel start-board" aria-labelledby="startBoardTitle">
    <div class="start-board-head">
        <h2 id="startBoardTitle">Steht an</h2>
        <p class="muted">
            <?php if ($todos === []): ?>
                Nichts Offenes – die Kontaktdaten sind vollständig gepflegt.
            <?php else: ?>
                <?= count($todos) === 1 ? 'Eine Sache wartet auf dich.' : count($todos) . ' Dinge warten auf dich.' ?>
            <?php endif; ?>
        </p>
    </div>

    <?php if ($todos === []): ?>
        <p class="start-board-clear">
            <?= icon('check') ?>
            <span><?= e((string) ($stats['total'] ?? 0)) ?> Kontakte, alle mit Mailadresse und Telefonnummer.</span>
        </p>
    <?php else: ?>
        <ul class="start-todo">
            <?php foreach ($todos as $todo): ?>
                <li>
                    <a class="start-todo-link" href="<?= e($todo['href']) ?>">
                        <span class="start-todo-count"><?= e((string) $todo['count']) ?></span>
                        <span class="start-todo-label"><?= e($todo['label']) ?></span>
                        <?= icon('chevron-right') ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p class="start-board-foot">
        <a href="<?= e(url('/kontakte')) ?>">Ganzes Adressbuch ansehen</a>
        · <?= e((string) ($stats['total'] ?? 0)) ?> Kontakte
    </p>
</section>
<?php endif; ?>

<?php $birthdays = $birthdays ?? []; $pendingEvents = $pendingEvents ?? []; ?>

<?php if ($pendingEvents !== []): ?>
    <section class="panel start-widget" aria-labelledby="startEventsTitle">
        <div class="start-board-head">
            <h2 id="startEventsTitle">Offene Rückmeldungen</h2>
            <p class="muted">Abstimmungen, bei denen noch nicht alle geantwortet haben.</p>
        </div>
        <ul class="start-widget-list">
            <?php foreach ($pendingEvents as $ev): ?>
                <li>
                    <a href="<?= e(url('/termine/detail?id=' . $ev['id'])) ?>">
                        <span class="start-widget-main"><?= e($ev['title']) ?></span>
                        <span class="start-widget-meta">
                            <?= (int) $ev['answered_count'] ?>&nbsp;/&nbsp;<?= (int) $ev['participant_count'] ?> geantwortet<?php
                            if (trim((string) ($ev['closes_at'] ?? '')) !== ''):
                                ?> · Frist <?= e(time_until_hint($ev['closes_at'])) ?><?php
                            endif; ?>
                        </span>
                        <?= icon('chevron-right') ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
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
                    <a href="<?= e(url('/contacts/edit?id=' . $b['id'])) ?>">
                        <span class="start-widget-main"><?= e(trim($b['vorname'] . ' ' . $b['nachname'])) ?></span>
                        <span class="start-widget-meta">
                            <?php if ($b['in_days'] === 0): ?>
                                <strong>heute</strong>
                            <?php elseif ($b['in_days'] === 1): ?>
                                morgen
                            <?php else: ?>
                                in <?= (int) $b['in_days'] ?> Tagen
                            <?php endif; ?>
                            · <?= e(format_date($b['geburtstag'])) ?><?php if ($b['turning'] !== null): ?> · wird <?= (int) $b['turning'] ?><?php endif; ?>
                        </span>
                        <?= icon('chevron-right') ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

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

// „Steht an": dieselben Kennzahlen wie früher, aber als verlinkte Aufgabe.
$todos = [];
if (($stats['without_email'] ?? 0) > 0) {
    $todos[] = [
        'count' => (int) $stats['without_email'],
        'label' => 'Personen ohne Mailadresse – Lücken schließen',
        'href' => url('/kontakte?without_email=1'),
    ];
}
if (($stats['without_phone'] ?? 0) > 0) {
    $todos[] = [
        'count' => (int) $stats['without_phone'],
        'label' => 'Personen ohne Handynummer',
        'href' => url('/kontakte?without_phone=1'),
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

<?php if ($canManage || $canMail): ?>
    <div class="start-actions">
        <?php if ($canManage): ?>
            <a class="button-link" href="<?= e(url('/contacts/create')) ?>"><?= icon('plus') ?><span>Person hinzufügen</span></a>
        <?php endif; ?>
        <?php if ($canMail): ?>
            <a class="ghost-button" href="<?= e(url('/rundmail')) ?>"><?= icon('mail') ?><span>Nachricht schreiben</span></a>
        <?php endif; ?>
    </div>
<?php endif; ?>

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

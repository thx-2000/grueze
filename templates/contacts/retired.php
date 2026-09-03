<?php
/** @var list<array<string,mixed>> $archived */
/** @var list<array<string,mixed>> $trashed */
/** @var int $trashDays */
$fullName = static fn (array $c): string => trim($c['vorname'] . ' ' . $c['nachname']);

$row = static function (array $c, bool $trash) use ($fullName, $csrfToken): void {
    $days = (int) ($c['purge_in_days'] ?? 0);
    ?>
    <li class="retired-row">
        <div class="retired-person">
            <strong><?= e($fullName($c)) ?></strong>
            <?php if (trim((string) ($c['geburtsname'] ?? '')) !== ''): ?>
                <span class="birth-name-inline">(ehem. <?= e((string) $c['geburtsname']) ?>)</span>
            <?php endif; ?>
            <?php if (trim((string) ($c['category_name'] ?? '')) !== ''): ?>
                <span class="muted"><?= e((string) $c['category_name']) ?></span>
            <?php endif; ?>
            <span class="retired-meta muted">
                <?php if ($trash): ?>
                    Papierkorb seit <?= e(format_date(substr((string) $c['deleted_at'], 0, 10))) ?> ·
                    <?php if ($days > 0): ?>
                        endgültige Löschung in <?= $days ?> <?= $days === 1 ? 'Tag' : 'Tagen' ?>
                    <?php else: ?>
                        wird beim nächsten Aufräumen gelöscht
                    <?php endif; ?>
                <?php else: ?>
                    archiviert seit <?= e(format_date(substr((string) $c['archived_at'], 0, 10))) ?>
                <?php endif; ?>
                <?php if (trim((string) ($c['retired_by_name'] ?? '')) !== ''): ?>
                    · von <?= e((string) $c['retired_by_name']) ?>
                <?php endif; ?>
            </span>
        </div>
        <div class="retired-actions">
            <form method="post" action="<?= e(url('/contacts/wiederherstellen')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" value="<?= e((string) $c['id']) ?>">
                <button type="submit" class="ghost-button"><?= icon('check') ?><span>Zurückholen</span></button>
            </form>
            <?php if ($trash): ?>
                <form method="post" action="<?= e(url('/contacts/endgueltig-loeschen')) ?>" data-confirm="„<?= e($fullName($c)) ?>“ jetzt endgültig und unwiderruflich löschen?">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="id" value="<?= e((string) $c['id']) ?>">
                    <button type="submit" class="danger-button"><?= icon('trash') ?><span>Endgültig löschen</span></button>
                </form>
            <?php else: ?>
                <form method="post" action="<?= e(url('/contacts/delete')) ?>" data-confirm="„<?= e($fullName($c)) ?>“ vom Archiv in den Papierkorb verschieben? Nach <?= $trashDays ?> Tagen wird der Kontakt gelöscht.">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="id" value="<?= e((string) $c['id']) ?>">
                    <input type="hidden" name="mode" value="trash">
                    <button type="submit" class="ghost-button"><?= icon('trash') ?><span>In den Papierkorb</span></button>
                </form>
            <?php endif; ?>
        </div>
    </li>
    <?php
};
?>
<p class="detail-backlink"><a href="<?= e(url('/kontakte')) ?>"><?= icon('chevron-right') ?>Zurück zum Adressbuch</a></p>

<header class="contacts-header">
    <div>
        <h1>Archiv &amp; Papierkorb</h1>
        <p class="muted">Kontakte, die nicht mehr im aktiven Adressbuch stehen. Archivierte bleiben dauerhaft, Papierkorb-Einträge werden nach <?= $trashDays ?> Tagen endgültig gelöscht.</p>
    </div>
</header>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Papierkorb</h2>
            <p class="muted"><?= count($trashed) ?> <?= count($trashed) === 1 ? 'Kontakt' : 'Kontakte' ?> · endgültige Löschung automatisch nach <?= $trashDays ?> Tagen.</p>
        </div>
    </div>
    <?php if ($trashed === []): ?>
        <p class="completeness-clear"><?= icon('check') ?><span>Der Papierkorb ist leer.</span></p>
    <?php else: ?>
        <ul class="retired-list">
            <?php foreach ($trashed as $c) { $row($c, true); } ?>
        </ul>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Archiv</h2>
            <p class="muted"><?= count($archived) ?> <?= count($archived) === 1 ? 'Kontakt' : 'Kontakte' ?> · bleiben dauerhaft erhalten.</p>
        </div>
    </div>
    <?php if ($archived === []): ?>
        <p class="completeness-clear"><?= icon('check') ?><span>Noch nichts archiviert.</span></p>
    <?php else: ?>
        <ul class="retired-list">
            <?php foreach ($archived as $c) { $row($c, false); } ?>
        </ul>
    <?php endif; ?>
</section>

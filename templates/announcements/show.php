<?php
/**
 * @var array<string,mixed>      $announcement
 * @var bool                     $canManage
 * @var list<string>             $audienceLabels
 * @var array<string,mixed>|null $pickerData
 */
$a = $announcement;
$today = (new DateTimeImmutable('now'))->format('Y-m-d');
$startsAt = trim((string) ($a['starts_at'] ?? ''));
$endsAt = trim((string) ($a['ends_at'] ?? ''));
$isPast = $endsAt !== '' ? $endsAt < $today : ($startsAt !== '' && $startsAt < $today);

$dateRange = '';
if ($startsAt !== '' && $endsAt !== '' && $endsAt !== $startsAt) {
    $dateRange = format_date($startsAt) . ' – ' . format_date($endsAt);
} elseif ($startsAt !== '') {
    $dateRange = format_date($startsAt);
}

$linkIcon = ['extern' => 'globe', 'dokument' => 'file', 'abstimmung' => 'poll'];
?>
<p class="detail-backlink"><a href="<?= e(url('/termine')) ?>"><?= icon('chevron-right') ?>Zurück zu den Terminen</a></p>

<header class="contact-detail-head">
    <p class="eyebrow">Termin<?= $isPast ? ' · vorbei' : '' ?></p>
    <h1><?= e((string) $a['title']) ?></h1>
    <div class="contact-detail-meta">
        <?php if ($dateRange !== ''): ?><span><?= icon('calendar') ?> <?= e($dateRange) ?></span><?php endif; ?>
        <?php if (trim((string) ($a['location'] ?? '')) !== ''): ?><span><?= icon('location') ?> <?= e((string) $a['location']) ?></span><?php endif; ?>
    </div>
    <?php if ($canManage && $audienceLabels !== []): ?>
        <p class="muted"><?= icon('eye') ?> Sichtbar für: <?= e(implode(', ', $audienceLabels)) ?> (du siehst es als Verwaltung immer).</p>
    <?php endif; ?>
</header>

<?php if (trim((string) ($a['info'] ?? '')) !== ''): ?>
    <section class="detail-card">
        <div class="gallery-description"><?= nl2br(e((string) $a['info'])) ?></div>
    </section>
<?php endif; ?>

<?php if (($a['links'] ?? []) !== []): ?>
    <section class="detail-card">
        <h2>Links</h2>
        <ul class="tight-list">
            <?php foreach ($a['links'] as $link): ?>
                <li><a href="<?= e((string) $link['url']) ?>" <?= $link['kind'] === 'extern' ? 'target="_blank" rel="noopener"' : '' ?>><?= icon($linkIcon[$link['kind']] ?? 'link') ?> <?= e((string) $link['label']) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<?php if ($canManage): ?>
    <details class="panel gallery-settings">
        <summary>Ankündigung bearbeiten</summary>
        <form method="post" action="<?= e(url('/termine/speichern')) ?>" class="stack">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= e((string) $a['id']) ?>">
            <?php view_partial('announcements/_fields', [
                'announcement' => $a,
                'pickerData' => $pickerData,
                'audienceRows' => $audienceRows ?? [],
            ]); ?>
            <div class="form-actions">
                <button type="submit" class="button-link"><?= icon('check') ?><span>Speichern</span></button>
            </div>
        </form>
    </details>

    <section class="detail-card detail-danger">
        <h2>Ankündigung löschen</h2>
        <p class="muted">Löschen entfernt die Ankündigung unwiderruflich.</p>
        <form method="post" action="<?= e(url('/termine/loeschen')) ?>" data-confirm="Ankündigung „<?= e((string) $a['title']) ?>“ endgültig löschen?">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= e((string) $a['id']) ?>">
            <button type="submit" class="danger-button"><?= icon('trash') ?><span>Löschen</span></button>
        </form>
    </section>
<?php endif; ?>

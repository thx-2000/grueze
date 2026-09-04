<?php
/**
 * @var list<array<string,mixed>> $entries
 * @var int $currentUserId
 */
$kindLabel = static fn (string $k): string => match ($k) {
    'rundmail' => 'Rundmail',
    'einzeln' => 'Einzelkontakt',
    'termin' => 'zu einem Termin',
    'gruesse' => 'Grüße',
    default => $k,
};
?>
<header class="page-head">
    <p class="eyebrow">Nachrichten</p>
    <h1>Gesendete Nachrichten</h1>
    <p class="muted">Frühere Serien-Mails – zum Nachlesen und, wenn nötig, erneut verschicken (an alle oder an einzelne Personen).</p>
</header>

<section class="panel">
    <?php if ($entries === []): ?>
        <p class="muted">Es wurde noch keine Nachricht über den Serienversand verschickt.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Zeitpunkt</th>
                        <th>Betreff</th>
                        <th>Von</th>
                        <th>Art</th>
                        <th>Empfänger</th>
                        <th><span class="visually-hidden">Öffnen</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td><?= e(format_datetime((string) $entry['created_at'])) ?></td>
                            <td>
                                <a href="<?= e(url('/rundmail/verlauf/ansehen?id=' . (int) $entry['id'])) ?>">
                                    <?= e(trim(((string) $entry['subject_prefix'] !== '' ? $entry['subject_prefix'] . ' ' : '') . (string) $entry['subject'])) ?: '(ohne Betreff)' ?>
                                </a>
                            </td>
                            <td>
                                <?= e((string) ($entry['current_sender_name'] ?: $entry['sender_name']) ?: 'unbekannt') ?>
                                <?php if ((int) $entry['user_id'] === $currentUserId): ?><span class="status-chip is-ok">du</span><?php endif; ?>
                            </td>
                            <td><?= e($kindLabel((string) $entry['kind'])) ?></td>
                            <td>
                                <?= e((string) $entry['sent_count']) ?>/<?= e((string) $entry['recipient_count']) ?>
                                <?php if ((int) $entry['failed_count'] > 0): ?>
                                    <span class="status-chip is-warn"><?= e((string) $entry['failed_count']) ?> Fehler</span>
                                <?php endif; ?>
                            </td>
                            <td><a class="row-open" href="<?= e(url('/rundmail/verlauf/ansehen?id=' . (int) $entry['id'])) ?>" aria-label="Nachricht öffnen"><?= icon('chevron-right') ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

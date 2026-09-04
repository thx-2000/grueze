<?php
/**
 * @var bool $linked
 * @var list<array<string,mixed>> $entries
 */
?>
<header class="page-head">
    <p class="eyebrow">Mein Eintrag</p>
    <h1>Erhaltene Mails</h1>
    <p class="muted">Die Rundmails, die an dich gegangen sind – zum Nachlesen. Du kannst dir jede davon nochmal ans eigene Postfach schicken lassen.</p>
</header>

<section class="panel">
    <?php if (!$linked): ?>
        <p class="muted">Für dich ist noch kein Eintrag im Adressbuch verknüpft – daher gibt es hier nichts anzuzeigen.</p>
    <?php elseif ($entries === []): ?>
        <p class="muted">An dich wurde noch keine Rundmail über dieses System verschickt.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Zeitpunkt</th>
                        <th>Betreff</th>
                        <th>Von</th>
                        <th>Status</th>
                        <th><span class="visually-hidden">Öffnen</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td><?= e(format_datetime((string) $entry['created_at'])) ?></td>
                            <td>
                                <a href="<?= e(url('/meine-nachrichten/ansehen?id=' . (int) $entry['id'])) ?>">
                                    <?= e(trim(((string) $entry['subject_prefix'] !== '' ? $entry['subject_prefix'] . ' ' : '') . (string) $entry['subject'])) ?: '(ohne Betreff)' ?>
                                </a>
                            </td>
                            <td><?= e((string) ($entry['current_sender_name'] ?: $entry['sender_name']) ?: 'unbekannt') ?></td>
                            <td>
                                <?php if (($entry['own_status'] ?? 'gesendet') === 'gesendet'): ?>
                                    <span class="status-chip is-ok">zugestellt</span>
                                <?php else: ?>
                                    <span class="status-chip is-warn">nicht zugestellt</span>
                                <?php endif; ?>
                            </td>
                            <td><a class="row-open" href="<?= e(url('/meine-nachrichten/ansehen?id=' . (int) $entry['id'])) ?>" aria-label="Nachricht öffnen"><?= icon('chevron-right') ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

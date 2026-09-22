<?php
$actionLabel = static fn (string $a): string => match ($a) {
    'created' => 'angelegt',
    'updated' => 'geändert',
    'deleted' => 'gelöscht',
    'impersonation_started' => 'Sitzung als Person gestartet',
    'impersonation_stopped' => 'Sitzung als Person beendet',
    'login' => 'Anmeldung',
    default => $a,
};
?>
<header class="page-head">
    <p class="eyebrow">Protokoll</p>
    <h1>Änderungsprotokoll</h1>
    <p class="muted">Chronologische Übersicht der letzten Aktivitäten – wer wann was geändert hat.</p>
</header>

<section class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Zeitpunkt</th>
                    <th>Wer</th>
                    <th>Kontakt</th>
                    <th>Aktion</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $entry): ?>
                    <?php $contactName = trim(($entry['vorname'] ?? '') . ' ' . ($entry['nachname'] ?? '')); ?>
                    <tr>
                        <td><?= e(format_datetime($entry['created_at'])) ?></td>
                        <td><?= e($entry['user_name']) ?></td>
                        <td>
                            <?php if ($contactName !== ''): ?>
                                <?= e($contactName) ?>
                            <?php elseif ($entry['contact_id'] !== null): ?>
                                Gelöschter Kontakt
                            <?php else: ?>
                                <span class="muted">–</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($actionLabel((string) $entry['action'])) ?></td>
                        <td>
                            <?= e($entry['details']) ?>
                            <?php if (!empty($entry['changes'])): ?>
                                <ul class="history-changes">
                                    <?php foreach ($entry['changes'] as $field => $change): ?>
                                        <li>
                                            <span class="history-field"><?= e((string) $field) ?></span>
                                            <span class="history-from"><?= e((string) ($change['from'] ?? '—')) ?></span>
                                            <span class="history-arrow" aria-hidden="true">→</span>
                                            <span class="history-to"><?= e((string) ($change['to'] ?? '—')) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

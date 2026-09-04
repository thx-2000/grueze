<?php
$actionLabel = static fn (string $a): string => match ($a) {
    'created' => 'angelegt',
    'updated' => 'geändert',
    'deleted' => 'gelöscht',
    'impersonation_started' => 'Sitzung als Person gestartet',
    'impersonation_stopped' => 'Sitzung als Person beendet',
    default => $a,
};
?>
<header class="page-head">
    <p class="eyebrow">Protokoll</p>
    <h1>Änderungsprotokoll</h1>
    <p class="muted">Chronologische Übersicht der letzten Aktivitäten – wer wann welchen Kontakt geändert hat.</p>
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
                    <tr>
                        <td><?= e(format_datetime($entry['created_at'])) ?></td>
                        <td><?= e($entry['user_name']) ?></td>
                        <td><?= e(trim(($entry['vorname'] ?? '') . ' ' . ($entry['nachname'] ?? '')) ?: 'Gelöschter Kontakt') ?></td>
                        <td><?= e($actionLabel((string) $entry['action'])) ?></td>
                        <td><?= e($entry['details']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

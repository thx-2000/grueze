<section class="hero-card">
    <p class="eyebrow">Audit-Log</p>
    <h2>Änderungen an Kontakten</h2>
    <p class="muted">Chronologische Übersicht der letzten Aktivitäten.</p>
</section>

<section class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Zeitpunkt</th>
                    <th>Nutzer</th>
                    <th>Kontakt</th>
                    <th>Aktion</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td><?= e($entry['created_at']) ?></td>
                        <td><?= e($entry['user_name']) ?></td>
                        <td><?= e(trim(($entry['vorname'] ?? '') . ' ' . ($entry['nachname'] ?? '')) ?: 'Gelöschter Kontakt') ?></td>
                        <td><?= e($entry['action']) ?></td>
                        <td><?= e($entry['details']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>


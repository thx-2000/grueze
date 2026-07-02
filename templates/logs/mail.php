<section class="hero-card">
    <p class="eyebrow">Versandprotokoll</p>
    <h2>Letzte Einzelversände</h2>
    <p class="muted">Jede Mail wird separat dokumentiert.</p>
</section>

<section class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Zeitpunkt</th>
                    <th>Nutzer</th>
                    <th>Kontakt</th>
                    <th>Empfänger</th>
                    <th>Betreff</th>
                    <th>Status</th>
                    <th>Fehler</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td><?= e(format_datetime($entry['gesendet_am'])) ?></td>
                        <td><?= e($entry['user_name']) ?></td>
                        <td><?= e(trim(($entry['vorname'] ?? '') . ' ' . ($entry['nachname'] ?? '')) ?: '-') ?></td>
                        <td><?= e($entry['empfaenger_email']) ?></td>
                        <td><?= e($entry['betreff']) ?></td>
                        <td><?= e($entry['status']) ?></td>
                        <td><?= e($entry['fehlermeldung'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php
$appliedNames = array_column($applied, 'migration');
$appliedDates = array_column($applied, 'applied_at', 'migration');
?>
<section class="hero-card narrow">
    <div>
        <p class="eyebrow">Administration</p>
        <h2>Datenbank-Migrationen</h2>
        <p class="muted">Hier siehst du welche Migrationen bereits angewendet wurden und welche noch ausstehen. Nur anwenden, wenn du weißt was du tust — ein Fehler kann die Datenbank beschädigen.</p>
    </div>
</section>

<?php if ($pending !== []): ?>
    <section class="panel">
        <div class="panel-head">
            <div>
                <h3>Ausstehende Migrationen</h3>
                <p class="muted"><?= count($pending) === 1 ? '1 Migration' : count($pending) . ' Migrationen' ?> noch nicht angewendet.</p>
            </div>
        </div>
        <div class="stack">
            <?php foreach ($pending as $name => $path): ?>
                <div class="subsection-card">
                    <strong><?= e($name) ?></strong>
                    <form method="post" action="<?= e(url('/admin/migrations/apply')) ?>" class="toolbar-actions" style="margin-top:0.6rem">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="migration" value="<?= e($name) ?>">
                        <button type="submit" onclick="return confirm('Migration <?= e(addslashes($name)) ?> wirklich anwenden?')">Anwenden</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php else: ?>
    <section class="panel">
        <p class="muted">Alle Migrationen sind auf dem aktuellen Stand.</p>
    </section>
<?php endif; ?>

<section class="panel">
    <div class="panel-head">
        <div>
            <h3>Angewendete Migrationen</h3>
            <p class="muted"><?= count($applied) ?> Migration<?= count($applied) !== 1 ? 'en' : '' ?> in der Datenbank.</p>
        </div>
    </div>
    <?php if ($applied === []): ?>
        <p class="muted">Noch keine Migrationen vermerkt. Bitte die Migrations-Tabelle zuerst anlegen.</p>
    <?php else: ?>
        <table class="contacts-table">
            <thead>
                <tr>
                    <th>Migration</th>
                    <th>Angewendet am</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applied as $row): ?>
                    <tr>
                        <td><?= e($row['migration']) ?></td>
                        <td class="muted"><?= e($row['applied_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php
$appliedNames = array_column($applied, 'migration');
$pendingCount = count($pendingMigrations);
$installedLabel = $installedVersion !== null ? 'v' . $installedVersion : 'unbekannt';
?>
<header class="page-head page-head--split">
    <div>
        <p class="eyebrow">Administration</p>
        <h1>Aktualisieren</h1>
        <p class="muted">
            Nach einem Datei-Upload bringt dieser Schritt die Datenbank auf den
            passenden Stand. Bestandsdaten bleiben dabei erhalten – Migrationen
            sind additiv, und auf Wunsch wird vorher eine Sicherung abgelegt.
        </p>
        <?php if ($lastUpdatedAt !== null): ?>
            <p class="detail-hint">Zuletzt aktualisiert: <?= e(format_datetime($lastUpdatedAt)) ?></p>
        <?php endif; ?>
    </div>
    <span class="version-badge">
        <span class="version-badge-from"><?= e($installedLabel) ?></span>
        <span aria-hidden="true">→</span>
        <span class="version-badge-to">v<?= e($codeVersion) ?></span>
    </span>
</header>

<?php if ($locked): ?>
    <section class="panel">
        <p class="flash flash-error" role="alert">Es läuft gerade ein Update. Bitte die Seite in ein paar Sekunden neu laden.</p>
    </section>
<?php elseif ($pendingCount > 0): ?>
    <section class="panel stack">
        <div class="panel-head">
            <div>
                <h3>Update bereit</h3>
                <p class="muted"><?= $pendingCount === 1 ? '1 Migration' : $pendingCount . ' Migrationen' ?> noch nicht angewendet.</p>
            </div>
        </div>

        <ul class="update-migration-list">
            <?php foreach ($pendingMigrations as $migration): ?>
                <li>
                    <strong><?= e($migration['name']) ?></strong>
                    <?php if ($migration['description'] !== ''): ?>
                        <span class="muted"><?= e($migration['description']) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <form method="post" action="<?= e(url('/admin/aktualisieren')) ?>" class="stack update-run-form">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <label class="inline-toggle">
                <input type="checkbox" name="with_backup" value="1" checked>
                <span>Vorher eine Datensicherung anlegen (empfohlen – landet in <code>storage/backups/</code>)</span>
            </label>
            <div class="toolbar-actions">
                <button type="submit" onclick="return confirm('Update jetzt anwenden? Alle offenen Migrationen werden ausgeführt.')">
                    <?= icon('upload') ?><span>Jetzt aktualisieren</span>
                </button>
            </div>
        </form>
    </section>
<?php else: ?>
    <section class="panel">
        <p class="muted">Die Datenbank ist auf dem aktuellen Stand
            <?php if ($installedVersion !== null && $installedVersion !== $codeVersion): ?>
                – Version wird beim nächsten Update auf <strong>v<?= e($codeVersion) ?></strong> gesetzt.
            <?php else: ?>
                (v<?= e($codeVersion) ?>).
            <?php endif; ?>
        </p>
    </section>
<?php endif; ?>

<?php if (trim($changelog) !== ''): ?>
    <section class="panel">
        <details class="admin-drawer">
            <summary><span><?= icon('history') ?></span><span>Änderungen in dieser Version</span></summary>
            <div class="admin-drawer-body">
                <pre class="changelog-block"><?= e($changelog) ?></pre>
            </div>
        </details>
    </section>
<?php endif; ?>

<section class="panel">
    <details class="admin-drawer">
        <summary><span><?= icon('sliders') ?></span><span>Migrationen im Detail (<?= count($applied) ?> angewendet<?= $pendingCount > 0 ? ', ' . $pendingCount . ' offen' : '' ?>)</span></summary>
        <div class="admin-drawer-body stack">
            <p class="detail-hint">Einzelanwendung als Fallback – im Normalfall reicht „Jetzt aktualisieren".</p>

            <?php if ($pendingMigrations !== []): ?>
                <div class="stack">
                    <?php foreach ($pendingMigrations as $migration): ?>
                        <div class="subsection-card">
                            <strong><?= e($migration['name']) ?></strong>
                            <?php if ($migration['description'] !== ''): ?>
                                <p class="muted"><?= e($migration['description']) ?></p>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('/admin/migrations/apply')) ?>" class="toolbar-actions" style="margin-top:0.6rem">
                                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="migration" value="<?= e($migration['name']) ?>">
                                <button type="submit" class="ghost-button" onclick="return confirm('Migration <?= e(addslashes($migration['name'])) ?> einzeln anwenden?')">Nur diese anwenden</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($applied === []): ?>
                <p class="muted">Noch keine Migrationen vermerkt.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="contacts-table">
                        <thead>
                            <tr>
                                <th scope="col">Migration</th>
                                <th scope="col">Angewendet am</th>
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
                </div>
            <?php endif; ?>
        </div>
    </details>
</section>

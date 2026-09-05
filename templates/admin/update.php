<?php
$appliedNames = array_column($applied, 'migration');
$pendingCount = count($pendingMigrations);
$installedLabel = $installedVersion !== null ? 'v' . $installedVersion : 'unbekannt';
$release = $release ?? ['enabled' => false, 'available' => false, 'latest' => null, 'url' => '', 'published_at' => null, 'checked_at' => null];
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

<?php if ($release['enabled'] && $release['available']): ?>
    <section class="panel stack new-release-panel">
        <div class="panel-head">
            <div>
                <h3><?= icon('sparkles') ?> Neue Version <?= e('v' . $release['latest']) ?> verfügbar</h3>
                <p class="muted">
                    Ihr nutzt gerade <strong>v<?= e($codeVersion) ?></strong>.
                    <?php if ($release['published_at']): ?>
                        Veröffentlicht am <?= e(format_date(substr((string) $release['published_at'], 0, 10))) ?>.
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <div class="toolbar-actions">
            <a class="button-link" href="<?= e($release['url']) ?>" target="_blank" rel="noopener">
                <?= icon('history') ?><span>Was ist neu? (Changelog auf GitHub)</span>
            </a>
        </div>
        <p class="detail-hint">
            Das Aktualisieren ist unten Schritt für Schritt beschrieben. Eure
            Einstellungen und Daten bleiben dabei erhalten.
        </p>
    </section>
<?php elseif ($release['enabled'] && $release['latest'] !== null): ?>
    <p class="detail-hint">
        <?= icon('check') ?> Ihr seid auf dem neuesten Stand (v<?= e($codeVersion) ?>).
        <?php if ($release['checked_at']): ?>Zuletzt geprüft: <?= e(format_datetime($release['checked_at'])) ?>.<?php endif; ?>
    </p>
<?php endif; ?>

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

        <form method="post" action="<?= e(url('/admin/aktualisieren')) ?>" class="stack update-run-form" data-confirm="Update jetzt anwenden? Alle offenen Migrationen werden ausgeführt.">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <label class="inline-toggle">
                <input type="checkbox" name="with_backup" value="1" checked>
                <span>Vorher eine Datensicherung anlegen (empfohlen – landet in <code>storage/backups/</code>)</span>
            </label>
            <div class="toolbar-actions">
                <button type="submit">
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

<section class="panel stack">
    <div class="panel-head">
        <div>
            <h3>So läuft ein Update</h3>
            <p class="muted">In drei Schritten – Einstellungen, Kontakte, Medien und Dokumente bleiben dabei unangetastet.</p>
        </div>
    </div>
    <ol class="update-steps">
        <li>
            <strong>Neue Dateien holen.</strong>
            Die aktuelle Version gibt es auf GitHub unter
            <a href="https://github.com/thx-2000/grueze/releases/latest" target="_blank" rel="noopener">Releases</a>
            (ZIP „Source code") – oder per <code>git pull</code> im Projektordner.
        </li>
        <li>
            <strong>Auf den Server laden.</strong>
            Am einfachsten mit <code>bash scripts/deploy.sh</code>. Wer die Dateien
            von Hand per FTP hochlädt, lässt dabei die Ordner
            <code>config/</code> und <code>storage/</code> unberührt – dort liegen
            Zugangsdaten, der Verschlüsselungs-Schlüssel und alle hochgeladenen
            Dateien. Der Rest darf überschrieben werden.
        </li>
        <li>
            <strong>Hier auf „Jetzt aktualisieren" klicken.</strong>
            Der Haken „Vorher eine Datensicherung anlegen" bleibt am besten
            gesetzt. Offene Datenbank-Migrationen laufen dann der Reihe nach –
            sie <strong>ergänzen</strong> die Datenbank nur (neue Spalten,
            neue Tabellen), es wird nie etwas gelöscht oder geleert.
        </li>
    </ol>
    <p class="detail-hint">
        Zwischen dem Hochladen und diesem Klick kann die Seite kurz einen Fehler
        zeigen, falls neuer Code auf eine noch fehlende Spalte trifft – nach dem
        Update ist das weg. Wer den Klick sparen will, setzt
        <code>app.auto_migrate</code> in der <code>config/config.php</code> auf
        <code>true</code>; dann laufen Migrationen beim ersten Aufruf von selbst.
    </p>
</section>

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
                                <button type="submit" class="ghost-button" data-confirm="Migration <?= e($migration['name']) ?> einzeln anwenden?">Nur diese anwenden</button>
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

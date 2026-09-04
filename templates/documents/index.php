<?php
/**
 * @var list<array<string,mixed>> $folders
 * @var bool $canCreate
 * @var bool $canManage
 */
?>
<header class="contacts-header">
    <div>
        <h1>Dokumente</h1>
        <p class="muted">Formulare, Vorlagen und andere Dateien für Orga-Team und Gruppenleitung – Ansehen und Herunterladen nach Rechten geschützt.</p>
    </div>
    <?php if ($canCreate): ?>
        <a class="button-link" href="<?= e(url('/dokumente/neu')) ?>"><?= icon('plus') ?><span>Neuer Ordner</span></a>
    <?php endif; ?>
</header>

<?php if ($folders === []): ?>
    <section class="panel">
        <p class="muted">
            <?php if ($canCreate): ?>
                Noch kein Ordner. <a href="<?= e(url('/dokumente/neu')) ?>">Ersten Ordner anlegen</a>.
            <?php else: ?>
                Es gibt noch keinen Ordner.
            <?php endif; ?>
        </p>
    </section>
<?php else: ?>
    <div class="gallery-grid document-folder-grid">
        <?php foreach ($folders as $f): ?>
            <?php $count = (int) $f['document_count']; ?>
            <a class="gallery-card document-folder-card" href="<?= e(url('/dokumente/ansehen?id=' . (int) $f['id'])) ?>">
                <span class="gallery-card-cover document-folder-cover">
                    <?= icon('folder') ?>
                </span>
                <span class="gallery-card-body">
                    <strong><?= e($f['title']) ?></strong>
                    <span class="muted"><?= $count === 1 ? '1 Datei' : e((string) $count) . ' Dateien' ?></span>
                    <?php if (trim((string) ($f['visible_group_name'] ?? '')) !== ''): ?>
                        <span class="gallery-card-event"><?= icon('eye') ?>nur „<?= e($f['visible_group_name']) ?>"</span>
                    <?php elseif (trim((string) ($f['owner_group_name'] ?? '')) !== ''): ?>
                        <span class="gallery-card-event"><?= icon('users') ?><?= e($f['owner_group_name']) ?></span>
                    <?php endif; ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

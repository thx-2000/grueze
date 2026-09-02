<header class="page-head">
    <p class="eyebrow">Verwaltung</p>
    <h1>Rollen</h1>
    <p class="muted">
        Anzeigename und Beschreibung frei wählen, eigene Rollen anlegen. Was
        eine Rolle darf und sieht, wird unter <a href="<?= e(url('/settings/permissions')) ?>">Berechtigungen</a>
        und <a href="<?= e(url('/settings/visibility')) ?>">Sichtbarkeit</a> festgelegt.
        <strong>Admin</strong> hat immer alle Rechte.
    </p>
</header>

<section class="panel stack">
    <div class="panel-head"><div><h3>Vorhandene Rollen</h3></div></div>

    <div class="taxo-list">
        <?php foreach ($roles as $role): ?>
            <div class="taxo-row">
                <form method="post" action="<?= e(url('/settings/roles/update')) ?>" class="role-edit">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="id" value="<?= e((string) $role['id']) ?>">
                    <div class="role-edit-fields">
                        <input type="text" name="label" value="<?= e((string) ($role['label'] ?: $role['name'])) ?>" required aria-label="Anzeigename der Rolle" placeholder="Anzeigename">
                        <input type="text" name="description" value="<?= e((string) $role['description']) ?>" aria-label="Beschreibung der Rolle" placeholder="Kurzbeschreibung">
                    </div>
                    <div class="role-edit-meta">
                        <span class="taxo-count" title="Interner Schlüssel – für Rechte-Zuordnung, nicht änderbar"><code><?= e((string) $role['name']) ?></code></span>
                        <span class="taxo-count"><?= e((string) $role['user_count']) ?> <?= (int) $role['user_count'] === 1 ? 'Benutzer' : 'Benutzer' ?></span>
                        <button type="submit" class="ghost-button compact-action">Speichern</button>
                    </div>
                </form>
                <?php if ($role['protected']): ?>
                    <span class="role-lock" title="Geschützt"><?= icon('lock') ?></span>
                <?php elseif ((int) $role['user_count'] > 0): ?>
                    <span class="role-lock" title="Erst Benutzer umziehen"><?= icon('lock') ?></span>
                <?php else: ?>
                    <form method="post" action="<?= e(url('/settings/roles/delete')) ?>" onsubmit="return confirm('Rolle „<?= e(addslashes((string) ($role['label'] ?: $role['name']))) ?>“ wirklich löschen?');">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="id" value="<?= e((string) $role['id']) ?>">
                        <button type="submit" class="danger-button icon-button" title="Rolle löschen" aria-label="Rolle löschen"><?= icon('trash') ?></button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <form method="post" action="<?= e(url('/settings/roles/store')) ?>" class="role-add">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="text" name="label" placeholder="Anzeigename, z. B. „Kassenwart“" required aria-label="Anzeigename der neuen Rolle">
        <input type="text" name="description" placeholder="Kurzbeschreibung (optional)" aria-label="Beschreibung der neuen Rolle">
        <button type="submit"><?= icon('plus') ?><span>Rolle anlegen</span></button>
    </form>
</section>

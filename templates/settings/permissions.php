<?php
$roleLabels = [
    'orga'           => 'Orga',
    'stufenmitglied' => 'Stufenmitglied',
    'betrachter'     => 'Betrachter',
];
?>
<section class="hero-card narrow">
    <div>
        <p class="eyebrow">Einstellungen</p>
        <h2>Berechtigungen</h2>
        <p class="muted">Hier legst du fest, welche Rollen welche Aktionen ausführen dürfen. <strong>Admin hat immer alle Rechte</strong> und kann hier nicht eingeschränkt werden.</p>
    </div>
</section>

<section class="panel">
    <form method="post" action="<?= e(url('/settings/permissions')) ?>" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <?php foreach ($permissionGroups as $groupLabel => $groupPerms): ?>
            <div class="subsection-card stack">
                <strong><?= e($groupLabel) ?></strong>
                <?php foreach ($groupPerms as $permission => $description): ?>
                    <div class="permission-row">
                        <span class="permission-label"><?= e($description) ?></span>
                        <div class="tag-picker">
                            <?php foreach ($configurableRoles as $role): ?>
                                <label class="inline-toggle">
                                    <input type="checkbox"
                                           name="permissions[<?= e($permission) ?>][]"
                                           value="<?= e($role) ?>"
                                        <?= in_array($role, $matrix[$permission] ?? [], true) ? 'checked' : '' ?>>
                                    <span><?= e($roleLabels[$role] ?? $role) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <?php
                        $defaultRoles = $defaults[$permission] ?? [];
                        $defaultLabels = array_map(static fn (string $r): string => $roleLabels[$r] ?? $r, $defaultRoles);
                        $defaultText = $defaultLabels !== [] ? implode(', ', $defaultLabels) : 'Nur Admin';
                        ?>
                        <p class="field-hint">Standard: <?= e($defaultText) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <div class="toolbar-actions">
            <button type="submit">Berechtigungen speichern</button>
        </div>
    </form>
</section>

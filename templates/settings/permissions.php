<?php
/**
 * @var array<string,array<string,string>> $permissionGroups  Gruppenname => [Recht => Beschreibung]
 * @var array<string,string> $permissionGroupIcons  Gruppenname => Icon-Name
 * @var array<string,array<string>> $matrix       Recht => aktive Rollen
 * @var array<string,array<string>> $defaults      Recht => Standard-Rollen
 * @var list<string> $configurableRoles
 * @var array<string,string> $roleLabels
 */
?>
<header class="page-head">
    <p class="eyebrow">Einstellungen</p>
    <h1>Berechtigungen</h1>
    <p class="muted">Wer darf was. <strong>Admin hat immer alle Rechte</strong> und kann hier nicht eingeschränkt werden.</p>
</header>

<section class="panel">
    <form method="post" action="<?= e(url('/settings/permissions')) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <div class="permission-table-wrap">
            <table class="permission-table">
                <thead>
                    <tr>
                        <th scope="col" class="permission-table-name">Recht</th>
                        <th scope="col" class="permission-table-admin">Admin</th>
                        <?php foreach ($configurableRoles as $role): ?>
                            <th scope="col"><?= e($roleLabels[$role] ?? $role) ?></th>
                        <?php endforeach; ?>
                        <th scope="col" class="permission-table-default">Standard</th>
                    </tr>
                </thead>
                <?php foreach ($permissionGroups as $groupLabel => $groupPerms): ?>
                    <tbody>
                        <tr class="permission-table-group">
                            <th scope="colgroup" colspan="<?= 3 + count($configurableRoles) ?>">
                                <?= icon($permissionGroupIcons[$groupLabel] ?? 'sliders') ?><?= e($groupLabel) ?>
                            </th>
                        </tr>
                        <?php foreach ($groupPerms as $permission => $description): ?>
                            <?php
                            $defaultRoles = $defaults[$permission] ?? [];
                            $defaultLabels = array_map(static fn (string $r): string => $roleLabels[$r] ?? $r, $defaultRoles);
                            $defaultText = $defaultLabels !== [] ? implode(', ', $defaultLabels) : 'Nur Admin';
                            ?>
                            <tr>
                                <th scope="row" class="permission-table-name"><?= e($description) ?></th>
                                <td class="permission-table-admin" aria-label="Admin: immer erlaubt"><?= icon('check') ?></td>
                                <?php foreach ($configurableRoles as $role): ?>
                                    <td>
                                        <label class="permission-table-check">
                                            <input type="checkbox"
                                                   name="permissions[<?= e($permission) ?>][]"
                                                   value="<?= e($role) ?>"
                                                   aria-label="<?= e($description . ' – ' . ($roleLabels[$role] ?? $role)) ?>"
                                                <?= in_array($role, $matrix[$permission] ?? [], true) ? 'checked' : '' ?>>
                                        </label>
                                    </td>
                                <?php endforeach; ?>
                                <td class="permission-table-default muted"><?= e($defaultText) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="toolbar-actions top-gap">
            <button type="submit">Berechtigungen speichern</button>
        </div>
    </form>
</section>

<section class="panel">
    <div class="panel-head"><div><h3>Neue Rolle</h3><p class="muted">Direkt anlegen – landet sofort als neue Spalte oben in der Matrix.</p></div></div>
    <form method="post" action="<?= e(url('/settings/roles/store')) ?>" class="role-add">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="redirect_to" value="/settings/permissions">
        <input type="text" name="label" placeholder="Anzeigename, z. B. „Kassenwart“" required aria-label="Anzeigename der neuen Rolle">
        <input type="text" name="description" placeholder="Kurzbeschreibung (optional)" aria-label="Beschreibung der neuen Rolle">
        <button type="submit"><?= icon('plus') ?><span>Rolle anlegen</span></button>
    </form>
    <p class="field-hint">Feintuning (Schlüssel, Löschen) weiter unter <a href="<?= e(url('/settings/roles')) ?>">Rollen</a>.</p>
</section>

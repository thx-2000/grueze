<section class="hero-card compact-editor-shell">
    <div class="hero-row">
        <div>
            <p class="eyebrow">Benutzerverwaltung</p>
            <h2>Accounts für euer Team</h2>
            <p class="muted">Neue Accounts erhalten ein einmalig angezeigtes Erstpasswort. Für Kontakte ist der bequemere Weg jetzt direkt im Kontaktformular.</p>
        </div>
    </div>

    <details class="admin-drawer compact-editor-drawer">
        <summary>
            <span><?= icon('plus') ?></span>
            <span>Benutzer anlegen</span>
        </summary>
        <div class="admin-drawer-body">
            <form method="post" action="<?= e(url('/users/store')) ?>" class="form-grid compact-user-form">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <label><span>Name</span><input type="text" name="name" required></label>
                <label><span>E-Mail</span><input type="email" name="email" required></label>
                <label>
                    <span>Rolle</span>
                    <select name="role_id" required>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= e((string) $role['id']) ?>"><?= e($role['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="form-actions">
                    <button type="submit">Benutzer anlegen</button>
                </div>
            </form>
        </div>
    </details>
</section>

<section class="panel compact-editor-shell">
    <div class="panel-head">
        <div>
            <h3>Bestehende Accounts</h3>
            <p class="muted">Sperren, Passwort setzen, Reset-Mail auslösen und testweise in andere Rollen wechseln.</p>
        </div>
    </div>
    <div class="table-wrap">
        <table class="compact-users-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Verknüpfter Kontakt</th>
                    <th>E-Mail</th>
                    <th>Rolle</th>
                    <th>Status</th>
                    <th>Passkeys</th>
                    <th>Letzter Login</th>
                    <?php if ($canImpersonateUsers): ?><th class="users-action-col">Verwaltung</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <?php $passkeyCount = (int) ($passkeyCounts[(int) $user['id']] ?? 0); ?>
                    <tr id="user-<?= e((string) $user['id']) ?>">
                        <td><?= e($user['name']) ?></td>
                        <td><?= e(trim(($user['vorname'] ?? '') . ' ' . ($user['nachname'] ?? '')) ?: 'Keiner') ?></td>
                        <td><?= e($user['email']) ?></td>
                        <td><?= e($user['role_name']) ?></td>
                        <td><?= (int) $user['is_active'] === 1 ? 'Aktiv' : 'Inaktiv' ?></td>
                        <td><?= $passkeyCount > 0 ? e((string) $passkeyCount) : '–' ?></td>
                        <td><?= e($user['last_login_at'] ? format_datetime($user['last_login_at']) : 'Noch nie') ?></td>
                        <?php if ($canImpersonateUsers): ?>
                            <td class="users-action-col">
                                <div class="user-admin-actions">
                                    <?php if ((int) $user['id'] === $currentUserId): ?>
                                        <span class="muted">Aktuelle Sitzung</span>
                                    <?php elseif ((int) $user['id'] === $originalUserId): ?>
                                        <span class="muted">Steuerndes Admin-Konto</span>
                                    <?php else: ?>
                                        <form method="post" action="<?= e(url('/users/impersonate')) ?>">
                                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                            <input type="hidden" name="user_id" value="<?= e((string) $user['id']) ?>">
                                            <button type="submit" class="ghost-button compact-action"><?= icon('login') ?><span>Anmelden als</span></button>
                                        </form>
                                    <?php endif; ?>

                                    <form method="post" action="<?= e(url('/users/toggle-active')) ?>">
                                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="user_id" value="<?= e((string) $user['id']) ?>">
                                        <input type="hidden" name="set_active" value="<?= (int) $user['is_active'] === 1 ? '0' : '1' ?>">
                                        <button type="submit" class="<?= (int) $user['is_active'] === 1 ? 'danger-button compact-action' : 'ghost-button compact-action' ?>">
                                            <?= (int) $user['is_active'] === 1 ? icon('lock') : icon('unlock') ?>
                                            <span><?= (int) $user['is_active'] === 1 ? 'Sperren' : 'Entsperren' ?></span>
                                        </button>
                                    </form>

                                    <form method="post" action="<?= e(url('/users/send-reset')) ?>">
                                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="user_id" value="<?= e((string) $user['id']) ?>">
                                        <button type="submit" class="ghost-button compact-action"><?= icon('mail') ?><span>Reset-Mail</span></button>
                                    </form>

                                    <?php if (!empty($passkeysAvailable)): ?>
                                        <form method="post" action="<?= e(url('/users/passkeys/reset')) ?>" onsubmit="return confirm('Alle Passkeys dieses Benutzers wirklich entfernen?');">
                                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                            <input type="hidden" name="user_id" value="<?= e((string) $user['id']) ?>">
                                            <button type="submit" class="danger-button compact-action"<?= $passkeyCount === 0 ? ' disabled' : '' ?>><?= icon('passkey') ?><span>Passkeys löschen</span></button>
                                        </form>
                                    <?php endif; ?>

                                    <details class="admin-drawer compact-inside-drawer compact-user-password-drawer">
                                        <summary>
                                            <span><?= icon('key') ?></span>
                                            <span>Passwort setzen</span>
                                        </summary>
                                        <div class="admin-drawer-body">
                                            <form method="post" action="<?= e(url('/users/set-password')) ?>" class="stack">
                                                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                                <input type="hidden" name="user_id" value="<?= e((string) $user['id']) ?>">
                                                <label>
                                                    <span>Neues Passwort</span>
                                                    <input type="text" name="new_password" minlength="12" required>
                                                </label>
                                                <button type="submit" class="compact-action"><?= icon('key') ?><span>Speichern</span></button>
                                            </form>
                                        </div>
                                    </details>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

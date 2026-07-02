<section class="hero-card">
    <p class="eyebrow">Benutzerverwaltung</p>
    <h2>Accounts für euer Team</h2>
    <p class="muted">Neue Accounts erhalten ein einmalig angezeigtes Erstpasswort. Für Kontakte ist der bequemere Weg jetzt direkt im Kontaktformular.</p>

    <form method="post" action="<?= e(url('/users/store')) ?>" class="form-grid">
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
</section>

<section class="panel">
    <h3>Bestehende Accounts</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Verknüpfter Kontakt</th>
                    <th>E-Mail</th>
                    <th>Rolle</th>
                    <th>Status</th>
                    <th>Letzter Login</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= e($user['name']) ?></td>
                        <td><?= e(trim(($user['vorname'] ?? '') . ' ' . ($user['nachname'] ?? '')) ?: 'Keiner') ?></td>
                        <td><?= e($user['email']) ?></td>
                        <td><?= e($user['role_name']) ?></td>
                        <td><?= (int) $user['is_active'] === 1 ? 'Aktiv' : 'Inaktiv' ?></td>
                        <td><?= e($user['last_login_at'] ? format_datetime($user['last_login_at']) : 'Noch nie') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

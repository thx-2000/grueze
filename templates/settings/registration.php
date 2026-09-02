<header class="contacts-header">
    <div>
        <h1>Selbst-Registrierung</h1>
        <p class="muted">Wie Leute an einen Zugang kommen. Einladungslinks (von dir auf einem Kontakt erstellt) funktionieren immer.</p>
    </div>
</header>

<section class="detail-card">
    <h2>Einstellungen</h2>
    <form method="post" action="<?= e(url('/verwaltung/registrierung')) ?>" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <label class="toggle-row">
            <input type="checkbox" name="self_enabled" value="1" <?= $config['self_enabled'] ? 'checked' : '' ?>>
            <span>Selbst-Anmeldung erlauben – Personen können unter <code>/registrieren</code> ihre bekannte Mailadresse eintragen und bekommen einen Link (Bestätigung über den Klick).</span>
        </label>
        <div class="form-grid">
            <label>
                <span>Rolle für neue Zugänge</span>
                <select name="default_role">
                    <?php foreach ($roles as $role): ?>
                        <?php if ($role['name'] === 'admin') { continue; } ?>
                        <option value="<?= e($role['name']) ?>" <?= $config['default_role'] === $role['name'] ? 'selected' : '' ?>>
                            <?= e(($role['label'] ?? '') !== '' ? $role['label'] : $role['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Link gültig für (Stunden)</span>
                <input type="number" name="link_hours" min="1" max="720" value="<?= e((string) $config['link_hours']) ?>">
            </label>
        </div>
        <div class="form-actions">
            <button type="submit">Speichern</button>
        </div>
    </form>
</section>

<?php if (!empty($awaiting)): ?>
    <section class="detail-card">
        <h2>Freigabe-Warteschlange <span class="status-chip is-warn"><?= count($awaiting) ?></span></h2>
        <p class="muted">Anfragen von Adressen, die (noch) keinem Kontakt zugeordnet sind. Nach der Freigabe geht ein Link an die Adresse.</p>
        <ul class="completeness-list">
            <?php foreach ($awaiting as $req): ?>
                <li class="completeness-row">
                    <div class="completeness-person">
                        <strong><?= e($req['email']) ?></strong>
                        <span class="muted">angefragt <?= e(format_datetime($req['created_at'])) ?></span>
                        <?php if (trim((string) ($req['note'] ?? '')) !== ''): ?>
                            <span class="registration-note">„<?= e($req['note']) ?>"</span>
                        <?php endif; ?>
                    </div>
                    <div class="completeness-actions">
                        <form method="post" action="<?= e(url('/verwaltung/einladung/freigeben')) ?>">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="id" value="<?= e((string) $req['id']) ?>">
                            <button type="submit" class="button-link">Freigeben</button>
                        </form>
                        <form method="post" action="<?= e(url('/verwaltung/einladung/ablehnen')) ?>">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="id" value="<?= e((string) $req['id']) ?>">
                            <button type="submit" class="ghost-button">Ablehnen</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<section class="detail-card">
    <h2>Offene Einladungen</h2>
    <?php if ($openInvites === []): ?>
        <p class="field-hint">Zurzeit keine offenen Einladungen.</p>
    <?php else: ?>
        <ul class="completeness-list">
            <?php foreach ($openInvites as $inv): ?>
                <li class="completeness-row">
                    <div class="completeness-person">
                        <strong><?= e(trim(($inv['vorname'] ?? '') . ' ' . ($inv['nachname'] ?? '')) ?: $inv['email']) ?></strong>
                        <span class="muted"><?= e($inv['email']) ?> · bis <?= e(format_datetime($inv['expires_at'])) ?><?= $inv['creator_name'] !== null ? ' · von ' . e($inv['creator_name']) : ' · selbst angefordert' ?></span>
                    </div>
                    <form method="post" action="<?= e(url('/verwaltung/einladung/zuruecknehmen')) ?>">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="email" value="<?= e($inv['email']) ?>">
                        <button type="submit" class="ghost-button">Zurücknehmen</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="hero-card compact-editor-shell">
    <div class="hero-row">
        <div>
            <p class="eyebrow">Mein Konto</p>
            <h2><?= e((string) ($accountUser['name'] ?? 'Benutzerkonto')) ?></h2>
            <p class="muted">Hier verwaltest du deine eigenen Zugangsdaten. Dieser Bereich ist bewusst persönlich gehalten: Passwort, Passkeys und spätere Kontoeinstellungen liegen an einem festen Ort.</p>
        </div>
        <div class="floating-icon"><?= icon('user') ?></div>
    </div>
    <div class="subsection-card account-overview-card">
        <div>
            <strong>E-Mail-Adresse</strong>
            <p class="detail-hint"><?= e((string) ($accountUser['email'] ?? '')) ?></p>
        </div>
        <div>
            <strong>Rolle</strong>
            <p class="detail-hint role-chip-label"><?= e((string) ($accountUser['role_name'] ?? '')) ?></p>
        </div>
    </div>
    <div class="account-quicknav">
        <a class="account-quicknav-item" href="#password">
            <span class="account-quicknav-icon"><?= icon('key') ?></span>
            <span>
                <strong>Passwort</strong>
                <small>Eigenes Kennwort ändern</small>
            </span>
        </a>
        <a class="account-quicknav-item" href="#passkeys">
            <span class="account-quicknav-icon"><?= icon('passkey') ?></span>
            <span>
                <strong>Passkeys</strong>
                <small>Geräte verwalten</small>
            </span>
        </a>
        <a class="account-quicknav-item" href="<?= e(url('/orga-team')) ?>">
            <span class="account-quicknav-icon"><?= icon('mail') ?></span>
            <span>
                <strong>Orga-Team schreiben</strong>
                <small>Frage oder Hinweis ans Team</small>
            </span>
        </a>
    </div>
</section>

<?php if (!empty($openEvents)): ?>
    <section class="panel" id="abstimmungen">
        <div class="panel-head">
            <div>
                <h3>Offene Abstimmungen</h3>
                <p class="muted">Termine, bei denen deine Rückmeldung fehlt oder noch geändert werden kann.</p>
            </div>
        </div>
        <ul class="account-events">
            <?php foreach ($openEvents as $ev): ?>
                <li>
                    <a href="<?= e(url('/abstimmen?token=' . $ev['token'])) ?>">
                        <span class="account-events-title"><?= e($ev['title']) ?></span>
                        <span class="account-events-meta">
                            <?php if ((int) $ev['has_answered'] === 1): ?>
                                <span class="status-chip is-ok">geantwortet</span> – ändern
                            <?php else: ?>
                                <span class="status-chip is-warn">offen</span> – jetzt abstimmen
                            <?php endif; ?>
                        </span>
                    </a>
                    <?= icon('chevron-right') ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<section class="panel compact-editor-shell" id="password">
    <div class="panel-head">
        <div>
            <h3>Passwort</h3>
            <p class="muted">Für Änderungen am eigenen Passwort brauchst du zur Sicherheit zuerst dein aktuelles Kennwort.</p>
        </div>
    </div>
    <div class="subsection-card account-section-card">
        <form method="post" action="<?= e(url('/account/password')) ?>" class="form-grid account-settings-form">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <label>
                <span>Aktuelles Passwort</span>
                <input type="password" name="current_password" autocomplete="current-password" required>
            </label>
            <label>
                <span>Neues Passwort</span>
                <input type="password" name="new_password" minlength="12" autocomplete="new-password" required>
            </label>
            <label>
                <span>Neues Passwort wiederholen</span>
                <input type="password" name="new_password_repeat" minlength="12" autocomplete="new-password" required>
            </label>
            <div class="form-actions">
                <button type="submit"><?= icon('key') ?><span>Passwort aktualisieren</span></button>
            </div>
        </form>
    </div>
</section>

<section class="panel compact-editor-shell" id="passkeys">
    <div class="panel-head">
        <div>
            <h3>Passkeys</h3>
            <p class="muted">Passkeys sind die schnelle Anmeldung per Face ID, Touch ID, Windows Hello oder Sicherheitsschlüssel. Du kannst mehrere Geräte hinterlegen und später wieder entfernen.</p>
        </div>
    </div>

    <?php if (empty($passkeysAvailable)): ?>
        <div class="flash flash-error">Für Passkeys fehlt noch die neue Datenbank-Tabelle. Sobald die Migration eingespielt ist, kannst du sie hier verwalten.</div>
    <?php else: ?>
        <div class="subsection-card">
            <strong>Neuen Passkey hinzufügen</strong>
            <p class="detail-hint">Vergib optional einen Namen wie „MacBook“, „iPhone“ oder „YubiKey“, damit du ihn später leichter wiedererkennst.</p>
            <div class="passkey-register-card">
                <label>
                    <span>Bezeichnung</span>
                    <input id="passkeyLabel" type="text" placeholder="z. B. MacBook Air">
                </label>
                <button
                    type="button"
                    id="registerPasskeyButton"
                    data-passkey-register
                    data-options-url="<?= e(url('/passkeys/register/options')) ?>"
                    data-register-url="<?= e(url('/passkeys/register')) ?>"
                >
                    <?= icon('passkey') ?><span>Passkey hinzufügen</span>
                </button>
            </div>
        </div>

        <div class="subsection-card">
            <strong>Gespeicherte Passkeys</strong>
            <p class="detail-hint">Beim Entfernen eines Geräts ist danach wieder Passwort oder ein anderer Passkey nötig.</p>

            <?php if ($passkeys === []): ?>
                <p class="muted account-empty-state">Für dieses Konto ist noch kein Passkey gespeichert.</p>
            <?php else: ?>
                <div class="passkey-list">
                    <?php foreach ($passkeys as $passkey): ?>
                        <article class="passkey-item">
                            <div class="passkey-item-copy">
                                <strong><?= e((string) ($passkey['label'] ?: 'Unbenannter Passkey')) ?></strong>
                                <p class="muted">
                                    Angelegt: <?= e(format_datetime((string) $passkey['created_at'])) ?>
                                    <?php if (!empty($passkey['last_used_at'])): ?>
                                        <br>Zuletzt genutzt: <?= e(format_datetime((string) $passkey['last_used_at'])) ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <form method="post" action="<?= e(url('/passkeys/delete')) ?>" onsubmit="return confirm('Diesen Passkey wirklich entfernen?');">
                                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="passkey_id" value="<?= e((string) $passkey['id']) ?>">
                                <button type="submit" class="danger-button compact-action"><?= icon('trash') ?><span>Passkey entfernen</span></button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

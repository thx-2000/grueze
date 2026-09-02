<header class="page-head">
    <p class="eyebrow">Sicherheit</p>
    <h1>Passkeys</h1>
    <p class="muted">Passkeys ermöglichen eine schnelle Anmeldung per Face ID, Touch ID, Windows Hello oder Sicherheitsschlüssel, ohne dass du dein Passwort eintippen musst.</p>
</header>

<?php if (empty($passkeysAvailable)): ?>
    <section class="panel">
        <div class="flash flash-error">Für Passkeys fehlt noch die neue Datenbank-Tabelle. Sobald die Migration eingespielt ist, kannst du sie hier verwalten.</div>
    </section>
<?php else: ?>
    <section class="panel compact-editor-shell">
        <div class="panel-head">
            <div>
                <h3>Neuen Passkey hinzufügen</h3>
                <p class="muted">Vergib optional einen Namen wie „MacBook“, „iPhone“ oder „YubiKey“, damit du ihn später leichter wiedererkennst.</p>
            </div>
        </div>
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
    </section>

    <section class="panel compact-editor-shell">
        <div class="panel-head">
            <div>
                <h3>Gespeicherte Passkeys</h3>
                <p class="muted">Du kannst einzelne Geräte oder Schlüssel jederzeit wieder entfernen. Beim nächsten Login ist dann wieder Passwort oder ein anderer Passkey nötig.</p>
            </div>
        </div>

        <?php if ($passkeys === []): ?>
            <p class="muted">Für dieses Konto ist noch kein Passkey gespeichert.</p>
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
                        <form method="post" action="<?= e(url('/passkeys/delete')) ?>" data-confirm="Diesen Passkey wirklich entfernen?">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="passkey_id" value="<?= e((string) $passkey['id']) ?>">
                            <button type="submit" class="danger-button compact-action"><?= icon('trash') ?><span>Passkey entfernen</span></button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

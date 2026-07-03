<section class="hero-card narrow">
    <div class="hero-row">
        <div>
            <p class="eyebrow">Anmeldung</p>
            <h2>Adress-Backend für das Orga-Team</h2>
            <p class="muted">Hier pflegt ihr Kontakte, Mailings und interne Organisationsdaten an einem Ort.</p>
        </div>
        <div class="floating-icon"><?= icon('mail-open') ?></div>
    </div>

    <div class="subsection-card">
        <strong>Öffentliche Seite</strong>
        <p class="detail-hint">Infos zum Treffen und die öffentliche Startseite findet ihr unter <a href="https://example.org" target="_blank" rel="noopener noreferrer">example.org</a>.</p>
    </div>

    <form method="post" action="<?= e(url('/login')) ?>" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <label>
            <span>E-Mail-Adresse</span>
            <input type="email" name="email" required autocomplete="email">
        </label>
        <label>
            <span>Passwort</span>
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit"><?= icon('login') ?><span>Anmelden</span></button>
    </form>

    <?php if (!empty($passkeysAvailable)): ?>
        <div class="subsection-card passkey-login-box">
            <strong>Schnell anmelden</strong>
            <p class="detail-hint">Wenn für dein Konto bereits ein Passkey hinterlegt ist, kannst du dich damit direkt per Gerätefreigabe anmelden.</p>
            <button
                type="button"
                class="ghost-button"
                data-passkey-login
                data-options-url="<?= e(url('/passkeys/auth/options')) ?>"
                data-auth-url="<?= e(url('/passkeys/authenticate')) ?>"
            >
                <?= icon('passkey') ?><span>Mit Passkey anmelden</span>
            </button>
        </div>
    <?php endif; ?>

    <div class="link-row">
        <p><a href="<?= e(url('/forgot-password')) ?>">Passwort vergessen?</a></p>
        <p><a href="https://example.org" target="_blank" rel="noopener noreferrer">Zur Website</a></p>
    </div>
    <?php if (empty($adminExists)): ?>
        <p><a href="<?= e(url('/setup/admin')) ?>">Ersten Admin anlegen</a></p>
    <?php endif; ?>
</section>

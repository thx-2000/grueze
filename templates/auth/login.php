<section class="hero-card narrow">
    <div class="hero-row">
        <div>
            <p class="eyebrow">Anmeldung</p>
            <h2>Willkommen zurück</h2>
            <p class="muted">Schlank, sicher und bereit für eure Jahrgangsorganisation.</p>
        </div>
        <div class="floating-icon"><?= icon('mail-open') ?></div>
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

    <div class="link-row">
        <p><a href="<?= e(url('/forgot-password')) ?>">Passwort vergessen?</a></p>
    </div>
    <?php if (empty($adminExists)): ?>
        <p><a href="<?= e(url('/setup/admin')) ?>">Ersten Admin anlegen</a></p>
    <?php endif; ?>
</section>

<section class="hero-card narrow">
    <p class="eyebrow">Passwort zurücksetzen</p>
    <h2>Reset-Link anfordern</h2>
    <form method="post" action="<?= e(url('/forgot-password')) ?>" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <label>
            <span>E-Mail-Adresse</span>
            <input type="email" name="email" required autocomplete="email">
        </label>
        <button type="submit">Reset-Link senden</button>
    </form>
    <p><a href="<?= e(url('/login')) ?>">Zurück zur Anmeldung</a></p>
</section>


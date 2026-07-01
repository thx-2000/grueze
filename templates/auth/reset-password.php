<section class="hero-card narrow">
    <p class="eyebrow">Neues Passwort</p>
    <h2>Passwort festlegen</h2>
    <form method="post" action="<?= e(url('/reset-password')) ?>" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="email" value="<?= e($email ?? '') ?>">
        <input type="hidden" name="token" value="<?= e($token ?? '') ?>">
        <label>
            <span>Neues Passwort</span>
            <input type="password" name="password" minlength="12" required autocomplete="new-password">
        </label>
        <button type="submit">Passwort speichern</button>
    </form>
</section>


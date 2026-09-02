<header class="page-head">
    <p class="eyebrow">Passwort zurücksetzen</p>
    <h1>Reset-Link anfordern</h1>
    <p class="muted">Wir schicken dir einen Link, mit dem du ein neues Passwort setzen kannst.</p>
</header>

<section class="panel stack auth-card">
    <form method="post" action="<?= e(url('/forgot-password')) ?>" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <label>
            <span>E-Mail-Adresse</span>
            <input type="email" name="email" required autocomplete="email">
        </label>
        <button type="submit">Reset-Link senden</button>
    </form>
    <div class="link-row">
        <a href="<?= e(url('/login')) ?>">Zurück zur Anmeldung</a>
    </div>
</section>

<header class="page-head">
    <p class="eyebrow">Neues Passwort</p>
    <h1>Passwort festlegen</h1>
    <p class="muted">Mindestens 12 Zeichen. Danach kannst du dich direkt anmelden.</p>
</header>

<section class="panel stack auth-card">
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
    <div class="link-row">
        <a href="<?= e(url('/login')) ?>">Zurück zur Anmeldung</a>
    </div>
</section>

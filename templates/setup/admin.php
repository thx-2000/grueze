<header class="page-head">
    <p class="eyebrow">Erstkonfiguration</p>
    <h1>Ersten Admin anlegen</h1>
    <p class="muted">Diese Seite ist nur nutzbar, solange noch kein Admin-Konto existiert.</p>
</header>

<section class="panel stack auth-card">
    <form method="post" action="<?= e(url('/setup/admin')) ?>" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <label>
            <span>Name</span>
            <input type="text" name="name" value="<?= e(old('name', '')) ?>" required>
        </label>
        <label>
            <span>E-Mail-Adresse</span>
            <input type="email" name="email" value="<?= e(old('email', '')) ?>" required>
        </label>
        <label>
            <span>Passwort</span>
            <input type="password" name="password" minlength="12" required autocomplete="new-password">
        </label>
        <button type="submit">Admin-Konto anlegen</button>
    </form>
</section>

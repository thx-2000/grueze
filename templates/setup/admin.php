<section class="hero-card narrow">
    <p class="eyebrow">Erstkonfiguration</p>
    <h2>Ersten Admin anlegen</h2>
    <p class="muted">Diese Seite ist nur nutzbar, solange noch kein Admin-Konto existiert.</p>

    <form method="post" action="<?= e(url('/setup/admin')) ?>" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <label>
            <span>Name</span>
            <input type="text" name="name" value="<?= e(old('name', 'Thomas')) ?>" required>
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

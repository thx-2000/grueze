<section class="hero-card narrow">
    <p class="eyebrow">Anmeldung</p>
    <h2>Willkommen zurück</h2>
    <p class="muted">Für Admins, Orga-Team und weitere Rollen vorbereitet.</p>

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
        <button type="submit">Anmelden</button>
    </form>

    <p><a href="<?= e(url('/forgot-password')) ?>">Passwort vergessen?</a></p>
</section>


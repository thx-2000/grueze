<section class="hero-card narrow">
    <p class="eyebrow">Zugang einrichten</p>
    <h2>Willkommen!</h2>
    <p class="muted">Für <strong><?= e($email) ?></strong>. Bestätige deinen Namen und leg ein Kennwort fest – dann bist du direkt drin. Einen Passkey (Face&nbsp;ID, Fingerabdruck …) kannst du danach unter „Mein Konto" hinzufügen.</p>

    <form method="post" action="<?= e(url('/registrieren')) ?>" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label>
            <span>Dein Name</span>
            <input type="text" name="name" value="<?= e($suggestedName) ?>" required autocomplete="name">
        </label>
        <label>
            <span>Kennwort (mindestens 12 Zeichen)</span>
            <input type="password" name="password" minlength="12" required autocomplete="new-password">
        </label>
        <label>
            <span>Kennwort wiederholen</span>
            <input type="password" name="password_repeat" minlength="12" required autocomplete="new-password">
        </label>
        <button type="submit">Zugang einrichten</button>
    </form>
</section>

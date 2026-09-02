<section class="hero-card narrow">
    <p class="eyebrow">Zugang einrichten</p>
    <h2>Noch keinen Zugang?</h2>

    <?php if ($selfEnabled): ?>
        <p class="muted">Trag die Mailadresse ein, die bei uns hinterlegt ist. Passt sie zu einem Kontakt, schicken wir dir einen Link, über den du dir den Zugang einrichten kannst.</p>
        <form method="post" action="<?= e(url('/registrieren')) ?>" class="stack">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <label>
                <span>Deine Mailadresse</span>
                <input type="email" name="email" required autocomplete="email">
            </label>
            <button type="submit">Link anfordern</button>
        </form>
    <?php else: ?>
        <p class="muted">Zugänge werden zurzeit nur über eine persönliche Einladung vergeben. Wende dich ans Orga-Team – sie schicken dir einen Link.</p>
    <?php endif; ?>

    <p class="field-hint"><a href="<?= e(url('/login')) ?>">Zurück zur Anmeldung</a></p>
</section>

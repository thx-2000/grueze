<header class="page-head">
    <p class="eyebrow">Zugang einrichten</p>
    <h1>Noch keinen Zugang?</h1>
</header>

<section class="panel stack auth-card">
    <?php if ($selfEnabled): ?>
        <p class="muted">Trag die Mailadresse ein, die bei uns hinterlegt ist. Passt sie zu einem Kontakt, schicken wir dir einen Link, über den du dir den Zugang einrichten kannst.</p>
        <form method="post" action="<?= e(url('/registrieren')) ?>" class="stack">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <label>
                <span>Deine Mailadresse</span>
                <input type="email" name="email" required autocomplete="email">
            </label>
            <label>
                <span>Kurz zu dir (optional)</span>
                <textarea name="note" rows="2" placeholder="Falls deine Adresse noch nicht hinterlegt ist – wer bist du?"></textarea>
            </label>
            <button type="submit">Link anfordern</button>
        </form>
        <p class="field-hint">Ist deine Adresse bei uns bekannt, kommt der Link sofort. Sonst prüft das Orga-Team deine Anfrage kurz.</p>
    <?php else: ?>
        <p class="muted">Zugänge werden zurzeit nur über eine persönliche Einladung vergeben. Wende dich ans Orga-Team – sie schicken dir einen Link.</p>
    <?php endif; ?>

    <div class="link-row">
        <a href="<?= e(url('/login')) ?>">Zurück zur Anmeldung</a>
    </div>
</section>

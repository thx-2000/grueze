<p class="detail-backlink"><a href="<?= e(url('/account')) ?>"><?= icon('chevron-right') ?>Zurück zu „Mein Konto"</a></p>

<header class="contact-detail-head">
    <p class="eyebrow">Orga-Team</p>
    <h1>Nachricht ans Orga-Team</h1>
    <p class="muted">Kurzer Draht ans Organisations-Team – z. B. falsche Daten, eine Frage oder ein Hinweis. <?= e($targetDescription) ?> Antworten gehen direkt an deine Login-Mailadresse.</p>
</header>

<form method="post" action="<?= e(url('/orga-team')) ?>" class="contact-detail-form">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <section class="detail-card">
        <div class="form-grid">
            <label class="full-width"><span>Betreff <span class="required-marker" aria-hidden="true">*</span></span><input type="text" name="subject" value="<?= e(old('subject')) ?>" required></label>
            <label class="full-width"><span>Nachricht <span class="required-marker" aria-hidden="true">*</span></span><textarea name="message" rows="8" required><?= e(old('message')) ?></textarea></label>
        </div>
    </section>
    <div class="detail-save-bar" data-save-bar>
        <span class="detail-save-hint">Ans Orga-Team senden.</span>
        <div class="detail-save-actions">
            <a class="ghost-button" href="<?= e(url('/account')) ?>">Abbrechen</a>
            <button type="submit"><?= icon('mail') ?><span>Absenden</span></button>
        </div>
    </div>
</form>

<section class="hero-card narrow">
    <div>
        <p class="eyebrow">Einstellungen</p>
        <h2>Automatischen Mail-Fuß festlegen</h2>
        <p class="muted">Dieser Text wird bei Testmails und beim Serienversand automatisch unter deine eigentliche Nachricht gesetzt.</p>
    </div>
</section>

<section class="panel narrow">
    <form method="post" action="<?= e(url('/settings/mail-footer')) ?>" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <label>
            <span>Mail-Fuß</span>
            <textarea name="mail_footer" rows="10" required><?= e($mailFooter) ?></textarea>
            <small class="field-hint">Im Mail-Kompositionsfenster wird dieser Abschnitt als Vorschau angezeigt, aber getrennt von der eigentlichen Nachricht gepflegt.</small>
        </label>

        <div class="subsection-card">
            <strong>Aktuelle Vorschau</strong>
            <div class="mail-footer-preview"><?= e($mailFooter) ?></div>
        </div>

        <div class="subsection-card">
            <strong>Standardtext</strong>
            <div class="mail-footer-preview"><?= e($defaultMailFooter) ?></div>
        </div>

        <div class="form-actions">
            <button type="submit">Speichern</button>
            <button type="submit" class="ghost-button" name="use_default" value="1">Standardtext einsetzen</button>
            <a class="ghost-button" href="<?= e(url('/')) ?>">Zurück</a>
        </div>
    </form>
</section>

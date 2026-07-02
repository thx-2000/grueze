<section class="hero-card narrow">
    <div>
        <p class="eyebrow">Einstellungen</p>
        <h2>Mail-Einstellungen</h2>
        <p class="muted">Hier pflegst du den automatischen Mail-Fuß und die auswählbaren Betreff-Präfixe für den Versand.</p>
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

        <label>
            <span>Betreff-Präfixe</span>
            <textarea name="subject_prefixes" rows="4" required><?= e($subjectPrefixes) ?></textarea>
            <small class="field-hint">Ein Präfix pro Zeile. Die erste Zeile ist der Standard, zum Beispiel <code>[Verteiler]</code>.</small>
        </label>

        <div class="subsection-card">
            <strong>Aktuelle Vorschau</strong>
            <div class="mail-footer-preview"><?= e($mailFooter) ?></div>
        </div>

        <div class="subsection-card">
            <strong>Aktueller Standard-Präfix</strong>
            <div class="mail-footer-preview"><?= e($defaultSubjectPrefix) ?> Beispielbetreff</div>
        </div>

        <div class="subsection-card">
            <strong>Standardtext</strong>
            <div class="mail-footer-preview"><?= e($defaultMailFooter) ?></div>
        </div>

        <div class="form-actions">
            <button type="submit">Speichern</button>
            <button type="submit" class="ghost-button" name="use_default" value="1">Standardwerte einsetzen</button>
            <a class="ghost-button" href="<?= e(url('/')) ?>">Zurück</a>
        </div>
    </form>
</section>

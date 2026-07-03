<section class="hero-card">
    <div class="hero-row">
        <div>
            <p class="eyebrow">Phase 1</p>
            <h2>Design & Branding</h2>
            <p class="muted">Hier entsteht die erste White-Label-Schicht. Name, öffentliche Links, Basisfarben, Fonts und Logo kommen von nun an aus einer zentralen Admin-Konfiguration statt aus fest eingebauten Projekttexten.</p>
        </div>
        <div class="floating-icon"><?= icon('sliders') ?></div>
    </div>
</section>

<section class="panel compact-editor-shell">
    <form method="post" action="<?= e(url('/settings/branding')) ?>" enctype="multipart/form-data" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <div class="subsection-card">
            <strong>Identität & Texte</strong>
            <div class="form-grid">
                <label>
                    <span>Anwendungsname</span>
                    <input type="text" name="branding_app_name" value="<?= e((string) ($branding['branding_app_name'] ?? '')) ?>" required>
                </label>
                <label>
                    <span>Kurzname / Logo-Text</span>
                    <input type="text" name="branding_short_name" value="<?= e((string) ($branding['branding_short_name'] ?? '')) ?>" required>
                </label>
                <label>
                    <span>Versionsnummer</span>
                    <input type="text" name="branding_version" value="<?= e((string) ($branding['branding_version'] ?? '0.2.0')) ?>">
                    <small class="field-hint">Zum Beispiel <code>0.2.0</code> oder <code>0.2.1</code>. Wird dezent im Fußbereich eingeblendet.</small>
                </label>
                <label>
                    <span>Öffentliche Seitenbezeichnung</span>
                    <input type="text" name="branding_public_site_label" value="<?= e((string) ($branding['branding_public_site_label'] ?? '')) ?>">
                </label>
                <label>
                    <span>Öffentliche Seiten-URL</span>
                    <input type="url" name="branding_public_site_url" value="<?= e((string) ($branding['branding_public_site_url'] ?? '')) ?>">
                </label>
                <label class="full-width">
                    <span>Login-Einleitung</span>
                    <textarea name="branding_login_intro" rows="3"><?= e((string) ($branding['branding_login_intro'] ?? '')) ?></textarea>
                </label>
                <label class="full-width">
                    <span>Hinweis auf die öffentliche Seite</span>
                    <textarea name="branding_login_public_hint" rows="2"><?= e((string) ($branding['branding_login_public_hint'] ?? '')) ?></textarea>
                </label>
                <label class="full-width">
                    <span>Seitentext in der linken Spalte</span>
                    <textarea name="branding_sidebar_copy" rows="2"><?= e((string) ($branding['branding_sidebar_copy'] ?? '')) ?></textarea>
                </label>
                <label>
                    <span>Support-/Kontakt-Mail</span>
                    <input type="email" name="branding_support_email" value="<?= e((string) ($branding['branding_support_email'] ?? '')) ?>">
                </label>
            </div>
        </div>

        <div class="subsection-card">
            <strong>Logo</strong>
            <div class="branding-logo-grid">
                <label>
                    <span>Logo hochladen</span>
                    <input type="file" name="branding_logo" accept=".png,.jpg,.jpeg,.webp,.svg">
                    <small class="field-hint">Empfohlen: transparentes PNG oder SVG. Das Logo wird für die Anwendungsschale vorbereitet.</small>
                </label>
                <div class="branding-logo-preview">
                    <span class="branding-logo-preview-title">Aktuelles Logo</span>
                    <?php if (!empty($branding['branding_logo_path'])): ?>
                        <img src="<?= e(asset_url('/' . ltrim((string) $branding['branding_logo_path'], '/'))) ?>" alt="Aktuelles Logo">
                    <?php else: ?>
                        <div class="branding-logo-fallback"><?= e((string) ($branding['branding_short_name'] ?? 'Logo')) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="subsection-card">
            <strong>Typografie</strong>
            <div class="form-grid">
                <label>
                    <span>Display-Font</span>
                    <input type="text" name="branding_font_display" value="<?= e((string) ($branding['branding_font_display'] ?? '')) ?>">
                </label>
                <label>
                    <span>Text-Font</span>
                    <input type="text" name="branding_font_body" value="<?= e((string) ($branding['branding_font_body'] ?? '')) ?>">
                </label>
            </div>
        </div>

        <div class="subsection-card">
            <strong>Farben</strong>
            <div class="form-grid branding-color-grid">
                <?php
                $colorFields = [
                    'branding_color_bg' => 'Hintergrund',
                    'branding_color_bg_alt' => 'Hintergrund alt',
                    'branding_color_surface' => 'Fläche',
                    'branding_color_surface_strong' => 'Fläche stark',
                    'branding_color_surface_soft' => 'Fläche weich',
                    'branding_color_text' => 'Text',
                    'branding_color_muted' => 'Sekundärtext',
                    'branding_color_primary' => 'Primär',
                    'branding_color_primary_strong' => 'Primär stark',
                    'branding_color_secondary' => 'Sekundär',
                    'branding_color_accent' => 'Signal',
                    'branding_color_highlight' => 'Highlight',
                    'branding_color_border' => 'Rahmen',
                    'branding_color_danger' => 'Warnung',
                    'branding_color_success' => 'Erfolg',
                ];
                ?>
                <?php foreach ($colorFields as $field => $label): ?>
                    <label>
                        <span><?= e($label) ?></span>
                        <div class="color-input-row">
                            <span class="color-preview-swatch" data-color-preview style="--swatch: <?= e((string) ($branding[$field] ?? 'transparent')) ?>;"></span>
                            <input type="text" name="<?= e($field) ?>" value="<?= e((string) ($branding[$field] ?? '')) ?>" data-color-source>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit">Branding speichern</button>
            <a class="ghost-button" href="<?= e(url('/settings/mail-footer')) ?>">Zu den Mail-Einstellungen</a>
            <a class="ghost-button" href="<?= e(url('/')) ?>">Zurück</a>
        </div>
    </form>
</section>

<script>
    document.querySelectorAll('[data-color-source]').forEach((input) => {
        const wrapper = input.closest('.color-input-row');
        const swatch = wrapper ? wrapper.querySelector('[data-color-preview]') : null;
        if (!swatch) {
            return;
        }

        const sync = () => {
            swatch.style.setProperty('--swatch', input.value.trim() || 'transparent');
        };

        input.addEventListener('input', sync);
        sync();
    });
</script>

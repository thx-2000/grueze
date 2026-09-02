<header class="page-head">
    <p class="eyebrow">Verwaltung</p>
    <h1>Branding</h1>
    <p class="muted">Name, Kurzname, öffentliche Links, Login-Texte, Support-Adresse und Logo. Das Aussehen – Farben, Schriften, Ecken – steckt in den <a href="<?= e(url('/settings/themes')) ?>">Themes</a>.</p>
</header>

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
                    <span>Login-Überschrift</span>
                    <input type="text" name="branding_login_headline" value="<?= e((string) ($branding['branding_login_headline'] ?? '')) ?>">
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
            <strong>Farben, Schriften &amp; Ecken</strong>
            <p class="detail-hint">Das Aussehen steckt jetzt im Theme-System. Dort lässt sich das aktive Theme wechseln, duplizieren und anpassen.</p>
            <div class="toolbar-actions">
                <a class="ghost-button compact-action" href="<?= e(url('/settings/themes')) ?>"><?= icon('sparkles') ?><span>Zu den Themes</span></a>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit">Branding speichern</button>
            <a class="ghost-button" href="<?= e(url('/settings/mail-footer')) ?>">Zu den Mail-Einstellungen</a>
            <a class="ghost-button" href="<?= e(url('/verwaltung')) ?>">Zurück</a>
        </div>
    </form>
</section>

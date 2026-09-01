<?php
$slug = (string) ($theme['slug'] ?? '');
$tokens = $theme['tokens'] ?? [];

$groups = [];
foreach ($tokenDefs as $key => $def) {
    $groups[$def['group']][] = $def;
}

/** Tokenwert oder – wenn leer – der Theme-Standard. */
$effective = static function (string $key) use ($tokens, $defaults): string {
    $value = trim((string) ($tokens[$key] ?? ''));

    return $value !== '' ? $value : (string) ($defaults[$key] ?? '');
};

/** Farbwert als #rrggbb für den nativen Farbwähler (Fallback: mittleres Grau). */
$asHex = static function (string $value): string {
    $rgb = css_color_to_rgb($value);
    if ($rgb === null) {
        return '#808080';
    }

    return sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
};

// Inline-Style der Vorschau vorab setzen, damit sie schon vor dem JS stimmt.
$previewStyle = '';
foreach ($tokenDefs as $key => $def) {
    $previewStyle .= $def['css'] . ':' . $effective($key) . ';';
}
foreach (['color_primary' => '--color-on-primary', 'color_danger' => '--color-on-danger', 'color_accent' => '--color-on-accent'] as $src => $var) {
    $previewStyle .= $var . ':' . readable_ink($effective($src)) . ';';
}
?>
<section class="hero-card narrow">
    <div>
        <p class="eyebrow">Theme bearbeiten</p>
        <h2><?= e($theme['name']) ?></h2>
        <p class="muted">
            <?= $theme['based_on'] !== '' ? 'Basiert auf „' . e($theme['based_on']) . '". ' : '' ?>
            Ein leeres Feld lässt den Token unverändert; der Platzhalter zeigt den
            Standardwert. Farbwerte als Hex (<code>#ff8800</code>) oder CSS
            (<code>rgba(…)</code>).
        </p>
    </div>
</section>

<section class="panel">
    <form method="post" action="<?= e(url('/settings/themes/umbenennen')) ?>" class="inline-form">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="slug" value="<?= e($slug) ?>">
        <input type="text" name="name" value="<?= e($theme['name']) ?>" required aria-label="Theme-Name">
        <button type="submit">Umbenennen</button>
    </form>
</section>

<div class="theme-editor-layout" id="themeEditor">
    <form method="post" action="<?= e(url('/settings/themes/speichern')) ?>" class="theme-editor-fields stack">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="slug" value="<?= e($slug) ?>">

        <?php foreach ($groups as $groupName => $defs): ?>
            <div class="subsection-card">
                <strong><?= e($groupName) ?></strong>
                <div class="token-grid">
                    <?php foreach ($defs as $def): ?>
                        <?php
                        $key = $def['key'];
                        $val = (string) ($tokens[$key] ?? '');
                        $isColor = $def['type'] === 'color';
                        ?>
                        <div class="token-field">
                            <label for="tf_<?= e($key) ?>"><?= e($def['label']) ?></label>
                            <div class="token-input-row">
                                <?php if ($isColor): ?>
                                    <input type="color" class="token-swatch" data-color-picker="<?= e($key) ?>"
                                           value="<?= e($asHex($effective($key))) ?>"
                                           aria-label="Farbwähler <?= e($def['label']) ?>" tabindex="-1">
                                <?php endif; ?>
                                <input type="text" id="tf_<?= e($key) ?>" name="token_<?= e($key) ?>"
                                       value="<?= e($val) ?>" placeholder="<?= e((string) ($defaults[$key] ?? '')) ?>"
                                       data-token="<?= e($key) ?>" data-cssvar="<?= e($def['css']) ?>"
                                       autocomplete="off" spellcheck="false">
                            </div>
                            <?php if ($isColor): ?>
                                <p class="contrast-note" data-contrast hidden></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="form-actions">
            <button type="submit">Theme speichern</button>
            <a class="ghost-button" href="<?= e(url('/settings/themes')) ?>">Zurück zur Übersicht</a>
        </div>
    </form>

    <aside class="theme-preview-shell">
        <div class="theme-preview" id="themePreview" style="<?= e($previewStyle) ?>">
            <div class="theme-preview-bar">
                <strong><?= e($theme['name']) ?></strong>
                <span class="theme-preview-chip">Kopfleiste</span>
            </div>
            <div class="theme-preview-card">
                <h3>Überschrift</h3>
                <p>Fließtext auf der Kartenfläche. <a href="#" onclick="return false;">Ein Link</a> zeigt die
                    Linkfarbe. <span class="theme-preview-muted">Gedämpfter Hinweis daneben.</span></p>
                <div class="theme-preview-actions">
                    <button type="button" onclick="return false;">Primär</button>
                    <button type="button" class="ghost-button" onclick="return false;">Sekundär</button>
                    <button type="button" class="danger-button" onclick="return false;">Löschen</button>
                </div>
                <input type="text" value="Eingabefeld" readonly aria-label="Vorschau-Eingabefeld">
                <div class="theme-preview-badges">
                    <span class="theme-preview-badge accent">Akzent</span>
                    <span class="theme-preview-badge success">Erfolg</span>
                    <span class="theme-preview-badge danger">Warnung</span>
                </div>
                <table class="theme-preview-table">
                    <thead><tr><th>Name</th><th>Ort</th></tr></thead>
                    <tbody>
                        <tr><td>Zeile eins</td><td>Berlin</td></tr>
                        <tr><td>Zeile zwei</td><td>Hamburg</td></tr>
                        <tr class="is-hover"><td>Zeile (Hover)</td><td>Köln</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="muted detail-hint">Live-Vorschau. Übernommen wird erst mit „Theme speichern".</p>
    </aside>
</div>

<script src="<?= e(asset_url('/assets/js/theme-editor.js')) ?>" defer></script>

<header class="page-head page-head--split">
    <div>
        <p class="eyebrow">Rechtliches &rsaquo; <?= e($pageLabel) ?></p>
        <h1><?= e($pageLabel) ?> bearbeiten</h1>
        <p class="muted">Der Inhalt wird als HTML gespeichert und direkt auf der &ouml;ffentlichen Seite angezeigt. Erlaubt sind nur einfache Textauszeichnungen &ndash; Skripte und andere aktive Inhalte werden beim Speichern entfernt.</p>
    </div>
    <a href="<?= e(url('/' . ($page === 'impressum' ? 'impressum' : 'datenschutz'))) ?>" class="ghost-button">Vorschau</a>
</header>

<section class="panel narrow">
    <form method="post" action="<?= e(url('/admin/legal/' . $page)) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <div class="form-group">
            <label for="content">HTML-Inhalt</label>
            <textarea id="content" name="content" rows="30" class="legal-editor" spellcheck="false"><?= htmlspecialchars($content, ENT_QUOTES, 'UTF-8') ?></textarea>
            <p class="field-hint">Standard-HTML wie &lt;h3&gt;, &lt;h4&gt;, &lt;p&gt;, &lt;ul&gt;, &lt;a&gt; usw. ist erlaubt.</p>
        </div>
        <div class="form-actions">
            <button type="submit">Speichern</button>
            <a href="<?= e(url('/' . ($page === 'impressum' ? 'impressum' : 'datenschutz'))) ?>" class="ghost-button">Abbrechen</a>
        </div>
    </form>
</section>

<section class="panel narrow">
    <h3 class="section-title">Standardinhalt wiederherstellen</h3>
    <p class="muted">Setzt den Inhalt auf den vordefinierten Standardtext zur&uuml;ck. Eigene &Auml;nderungen gehen dabei verloren.</p>
    <form method="post" action="<?= e(url('/admin/legal/' . $page)) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="_action" value="reset">
        <button type="submit" class="ghost-button" data-confirm="Eigene Änderungen verwerfen und Standardinhalt wiederherstellen?">Standard wiederherstellen</button>
    </form>
</section>

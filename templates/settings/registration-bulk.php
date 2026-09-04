<?php
/**
 * @var list<array<string,mixed>> $categories
 * @var list<array<string,mixed>> $tags
 * @var list<array<string,mixed>> $groups
 * @var array<string,mixed> $config
 */
?>
<header class="contacts-header">
    <div>
        <p class="eyebrow"><a href="<?= e(url('/verwaltung/registrierung')) ?>">Selbst-Registrierung</a></p>
        <h1>Sammel-Einladung</h1>
        <p class="muted">Mehrere Personen auf einmal einladen. Übersprungen werden Kontakte ohne Mailadresse und solche, die schon einen Zugang oder eine offene Einladung haben – die Auswahl siehst du danach vor dem Verschicken.</p>
    </div>
</header>

<section class="panel">
    <form method="post" action="<?= e(url('/verwaltung/einladungen/vorschau')) ?>" class="stack" data-bulk-invite-form>
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <fieldset class="stack" style="border:0;padding:0;margin:0">
            <legend class="visually-hidden">Wer soll eingeladen werden?</legend>

            <label class="inline-toggle">
                <input type="radio" name="mode" value="without_account" checked data-mode-radio>
                <span><strong>Alle ohne Zugang</strong> – jeder Kontakt mit Mailadresse, der noch keinen Login hat</span>
            </label>

            <label class="inline-toggle">
                <input type="radio" name="mode" value="category" data-mode-radio>
                <span><strong>Nach Kategorie</strong></span>
            </label>
            <div class="bulk-invite-sub" data-mode-sub="category" hidden>
                <select name="category_id">
                    <option value="">— Kategorie wählen —</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e((string) $category['id']) ?>"><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <label class="inline-toggle">
                <input type="radio" name="mode" value="tags" data-mode-radio>
                <span><strong>Nach Tag(s)</strong></span>
            </label>
            <div class="bulk-invite-sub tag-picker compact-picker" data-mode-sub="tags" hidden>
                <?php foreach ($tags as $tag): ?>
                    <label class="tag-option">
                        <input type="checkbox" name="tag_ids[]" value="<?= e((string) $tag['id']) ?>">
                        <span><?= e($tag['name']) ?></span>
                    </label>
                <?php endforeach; ?>
                <?php if ($tags === []): ?><p class="field-hint">Noch keine Tags angelegt.</p><?php endif; ?>
            </div>

            <label class="inline-toggle">
                <input type="radio" name="mode" value="groups" data-mode-radio>
                <span><strong>Nach Gruppe(n)</strong></span>
            </label>
            <div class="bulk-invite-sub tag-picker compact-picker" data-mode-sub="groups" hidden>
                <?php foreach ($groups as $group): ?>
                    <label class="tag-option">
                        <input type="checkbox" name="group_ids[]" value="<?= e((string) $group['id']) ?>">
                        <span><?= e($group['name']) ?></span>
                    </label>
                <?php endforeach; ?>
                <?php if ($groups === []): ?><p class="field-hint">Noch keine Gruppen angelegt.</p><?php endif; ?>
            </div>
        </fieldset>

        <p class="field-hint">Rolle für neue Zugänge: <strong><?= e(role_label((string) $config['default_role'])) ?></strong> (<a href="<?= e(url('/verwaltung/registrierung')) ?>">einstellbar</a>). Link gültig <?= (int) $config['link_hours'] ?> Stunden.</p>

        <div class="form-actions">
            <button type="submit" class="button-link"><?= icon('mail') ?><span>Weiter zur Vorschau</span></button>
        </div>
    </form>
</section>

<p class="detail-hint">Bestimmte Personen von Hand auswählen? Im <a href="<?= e(url('/kontakte')) ?>">Adressbuch</a> „Auswählen" nutzen, dann „Einladungen für Auswahl".</p>

<script nonce="<?= e(csp_nonce()) ?>">
(function () {
    var form = document.querySelector('[data-bulk-invite-form]');
    if (!form) return;
    var radios = form.querySelectorAll('[data-mode-radio]');
    function sync() {
        var mode = form.querySelector('[data-mode-radio]:checked').value;
        form.querySelectorAll('[data-mode-sub]').forEach(function (el) {
            el.hidden = el.getAttribute('data-mode-sub') !== mode;
        });
    }
    radios.forEach(function (r) { r.addEventListener('change', sync); });
    sync();
})();
</script>

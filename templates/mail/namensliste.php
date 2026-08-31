<section class="hero-card narrow">
    <div>
        <p class="eyebrow">Namensliste</p>
        <h2>Namen zum Abgleich</h2>
        <p class="muted">Eine reine Namensliste als Kopiervorlage – zum Weitergeben, damit alle prüfen können, ob noch jemand fehlt, der dazugehört.</p>
    </div>
</section>

<section class="panel">
    <form method="get" action="<?= e(url('/namensliste')) ?>" class="filter-grid">
        <label>
            <span>Kategorie</span>
            <select name="category_id">
                <option value="">Alle Kontakte</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e((string) $category['id']) ?>" <?= $categoryId === (string) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Sortierung</span>
            <select name="sort">
                <option value="nachname" <?= $sort === 'nachname' ? 'selected' : '' ?>>Nach Nachname</option>
                <option value="vorname" <?= $sort === 'vorname' ? 'selected' : '' ?>>Nach Vorname</option>
            </select>
        </label>
        <div class="filter-actions">
            <label class="inline-toggle">
                <input type="checkbox" name="numbered" value="1" <?= $numbered ? 'checked' : '' ?>>
                <span>Nummeriert</span>
            </label>
            <button type="submit">Liste aktualisieren</button>
        </div>
    </form>
</section>

<section class="panel stack">
    <div class="panel-head">
        <div>
            <h3>Kopiervorlage</h3>
            <p class="muted"><?= e((string) $count) ?> <?= $count === 1 ? 'Name' : 'Namen' ?>. Text bei Bedarf noch anpassen.</p>
        </div>
        <button type="button" class="ghost-button compact-action" data-copy="#nameListField"><?= icon('copy') ?><span>Kopieren</span></button>
    </div>

    <form method="post" action="<?= e(url('/namensliste')) ?>" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <textarea name="name_list" id="nameListField" rows="16" spellcheck="false"><?= e($nameList) ?></textarea>

        <?php if ($canSend): ?>
            <div class="subsection-card stack">
                <strong>Per E-Mail verschicken</strong>
                <label>
                    <span>Betreff</span>
                    <input type="text" name="subject" value="<?= e($defaultSubject) ?>" required>
                </label>
                <label>
                    <span>Empfänger – eine oder mehrere Adressen, mit Komma oder Zeilenumbruch getrennt</span>
                    <textarea name="recipients" rows="2" placeholder="orga@example.org, klassensprecher@example.org"></textarea>
                </label>
                <label>
                    <span>Einleitungstext (optional)</span>
                    <textarea name="intro" rows="3" placeholder="Hallo zusammen, bitte schaut die Liste durch …"></textarea>
                </label>
                <label class="inline-toggle">
                    <input type="checkbox" name="send_to_self" value="1">
                    <span>Kopie an mich (<?= e((string) ($currentUser['email'] ?? '')) ?>)</span>
                </label>
                <div class="toolbar-actions">
                    <button type="submit"><?= icon('message-send') ?><span>Namensliste senden</span></button>
                </div>
            </div>
        <?php endif; ?>
    </form>
</section>

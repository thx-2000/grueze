<?php
$activeFilters = [];
if ($categoryId !== '') {
    foreach ($categories as $c) {
        if ((string) $c['id'] === $categoryId) {
            $activeFilters[] = $c['name'];
        }
    }
}
if ($withoutEmail) {
    $activeFilters[] = 'ohne Mailadresse';
}
if ($withoutPhone) {
    $activeFilters[] = 'ohne Handynummer';
}
?>
<section class="hero-card narrow">
    <div>
        <p class="eyebrow">Namensliste</p>
        <h2>Namen zum Abgleich</h2>
        <p class="muted">Eine reine Namensliste als Kopiervorlage – zum Weitergeben, damit alle prüfen können, ob noch jemand fehlt, der dazugehört. Mit den Filtern lässt sich auch gezielt zeigen, wem noch die Mailadresse oder Handynummer fehlt.</p>
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
            <button type="submit">Liste aktualisieren</button>
        </div>
        <details class="admin-drawer filter-drawer" <?= ($withoutEmail || $withoutPhone || !$numbered) ? 'open' : '' ?>>
            <summary><span><?= icon('sliders') ?></span><span>Weitere Optionen</span></summary>
            <div class="admin-drawer-body">
                <div class="filter-advanced-grid">
                    <div class="filter-tags">
                        <span>Darstellung</span>
                        <label class="inline-toggle">
                            <input type="checkbox" name="numbered" value="1" <?= $numbered ? 'checked' : '' ?>>
                            <span>Nummeriert</span>
                        </label>
                    </div>
                    <div class="filter-tags">
                        <span>Nur Lücken zeigen</span>
                        <label class="inline-toggle">
                            <input type="checkbox" name="without_email" value="1" <?= $withoutEmail ? 'checked' : '' ?>>
                            <span>Nur Personen ohne Mailadresse</span>
                        </label>
                        <label class="inline-toggle">
                            <input type="checkbox" name="without_phone" value="1" <?= $withoutPhone ? 'checked' : '' ?>>
                            <span>Nur Personen ohne Handynummer</span>
                        </label>
                    </div>
                </div>
            </div>
        </details>
    </form>
</section>

<section class="panel stack">
    <div class="panel-head">
        <div>
            <h3>Kopiervorlage</h3>
            <p class="muted"><?= e((string) $count) ?> <?= $count === 1 ? 'Name' : 'Namen' ?><?= $activeFilters !== [] ? ' · ' . e(implode(' · ', $activeFilters)) : '' ?>. Text bei Bedarf noch anpassen.</p>
        </div>
        <button type="button" class="ghost-button compact-action" data-copy="#nameListField"><?= icon('copy') ?><span>Kopieren</span></button>
    </div>

    <form method="post" action="<?= e(url('/namensliste')) ?>" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="subject_title" value="<?= e($subjectTitle) ?>">
        <textarea name="name_list" id="nameListField" rows="16" spellcheck="false"><?= e($nameList) ?></textarea>

        <?php if ($canSend): ?>
            <div class="subsection-card stack">
                <strong>Verschicken</strong>
                <label>
                    <span>Einleitungstext (optional)</span>
                    <textarea name="intro" rows="3" placeholder="Hallo zusammen, bitte schaut die Liste durch …"></textarea>
                </label>

                <div class="stack" style="gap:0.5rem">
                    <p class="detail-hint">An eine Empfängergruppe: Du wählst im nächsten Schritt zwischen <strong>alle mit Mailadresse</strong>, einer <strong>Kategorie</strong> oder <strong>Tags</strong> und siehst die Mail vor dem Senden.</p>
                    <div class="toolbar-actions">
                        <button type="submit" class="button-link" formaction="<?= e(url('/namensliste/rundmail')) ?>"><?= icon('mail') ?><span>Als Rundmail an eine Gruppe</span></button>
                    </div>
                </div>

                <hr>

                <p class="detail-hint">Oder direkt an einzelne Adressen (für ein paar Empfänger):</p>
                <label>
                    <span>Betreff</span>
                    <input type="text" name="subject" value="<?= e($defaultSubject) ?>">
                </label>
                <label>
                    <span>Empfänger – eine oder mehrere Adressen, mit Komma oder Zeilenumbruch getrennt</span>
                    <textarea name="recipients" rows="2" placeholder="orga@example.org, klassensprecher@example.org"></textarea>
                </label>
                <label class="inline-toggle">
                    <input type="checkbox" name="send_to_self" value="1">
                    <span>Kopie an mich (<?= e((string) ($currentUser['email'] ?? '')) ?>)</span>
                </label>
                <div class="toolbar-actions">
                    <button type="submit" class="ghost-button"><?= icon('message-send') ?><span>An diese Adressen senden</span></button>
                </div>
            </div>
        <?php endif; ?>
    </form>
</section>

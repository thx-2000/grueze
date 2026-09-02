<header class="msg-head">
    <p class="eyebrow">Weihnachtsgrüße</p>
    <h1>Weihnachtsgrüße verschicken</h1>
    <p class="muted">Jede Person bekommt zufällig einen Text aus dem Pool (aktuell <strong><?= e((string) $poolSize) ?></strong> aktive) – so bekommt nicht die ganze Stufe dieselbe Mail. Vor dem Senden siehst du eine Vorschau und kannst neu mischen.</p>
</header>

<?php if ($poolSize === 0): ?>
    <section class="detail-card">
        <p class="field-hint">Im Pool sind noch keine aktiven Weihnachts-Texte. <a href="<?= e(url('/verwaltung/gruesse')) ?>">Zuerst Texte anlegen</a>.</p>
    </section>
<?php else: ?>
    <form method="post" action="<?= e(url('/gruesse/weihnachten/vorschau')) ?>" class="contact-detail-form" data-message-form>
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <section class="detail-card">
            <h2>Empfänger</h2>
            <div class="recipient-options" role="radiogroup" aria-label="Empfängerkreis">
                <label class="recipient-option is-active">
                    <input type="radio" name="recipient_mode" value="all" checked>
                    <span class="recipient-option-body">
                        <span class="recipient-option-title">Alle mit Mailadresse <span class="recipient-badge"><?= e((string) $totalWithEmail) ?></span></span>
                    </span>
                </label>
                <label class="recipient-option">
                    <input type="radio" name="recipient_mode" value="category">
                    <span class="recipient-option-body">
                        <span class="recipient-option-title">Eine Kategorie</span>
                        <span class="recipient-option-sub">
                            <select name="category_id" aria-label="Kategorie">
                                <option value="">Kategorie wählen …</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= e((string) $category['id']) ?>"><?= e($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </span>
                    </span>
                </label>
                <?php if ($tags !== []): ?>
                    <label class="recipient-option">
                        <input type="radio" name="recipient_mode" value="tags">
                        <span class="recipient-option-body">
                            <span class="recipient-option-title">Bestimmte Tags</span>
                            <span class="recipient-option-sub tag-picker">
                                <?php foreach ($tags as $tag): ?>
                                    <label class="tag-option"><input type="checkbox" name="tag_ids[]" value="<?= e((string) $tag['id']) ?>"><span><?= e($tag['name']) ?></span></label>
                                <?php endforeach; ?>
                            </span>
                        </span>
                    </label>
                <?php endif; ?>
            </div>
        </section>

        <section class="detail-card">
            <h2>Betreff &amp; Absender</h2>
            <div class="form-grid">
                <label class="full-width"><span>Betreff</span><input type="text" name="subject" value="Frohe Weihnachten!" required></label>
                <label>
                    <span>Absenderadresse</span>
                    <select name="sender_key" required>
                        <?php foreach ($identities as $identity): ?>
                            <option value="<?= e($identity['key']) ?>"><?= e($identity['name'] . ' <' . $identity['email'] . '>') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Antwort-an</span>
                    <select name="reply_to_key" required>
                        <?php foreach ($replyToOptions as $r): ?>
                            <option value="<?= e($r['key']) ?>"><?= e($r['name'] . ' <' . $r['email'] . '>') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        </section>

        <div class="detail-save-bar" data-save-bar>
            <span class="detail-save-hint">Vorschau erstellen und mischen.</span>
            <div class="detail-save-actions">
                <a class="ghost-button" href="<?= e(url('/verwaltung/gruesse')) ?>">Texte bearbeiten</a>
                <button type="submit"><?= icon('sparkles') ?><span>Vorschau erstellen</span></button>
            </div>
        </div>
    </form>
<?php endif; ?>

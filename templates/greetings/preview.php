<header class="msg-head">
    <p class="eyebrow">Weihnachtsgrüße · Vorschau</p>
    <h1><?= e((string) count($rows)) ?> Empfänger, gemischt</h1>
    <p class="muted">Betreff: „<?= e($subject) ?>". Jede Zeile geht einzeln und personalisiert raus. Passt die Mischung nicht, einfach neu mischen.</p>
</header>

<section class="detail-card">
    <div class="vote-matrix-wrap">
        <table class="vote-matrix greeting-preview-table">
            <thead>
                <tr><th scope="col">Person</th><th scope="col">Zugeloster Text</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <th scope="row">
                            <?= e($row['name']) ?>
                            <span class="muted"><?= e($row['email']) ?></span>
                        </th>
                        <td><?= e($row['text']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="greeting-preview-actions">
    <form method="post" action="<?= e(url('/gruesse/weihnachten/vorschau')) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="recipient_mode" value="<?= e($params['recipient_mode']) ?>">
        <input type="hidden" name="category_id" value="<?= e($params['category_id']) ?>">
        <?php foreach ($params['tag_ids'] as $tid): ?><input type="hidden" name="tag_ids[]" value="<?= e((string) $tid) ?>"><?php endforeach; ?>
        <input type="hidden" name="subject" value="<?= e($params['subject']) ?>">
        <input type="hidden" name="sender_key" value="<?= e($params['sender_key']) ?>">
        <input type="hidden" name="reply_to_key" value="<?= e($params['reply_to_key']) ?>">
        <button type="submit" class="ghost-button"><?= icon('reset') ?><span>Neu mischen</span></button>
    </form>
    <form method="post" action="<?= e(url('/mail/gruesse-senden')) ?>" onsubmit="return confirm('<?= e((string) count($rows)) ?> Weihnachtsgrüße jetzt verschicken?');">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <button type="submit" class="button-link"><?= icon('message-send') ?><span>Jetzt verschicken</span></button>
    </form>
</div>

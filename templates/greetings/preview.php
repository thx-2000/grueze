<?php $hasWhen = isset($rows[0]['when']); ?>
<header class="msg-head">
    <p class="eyebrow">Grüße · Vorschau</p>
    <h1><?= e($headline) ?></h1>
    <p class="muted">Betreff: „<?= e($subject) ?>". Jede Zeile geht einzeln und personalisiert raus. Passt die Mischung nicht, einfach neu mischen.</p>
</header>

<section class="detail-card">
    <div class="vote-matrix-wrap">
        <table class="vote-matrix greeting-preview-table">
            <thead>
                <tr>
                    <th scope="col">Person</th>
                    <?php if ($hasWhen): ?><th scope="col">Geburtstag</th><?php endif; ?>
                    <th scope="col">Zugeloster Text</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <th scope="row">
                            <?= e($row['name']) ?>
                            <span class="muted"><?= e($row['email']) ?></span>
                        </th>
                        <?php if ($hasWhen): ?><td class="muted"><?= e($row['when']) ?></td><?php endif; ?>
                        <td><?= e($row['text']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="greeting-preview-actions">
    <form method="post" action="<?= e($rebuild['action']) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <?php foreach ($rebuild['fields'] as $name => $value): ?>
            <?php if (is_array($value)): ?>
                <?php foreach ($value as $v): ?><input type="hidden" name="<?= e($name) ?>[]" value="<?= e((string) $v) ?>"><?php endforeach; ?>
            <?php else: ?>
                <input type="hidden" name="<?= e($name) ?>" value="<?= e((string) $value) ?>">
            <?php endif; ?>
        <?php endforeach; ?>
        <button type="submit" class="ghost-button"><?= icon('reset') ?><span>Neu mischen</span></button>
    </form>
    <form method="post" action="<?= e(url('/mail/gruesse-senden')) ?>" data-confirm="<?= e((string) count($rows)) ?> Grüße jetzt verschicken?">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <button type="submit" class="button-link"><?= icon('message-send') ?><span>Jetzt verschicken</span></button>
    </form>
</div>

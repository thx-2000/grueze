<?php
$ranges = [0 => 'Heute', 3 => 'Nächste 3 Tage', 7 => 'Nächste 7 Tage', 14 => 'Nächste 14 Tage'];
$withEmail = array_values(array_filter($rows, static fn (array $r): bool => trim((string) ($r['email'] ?? '')) !== ''));
$withoutEmail = array_values(array_filter($rows, static fn (array $r): bool => trim((string) ($r['email'] ?? '')) === ''));
?>
<header class="msg-head">
    <p class="eyebrow">Geburtstagsgrüße</p>
    <h1>Anstehende Geburtstage</h1>
    <p class="muted">Jede Person bekommt beim Verschicken zufällig einen Text aus dem Geburtstags-Pool (aktuell <strong><?= e((string) $poolSize) ?></strong> aktive). Vorher siehst du eine Vorschau und kannst neu mischen.</p>
</header>

<nav class="events-tabs" aria-label="Zeitraum">
    <?php foreach ($ranges as $d => $label): ?>
        <a class="<?= (int) $days === $d ? 'is-active' : '' ?>" href="<?= e(url('/gruesse/geburtstage?tage=' . $d)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($rows === []): ?>
    <section class="detail-card"><p class="muted">In diesem Zeitraum hat niemand Geburtstag.</p></section>
<?php else: ?>
    <section class="detail-card">
        <h2><?= count($rows) ?> Geburtstag<?= count($rows) === 1 ? '' : 'e' ?></h2>
        <ul class="completeness-list">
            <?php foreach ($rows as $r): ?>
                <li class="completeness-row">
                    <div class="completeness-person">
                        <strong><?= e(trim($r['vorname'] . ' ' . $r['nachname'])) ?></strong>
                        <span class="muted"><?= $r['days_until'] === 0 ? 'heute' : 'in ' . $r['days_until'] . ' Tag' . ($r['days_until'] === 1 ? '' : 'en') ?> · <?= e(format_date($r['geburtstag'])) ?></span>
                        <?php if (trim((string) ($r['email'] ?? '')) === ''): ?><span class="status-chip is-warn">keine Mail</span><?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <?php if ($withEmail === []): ?>
        <section class="detail-card"><p class="field-hint">Niemand davon hat eine Mailadresse – es gibt nichts zu verschicken.</p></section>
    <?php elseif ($poolSize === 0): ?>
        <section class="detail-card"><p class="field-hint">Im Pool sind noch keine aktiven Geburtstags-Texte. <a href="<?= e(url('/verwaltung/gruesse')) ?>">Zuerst Texte anlegen</a>.</p></section>
    <?php else: ?>
        <form method="post" action="<?= e(url('/gruesse/geburtstage/vorschau')) ?>" class="contact-detail-form">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="tage" value="<?= e((string) $days) ?>">
            <section class="detail-card">
                <h2>Betreff &amp; Absender</h2>
                <p class="field-hint"><?= count($withEmail) ?> Person<?= count($withEmail) === 1 ? '' : 'en' ?> mit Mailadresse<?= $withoutEmail !== [] ? ' · ' . count($withoutEmail) . ' ohne (werden übersprungen)' : '' ?>.</p>
                <div class="form-grid">
                    <label class="full-width"><span>Betreff</span><input type="text" name="subject" value="Alles Gute zum Geburtstag!" required></label>
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
                            <?php foreach ($replyToOptions as $rt): ?>
                                <option value="<?= e($rt['key']) ?>"><?= e($rt['name'] . ' <' . $rt['email'] . '>') ?></option>
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
<?php endif; ?>

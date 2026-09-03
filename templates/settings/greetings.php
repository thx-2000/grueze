<?php
$groups = [
    'birthday' => ['Geburtstagsgrüße', 'Wird beim Verschicken je Person zufällig gezogen.', $birthday],
    'christmas' => ['Weihnachtsgrüße', 'Beim Serienversand bekommt jede Person zufällig einen davon.', $christmas],
];
?>
<header class="contacts-header">
    <div>
        <h1>Grüße-Pool</h1>
        <p class="muted">Kurze, persönliche Standard-Wünsche. Platzhalter <code>{Anrede}</code>, <code>{Vorname}</code>, <code>{Nachname}</code> werden beim Versand ersetzt.</p>
    </div>
    <div class="hero-actions">
        <a class="ghost-button" href="<?= e(url('/gruesse/geburtstage')) ?>"><?= icon('calendar') ?><span>Geburtstage</span></a>
        <a class="ghost-button" href="<?= e(url('/gruesse/weihnachten')) ?>"><?= icon('mail') ?><span>Weihnachtsgrüße</span></a>
    </div>
</header>

<section class="detail-card">
    <h2>Geburtstage automatisch verschicken</h2>
    <p class="field-hint">Wenn aktiv, prüft das System täglich ab der eingestellten Uhrzeit, wer heute Geburtstag hat <strong>und</strong> eine Mailadresse hinterlegt hat, und schickt automatisch einen zufällig gezogenen Geburtstagsgruß. Braucht einen eingerichteten Cronjob (Verwaltung → System).</p>
    <form method="post" action="<?= e(url('/verwaltung/gruesse/automatik')) ?>" class="form-grid">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <label class="checkbox-row full-width">
            <input type="checkbox" name="auto_enabled" value="1" <?= !empty($autoBirthday['enabled']) ? 'checked' : '' ?>>
            <span>Automatischen Geburtstagsversand einschalten</span>
        </label>
        <label>
            <span>Uhrzeit</span>
            <input type="time" name="auto_time" value="<?= e((string) ($autoBirthday['time'] ?? '08:00')) ?>">
        </label>
        <label>
            <span>Betreff <span class="field-hint">(<code>{Vorname}</code> wird ersetzt)</span></span>
            <input type="text" name="auto_subject" maxlength="190" value="<?= e((string) ($autoBirthday['subject'] ?? '')) ?>">
        </label>
        <div class="form-actions full-width">
            <button type="submit">Speichern</button>
        </div>
    </form>
    <p class="field-hint">
        <?php if (!empty($autoBirthday['last_run'])): ?>
            Zuletzt gelaufen: <?= e(format_date($autoBirthday['last_run'])) ?>.
        <?php else: ?>
            Noch nicht gelaufen.
        <?php endif; ?>
        Der Text wird aus den aktiven <strong>Geburtstagsgrüßen</strong> unten gezogen – ist keiner aktiv, passiert nichts.
    </p>
</section>

<?php foreach ($groups as $occasion => [$title, $hint, $items]): ?>
    <section class="detail-card">
        <h2><?= e($title) ?> <span class="muted">(<?= count(array_filter($items, static fn (array $g): bool => (int) $g['is_active'] === 1)) ?> aktiv)</span></h2>
        <p class="field-hint"><?= e($hint) ?></p>

        <ul class="greeting-list">
            <?php foreach ($items as $item): ?>
                <li class="greeting-row<?= (int) $item['is_active'] === 1 ? '' : ' is-inactive' ?>">
                    <form method="post" action="<?= e(url('/verwaltung/gruesse/bearbeiten')) ?>" class="greeting-edit">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">
                        <textarea name="text" rows="2" aria-label="Text bearbeiten"><?= e((string) $item['text']) ?></textarea>
                        <div class="greeting-edit-actions">
                            <label class="inline-toggle">
                                <input type="checkbox" name="is_active" value="1" <?= (int) $item['is_active'] === 1 ? 'checked' : '' ?>>
                                <span>aktiv</span>
                            </label>
                            <button type="submit" class="ghost-button">Speichern</button>
                        </div>
                    </form>
                    <form method="post" action="<?= e(url('/verwaltung/gruesse/loeschen')) ?>" data-confirm="Diesen Gruß löschen?">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">
                        <button type="submit" class="danger-button icon-button" title="Löschen" aria-label="Löschen"><?= icon('trash') ?></button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>

        <form method="post" action="<?= e(url('/verwaltung/gruesse')) ?>" class="greeting-add">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="occasion" value="<?= e($occasion) ?>">
            <textarea name="text" rows="2" placeholder="Neuen <?= e($title === 'Geburtstagsgrüße' ? 'Geburtstagsgruß' : 'Weihnachtsgruß') ?> hinzufügen …" aria-label="Neuen Gruß hinzufügen" required></textarea>
            <button type="submit"><?= icon('plus') ?><span>Hinzufügen</span></button>
        </form>
    </section>
<?php endforeach; ?>

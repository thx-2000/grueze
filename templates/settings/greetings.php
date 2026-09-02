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
                    <form method="post" action="<?= e(url('/verwaltung/gruesse/loeschen')) ?>" onsubmit="return confirm('Diesen Gruß löschen?');">
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

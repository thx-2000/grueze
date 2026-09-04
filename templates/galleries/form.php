<?php
/**
 * @var array<string,mixed>|null $gallery
 * @var list<array<string,mixed>> $events
 */
$g = $gallery ?? [];
$isEdit = $gallery !== null;
$action = $isEdit ? url('/galerien/speichern') : url('/galerien');
?>
<header class="contact-detail-head">
    <p class="eyebrow"><a href="<?= e(url('/galerien')) ?>">Galerien</a></p>
    <h1><?= $isEdit ? 'Galerie bearbeiten' : 'Neue Galerie' ?></h1>
</header>

<section class="panel">
    <form method="post" action="<?= e($action) ?>" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e((string) $g['id']) ?>"><?php endif; ?>

        <label>
            <span>Titel <span aria-hidden="true">*</span></span>
            <input type="text" name="title" required maxlength="190" value="<?= e((string) ($g['title'] ?? old('title'))) ?>" autofocus>
        </label>

        <label>
            <span>Beschreibung</span>
            <textarea name="description" rows="3" maxlength="5000"><?= e((string) ($g['description'] ?? old('description'))) ?></textarea>
        </label>

        <div class="form-grid">
            <label>
                <span>Datum der Veranstaltung</span>
                <input type="date" name="gallery_date" value="<?= e(substr((string) ($g['gallery_date'] ?? old('gallery_date')), 0, 10)) ?>">
            </label>
            <label>
                <span>Sortierung der Medien</span>
                <select name="sort_mode">
                    <?php
                    $modes = ['captured' => 'Nach Aufnahmezeit', 'uploaded' => 'Nach Upload-Reihenfolge', 'manual' => 'Manuell (Ziehen)'];
                    $current = (string) ($g['sort_mode'] ?? 'captured');
                    foreach ($modes as $value => $label):
                    ?>
                        <option value="<?= e($value) ?>" <?= $current === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <label>
            <span>Zu einem Termin (optional)</span>
            <select name="event_id">
                <option value="">— kein Termin —</option>
                <?php foreach ($events as $event): ?>
                    <option value="<?= e((string) $event['id']) ?>" <?= (int) ($g['event_id'] ?? 0) === (int) $event['id'] ? 'selected' : '' ?>>
                        <?= e($event['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="form-actions">
            <button type="submit" class="button-link"><?= icon('check') ?><span><?= $isEdit ? 'Speichern' : 'Anlegen' ?></span></button>
            <a class="ghost-button" href="<?= e(url($isEdit ? '/galerien/ansehen?id=' . $g['id'] : '/galerien')) ?>">Abbrechen</a>
        </div>
    </form>
</section>

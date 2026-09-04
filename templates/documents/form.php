<?php
/**
 * @var array<string,mixed>|null $folder
 * @var array<string,mixed>|null $parent  gesetzt = Unterordner wird angelegt
 * @var list<array<string,mixed>> $announcements
 * @var list<array<string,mixed>> $groupChoices
 * @var bool $canPickGroup
 */
$f = $folder ?? [];
$isEdit = $folder !== null;
$action = $isEdit ? url('/dokumente/speichern') : url('/dokumente');
$parent = $parent ?? null;
?>
<header class="contact-detail-head">
    <p class="eyebrow">
        <a href="<?= e(url('/dokumente')) ?>">Dokumente</a>
        <?php if ($parent !== null): ?> · <a href="<?= e(url('/dokumente/ansehen?id=' . (int) $parent['id'])) ?>"><?= e((string) $parent['title']) ?></a><?php endif; ?>
    </p>
    <h1><?= $isEdit ? 'Ordner bearbeiten' : ($parent !== null ? 'Neuer Unterordner' : 'Neuer Ordner') ?></h1>
    <?php if ($parent !== null): ?><p class="muted">In „<?= e((string) $parent['title']) ?>"</p><?php endif; ?>
</header>

<section class="panel">
    <form method="post" action="<?= e($action) ?>" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e((string) $f['id']) ?>"><?php endif; ?>
        <?php if ($parent !== null): ?><input type="hidden" name="parent_id" value="<?= e((string) $parent['id']) ?>"><?php endif; ?>

        <label>
            <span>Titel <span aria-hidden="true">*</span></span>
            <input type="text" name="title" required maxlength="190" value="<?= e((string) ($f['title'] ?? old('title'))) ?>" autofocus>
        </label>

        <label>
            <span>Beschreibung</span>
            <textarea name="description" rows="3" maxlength="5000"><?= e((string) ($f['description'] ?? old('description'))) ?></textarea>
        </label>

        <label>
            <span>Zu einer Ankündigung (optional)</span>
            <select name="announcement_id">
                <option value="">— keine Ankündigung —</option>
                <?php foreach ($announcements as $announcement): ?>
                    <option value="<?= e((string) $announcement['id']) ?>" <?= (int) ($f['announcement_id'] ?? 0) === (int) $announcement['id'] ? 'selected' : '' ?>><?= e($announcement['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <?php view_partial('documents/_visibility-fields', ['folder' => $folder, 'groupChoices' => $groupChoices, 'canPickGroup' => $canPickGroup]); ?>

        <div class="form-actions">
            <button type="submit" class="button-link"><?= icon('check') ?><span><?= $isEdit ? 'Speichern' : 'Anlegen' ?></span></button>
            <a class="ghost-button" href="<?= e(url($isEdit ? '/dokumente/ansehen?id=' . $f['id'] : ($parent !== null ? '/dokumente/ansehen?id=' . $parent['id'] : '/dokumente'))) ?>">Abbrechen</a>
        </div>
    </form>
</section>

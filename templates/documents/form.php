<?php
/**
 * @var array<string,mixed>|null $folder
 * @var list<array<string,mixed>> $groupChoices
 * @var bool $canPickGroup
 */
$f = $folder ?? [];
$isEdit = $folder !== null;
$action = $isEdit ? url('/dokumente/speichern') : url('/dokumente');
?>
<header class="contact-detail-head">
    <p class="eyebrow"><a href="<?= e(url('/dokumente')) ?>">Dokumente</a></p>
    <h1><?= $isEdit ? 'Ordner bearbeiten' : 'Neuer Ordner' ?></h1>
</header>

<section class="panel">
    <form method="post" action="<?= e($action) ?>" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e((string) $f['id']) ?>"><?php endif; ?>

        <label>
            <span>Titel <span aria-hidden="true">*</span></span>
            <input type="text" name="title" required maxlength="190" value="<?= e((string) ($f['title'] ?? old('title'))) ?>" autofocus>
        </label>

        <label>
            <span>Beschreibung</span>
            <textarea name="description" rows="3" maxlength="5000"><?= e((string) ($f['description'] ?? old('description'))) ?></textarea>
        </label>

        <?php view_partial('documents/_visibility-fields', ['folder' => $folder, 'groupChoices' => $groupChoices, 'canPickGroup' => $canPickGroup]); ?>

        <div class="form-actions">
            <button type="submit" class="button-link"><?= icon('check') ?><span><?= $isEdit ? 'Speichern' : 'Anlegen' ?></span></button>
            <a class="ghost-button" href="<?= e(url($isEdit ? '/dokumente/ansehen?id=' . $f['id'] : '/dokumente')) ?>">Abbrechen</a>
        </div>
    </form>
</section>

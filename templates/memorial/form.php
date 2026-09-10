<?php
/**
 * @var array<string,mixed>|null $entry  null = neuer freier Eintrag
 * @var list<string> $roleSuggestions
 */
$e = $entry ?? [];
$roleSuggestions = $roleSuggestions ?? [];
$isEdit = $entry !== null;
$isLinked = $isEdit && $entry['contact_id'] !== null;
$action = $isEdit ? url('/memoriam/speichern') : url('/memoriam');
?>
<p class="detail-backlink"><a href="<?= e(url('/memoriam')) ?>"><?= icon('chevron-right') ?>Zurück zu <?= e(memorial_label()) ?></a></p>

<header class="contact-detail-head">
    <p class="eyebrow">Gedenken</p>
    <h1><?= $isEdit ? 'Eintrag bearbeiten' : 'Eintrag hinzufügen' ?></h1>
    <?php if ($isLinked): ?>
        <p class="muted">Name und Foto kommen aus dem verknüpften Kontakt und lassen sich hier nicht ändern.</p>
    <?php endif; ?>
</header>

<section class="panel">
    <form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e((string) $e['id']) ?>"><?php endif; ?>

        <?php if (!$isLinked): ?>
            <label>
                <span>Name <span aria-hidden="true">*</span></span>
                <input type="text" name="display_name" required maxlength="190" value="<?= e((string) ($e['name'] ?? old('display_name'))) ?>" autofocus>
            </label>
        <?php endif; ?>

        <label>
            <span>Bereich / Rolle</span>
            <input type="text" name="role_label" maxlength="120" list="memorialRoles" value="<?= e((string) ($e['role_label'] ?? old('role_label'))) ?>" placeholder="z. B. Lehrkräfte, Stufe 95">
            <small class="field-hint">Gleiche Schreibweise = gleiche Gruppe auf der Seite. Leer lassen für „ohne Gruppe".</small>
        </label>
        <?php if ($roleSuggestions !== []): ?>
            <datalist id="memorialRoles">
                <?php foreach ($roleSuggestions as $suggestion): ?>
                    <option value="<?= e($suggestion) ?>"></option>
                <?php endforeach; ?>
            </datalist>
        <?php endif; ?>

        <div class="form-grid">
            <label>
                <span>Geburtsjahr</span>
                <input type="number" name="born_year" min="1900" max="2100" step="1" inputmode="numeric" value="<?= e((string) ($e['born_year'] ?? old('born_year'))) ?>">
            </label>
            <label>
                <span>Sterbejahr</span>
                <input type="number" name="died_year" min="1900" max="2100" step="1" inputmode="numeric" value="<?= e((string) ($e['died_year'] ?? old('died_year'))) ?>">
            </label>
        </div>
        <div class="form-grid">
            <label>
                <span>Genaues Geburtsdatum (optional)</span>
                <input type="date" name="born_on" value="<?= e(substr((string) ($e['born_on'] ?? old('born_on')), 0, 10)) ?>">
            </label>
            <label>
                <span>Genaues Sterbedatum (optional)</span>
                <input type="date" name="died_on" value="<?= e(substr((string) ($e['died_on'] ?? old('died_on')), 0, 10)) ?>">
            </label>
        </div>
        <small class="field-hint">Sind beide vollen Daten bekannt, wird das Lebensalter automatisch berechnet.</small>

        <label>
            <span>Gedenkzeile</span>
            <textarea name="note" rows="3" maxlength="500"><?= e((string) ($e['note'] ?? old('note'))) ?></textarea>
        </label>

        <?php if (!$isLinked): ?>
            <label>
                <span>Foto</span>
                <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp" aria-describedby="memPhotoHint">
                <small class="field-hint" id="memPhotoHint">JPG, PNG oder WEBP bis 2 MB.<?php if (!empty($e['photo_path'])): ?> Aktuell ist ein Bild hinterlegt – ein neues ersetzt es.<?php endif; ?></small>
            </label>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="button-link"><?= icon('check') ?><span><?= $isEdit ? 'Speichern' : 'Hinzufügen' ?></span></button>
            <a class="ghost-button" href="<?= e(url('/memoriam')) ?>">Abbrechen</a>
        </div>
    </form>
</section>

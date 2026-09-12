<?php
/**
 * @var array<string,mixed>|null $person  null = neuer Eintrag
 * @var list<string> $roleSuggestions
 */
$p = $person ?? [];
$roleSuggestions = $roleSuggestions ?? [];
$isEdit = $person !== null;
$action = $isEdit ? url('/weitere-personen/speichern') : url('/weitere-personen');
?>
<p class="detail-backlink"><a href="<?= e(url('/weitere-personen')) ?>"><?= icon('chevron-right') ?>Zurück zu <?= e(roster_label()) ?></a></p>

<header class="contact-detail-head<?= $isEdit ? ' contact-detail-head--avatar' : '' ?>">
    <?php if ($isEdit): ?><?= contact_avatar($p, 'lg', true) ?><?php endif; ?>
    <div class="contact-detail-head-main">
        <p class="eyebrow"><?= e(roster_label()) ?></p>
        <h1><?= $isEdit ? e((string) $p['name']) : 'Eintrag hinzufügen' ?></h1>
        <?php if ($isEdit && $p['is_deceased']): ?>
            <p class="muted"><?= icon('cross') ?> Als verstorben eingetragen<?php if (!empty($p['died_on'])): ?> (<?= e(format_date((string) $p['died_on'])) ?>)<?php endif; ?> – erscheint auf der Gedenkseite.</p>
        <?php endif; ?>
    </div>
</header>

<section class="panel">
    <form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="contact-detail-form" data-detail-form>
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e((string) $p['id']) ?>"><?php endif; ?>

        <section class="detail-card">
            <h2>Stammdaten</h2>
            <div class="form-grid">
                <label class="full-width">
                    <span>Name <span class="required-marker" aria-hidden="true">*</span></span>
                    <input type="text" name="name" required maxlength="190" value="<?= e((string) ($p['name'] ?? old('name'))) ?>" autofocus>
                </label>
                <label class="full-width">
                    <span>Fach / Rolle</span>
                    <input type="text" name="role_label" maxlength="160" list="rosterRoles" value="<?= e((string) ($p['role_label'] ?? old('role_label'))) ?>" placeholder="z. B. Deutsch LK, Mathe GK">
                    <small class="field-hint">Frei wählbar – z. B. Fach/Kurs beim Abi-Jahrgang oder eine andere Zuordnung.</small>
                </label>
                <?php if ($roleSuggestions !== []): ?>
                    <datalist id="rosterRoles">
                        <?php foreach ($roleSuggestions as $suggestion): ?>
                            <option value="<?= e($suggestion) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                <?php endif; ?>
                <label>
                    <span>Geburtsdatum</span>
                    <input type="date" name="born_on" value="<?= e(substr((string) ($p['born_on'] ?? old('born_on')), 0, 10)) ?>">
                </label>
                <label>
                    <span>Todesdatum</span>
                    <input type="date" name="died_on" value="<?= e(substr((string) ($p['died_on'] ?? old('died_on')), 0, 10)) ?>">
                    <small class="field-hint">Gesetzt = erscheint automatisch auf <?= e(memorial_label()) ?>, bleibt aber hier stehen.</small>
                </label>
            </div>
        </section>

        <section class="detail-card">
            <h2>Kontakt</h2>
            <div class="form-grid">
                <label><span>E-Mail</span><input type="text" name="email" inputmode="email" maxlength="190" value="<?= e((string) ($p['email'] ?? old('email'))) ?>" placeholder="name@example.com"></label>
                <label><span>Handy</span><input type="text" name="mobile" inputmode="tel" maxlength="60" value="<?= e((string) ($p['mobile'] ?? old('mobile'))) ?>" placeholder="+49 …"></label>
            </div>
        </section>

        <section class="detail-card">
            <h2>Adresse</h2>
            <div class="form-grid">
                <label class="full-width"><span>Straße</span><input type="text" name="strasse" maxlength="190" value="<?= e((string) ($p['strasse'] ?? old('strasse'))) ?>"></label>
                <label><span>PLZ</span><input type="text" name="plz" maxlength="20" value="<?= e((string) ($p['plz'] ?? old('plz'))) ?>"></label>
                <label><span>Ort</span><input type="text" name="ort" maxlength="120" value="<?= e((string) ($p['ort'] ?? old('ort'))) ?>"></label>
                <label><span>Land</span><input type="text" name="land" maxlength="80" value="<?= e((string) ($p['land'] ?? old('land', config('defaults.country', 'Deutschland')))) ?>"></label>
                <label class="full-width">
                    <span>Profilbild</span>
                    <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp" aria-describedby="rosterPhotoHint">
                    <small class="field-hint" id="rosterPhotoHint">JPG, PNG oder WEBP bis 2 MB.<?php if ($isEdit && !empty($p['photo_path'])): ?> Ein neues Bild ersetzt das aktuelle.<?php endif; ?></small>
                </label>
                <?php if ($isEdit && !empty($p['photo_path'])): ?>
                    <div class="full-width photo-field-current">
                        <?= contact_avatar($p, 'md', true) ?>
                        <label class="inline-toggle"><input type="checkbox" name="photo_remove" value="1"><span>Aktuelles Bild entfernen</span></label>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="detail-card">
            <h2>Notiz</h2>
            <label>
                <span>Freitext</span>
                <textarea name="note" rows="3" maxlength="500" placeholder="z. B. „hatte uns nur in Klasse 8“"><?= e((string) ($p['note'] ?? old('note'))) ?></textarea>
            </label>
        </section>

        <div class="form-actions">
            <button type="submit" class="button-link"><?= icon('check') ?><span><?= $isEdit ? 'Speichern' : 'Hinzufügen' ?></span></button>
            <a class="ghost-button" href="<?= e(url('/weitere-personen')) ?>">Abbrechen</a>
        </div>
    </form>
</section>

<?php if ($isEdit): ?>
    <section class="detail-card">
        <h2>Eintrag entfernen</h2>
        <p class="field-hint">Entfernt „<?= e((string) $p['name']) ?>“ komplett aus <?= e(roster_label()) ?>.<?= $p['is_deceased'] ? ' Der zugehörige Eintrag auf ' . e(memorial_label()) . ' wird dabei ebenfalls entfernt.' : '' ?></p>
        <form method="post" action="<?= e(url('/weitere-personen/loeschen')) ?>" data-confirm="„<?= e((string) $p['name']) ?>“ wirklich entfernen?<?= $p['is_deceased'] ? ' Der zugehörige Gedenk-Eintrag wird ebenfalls entfernt.' : '' ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= e((string) $p['id']) ?>">
            <button type="submit" class="danger-button"><?= icon('trash') ?><span>Löschen</span></button>
        </form>
    </section>
<?php endif; ?>

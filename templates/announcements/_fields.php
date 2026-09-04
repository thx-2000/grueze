<?php
/**
 * Formular-Inhalt für „Neue Ankündigung" und das Bearbeiten auf der
 * Detailseite – Titel/Info/Ort/Zeitraum, Sichtbarkeit, Links.
 *
 * @var array<string,mixed>|null       $announcement null = Neuanlage
 * @var array<string,mixed>            $pickerData   contacts/groups/tags/documents/pollEvents
 * @var list<array{kind:string,ref_id:int}> $audienceRows aktuelle Sichtbarkeits-Einschränkung (leer bei Neuanlage)
 */
$a = $announcement ?? [];
$isEdit = $announcement !== null;
$currentAudience = array_map(
    static fn (array $r): string => $r['kind'] . ':' . $r['ref_id'],
    $audienceRows ?? []
);
$links = $isEdit ? ($a['links'] ?? []) : [];
?>
<section class="detail-card">
    <h2>Worum geht es?</h2>
    <div class="form-grid">
        <label class="full-width">
            <span>Titel <span class="required-marker" aria-hidden="true">*</span></span>
            <input type="text" name="title" required maxlength="190" value="<?= e((string) ($a['title'] ?? old('title'))) ?>">
        </label>
        <label class="full-width">
            <span>Info vom Orga-Team</span>
            <textarea name="info" rows="5"><?= e((string) ($a['info'] ?? old('info'))) ?></textarea>
        </label>
        <label><span>Ort</span><input type="text" name="location" value="<?= e((string) ($a['location'] ?? old('location'))) ?>"></label>
    </div>
</section>

<section class="detail-card">
    <h2>Zeitraum (optional)</h2>
    <p class="field-hint">Leer lassen für eine Ankündigung ohne festes Datum (z. B. ein dauerhafter Hinweis).</p>
    <div class="form-grid">
        <label><span>Von</span><input type="date" name="starts_at" value="<?= e(substr((string) ($a['starts_at'] ?? old('starts_at')), 0, 10)) ?>"></label>
        <label><span>Bis</span><input type="date" name="ends_at" value="<?= e(substr((string) ($a['ends_at'] ?? old('ends_at')), 0, 10)) ?>"></label>
    </div>
</section>

<section class="detail-card">
    <h2>Sichtbar für</h2>
    <p class="field-hint">Ohne Auswahl sehen alle angemeldeten Personen die Ankündigung. Mit Auswahl nur die gewählten Personen, Mitglieder der gewählten Gruppen oder Personen mit den gewählten Tags. Verwaltung sieht immer alles.</p>
    <div class="form-grid">
        <label>
            <span>Bestimmte Personen</span>
            <select name="audience_contacts[]" multiple size="6">
                <?php foreach ($pickerData['contacts'] as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= in_array('contact:' . $c['id'], $currentAudience, true) ? 'selected' : '' ?>><?= e(trim($c['vorname'] . ' ' . $c['nachname'])) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Bestimmte Gruppen</span>
            <select name="audience_groups[]" multiple size="6">
                <?php foreach ($pickerData['groups'] as $g): ?>
                    <option value="<?= (int) $g['id'] ?>" <?= in_array('group:' . $g['id'], $currentAudience, true) ? 'selected' : '' ?>><?= e((string) $g['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Bestimmte Tags</span>
            <select name="audience_tags[]" multiple size="6">
                <?php foreach ($pickerData['tags'] as $t): ?>
                    <option value="<?= (int) $t['id'] ?>" <?= in_array('tag:' . $t['id'], $currentAudience, true) ? 'selected' : '' ?>><?= e((string) $t['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
</section>

<section class="detail-card">
    <h2>Links (optional)</h2>
    <p class="field-hint">Extern, zu einem Dokument oder zu einer Abstimmung – z. B. wenn vorher etwas ausgefüllt werden muss.</p>
    <div class="link-options" data-link-options>
        <?php if ($links === []): $links = [['kind' => 'extern', 'label' => '', 'url' => '']]; endif; ?>
        <?php foreach ($links as $link): ?>
            <?php view_partial('announcements/_link-row', ['link' => $link, 'pickerData' => $pickerData]); ?>
        <?php endforeach; ?>
    </div>
    <button type="button" class="ghost-button" data-add-link><?= icon('plus') ?><span>Weiterer Link</span></button>
    <template id="linkRowTemplate">
        <?php view_partial('announcements/_link-row', ['link' => null, 'pickerData' => $pickerData]); ?>
    </template>
</section>

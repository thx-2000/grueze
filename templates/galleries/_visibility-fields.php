<?php
/**
 * Sichtbarkeit/Zugehörigkeit einer Galerie – gemeinsam für „Neue Galerie" und
 * das Bearbeiten-Formular auf der Galerie-Seite.
 *
 * @var array<string,mixed>|null $gallery       null = Neuanlage
 * @var list<array<string,mixed>> $groupChoices  bei globalem Verwalten alle Gruppen, sonst nur eigene geleitete
 * @var bool $canPickGroup                       true = globales galleries.manage (jede Gruppe wählbar)
 */
$g = $gallery ?? [];
$currentVisible = (int) ($g['visible_group_id'] ?? 0) ?: null;
$currentOwner = (int) ($g['owner_group_id'] ?? 0) ?: null;
?>
<?php if ($canPickGroup): ?>
    <label>
        <span>Sichtbar für</span>
        <select name="visible_group_id">
            <option value="">Alle mit Ansehen-Recht (normal)</option>
            <?php foreach ($groupChoices as $group): ?>
                <option value="<?= e((string) $group['id']) ?>" <?= $currentVisible === (int) $group['id'] ? 'selected' : '' ?>>
                    Nur Gruppe „<?= e($group['name']) ?>"
                </option>
            <?php endforeach; ?>
        </select>
        <small class="field-hint">Schränkt das Ansehen auf die Mitglieder dieser Gruppe ein (plus Verwaltung). Admin sieht immer alles.</small>
    </label>
<?php elseif ($groupChoices !== []): ?>
    <?php $ownerChoice = $currentOwner ?? (int) $groupChoices[0]['id']; ?>
    <?php if (count($groupChoices) > 1 && $gallery === null): ?>
        <label>
            <span>Gehört zu deiner Gruppe</span>
            <select name="owner_group_id">
                <?php foreach ($groupChoices as $group): ?>
                    <option value="<?= e((string) $group['id']) ?>" <?= $ownerChoice === (int) $group['id'] ? 'selected' : '' ?>><?= e($group['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    <?php else: ?>
        <?php $ownerName = array_values(array_filter($groupChoices, static fn (array $x): bool => (int) $x['id'] === $ownerChoice)); ?>
        <input type="hidden" name="owner_group_id" value="<?= e((string) $ownerChoice) ?>">
        <p class="field-hint">Galerie deiner Gruppe „<?= e((string) ($ownerName[0]['name'] ?? '')) ?>".</p>
    <?php endif; ?>
    <fieldset class="stack" style="border:0;padding:0;margin:0">
        <legend>Sichtbar für</legend>
        <label class="inline-toggle">
            <input type="radio" name="visible_group_id" value="" <?= $currentVisible === null ? 'checked' : '' ?>>
            <span>Alle angemeldeten Personen (mit Ansehen-Recht)</span>
        </label>
        <label class="inline-toggle">
            <input type="radio" name="visible_group_id" value="<?= e((string) $ownerChoice) ?>" <?= $currentVisible !== null ? 'checked' : '' ?>>
            <span>Nur Teilnehmende deiner Gruppe</span>
        </label>
    </fieldset>
<?php endif; ?>

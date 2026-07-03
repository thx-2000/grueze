<?php
$roleLabels = [
    'admin'          => 'Admin',
    'orga'           => 'Orga',
    'stufenmitglied' => 'Stufenmitglied',
    'betrachter'     => 'Betrachter',
];
?>
<section class="hero-card narrow">
    <div>
        <p class="eyebrow">Einstellungen</p>
        <h2>Sichtbarkeit & Rollen</h2>
        <p class="muted">Hier legst du fest, welche Rollen welche Kontaktfelder sehen dürfen. Stufenmitglieder und Betrachter sehen standardmäßig nur Namen und Kategorie.</p>
    </div>
</section>

<section class="panel">
    <form method="post" action="<?= e(url('/settings/visibility')) ?>" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <?php foreach ($fieldLabels as $field => $label): ?>
            <div class="subsection-card stack">
                <strong><?= e($label) ?></strong>
                <div class="tag-picker">
                    <?php foreach ($roles as $role): ?>
                        <label class="inline-toggle">
                            <input type="checkbox"
                                   name="visibility[<?= e($field) ?>][]"
                                   value="<?= e($role) ?>"
                                <?= in_array($role, $visibility[$field] ?? [], true) ? 'checked' : '' ?>>
                            <span><?= e($roleLabels[$role] ?? $role) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="field-hint">Standard: <?= e(implode(', ', array_map(static fn (string $r): string => $roleLabels[$r] ?? $r, $defaults[$field] ?? []))) ?></p>
            </div>
        <?php endforeach; ?>

        <div class="toolbar-actions">
            <button type="submit">Sichtbarkeit speichern</button>
        </div>
    </form>
</section>

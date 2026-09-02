<header class="page-head">
    <p class="eyebrow">Einstellungen</p>
    <h1>Sichtbarkeit</h1>
    <p class="muted">Hier legst du fest, welche Rollen welche Kontaktfelder sehen dürfen. Rollen selbst verwaltest du unter <a href="<?= e(url('/settings/roles')) ?>">Rollen</a>. Admin sieht immer alles.</p>
</header>

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

        <div class="subsection-card stack">
            <strong>Eigener Kontakt</strong>
            <label class="inline-toggle">
                <input type="checkbox" name="own_contact_visible" value="1" <?= !empty($ownContactVisible) ? 'checked' : '' ?>>
                <span>Nutzer:innen sehen die Daten ihres eigenen verknüpften Kontakts immer</span>
            </label>
            <p class="field-hint">Zeigt eingeloggten Personen ihre eigenen Kontaktdaten (Adresse, Geburtstag, Mail, Telefon, Login) – auch wenn ihre Rolle sonst nichts sieht. <strong>Notizen bleiben ausgenommen</strong> und folgen weiter der Rollen-Regel oben.</p>
        </div>

        <div class="toolbar-actions">
            <button type="submit">Sichtbarkeit speichern</button>
        </div>
    </form>
</section>

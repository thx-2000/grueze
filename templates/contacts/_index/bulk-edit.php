<?php
/**
 * Sammelbearbeitung der Auswahl (nur `contacts.manage`). Liegt im
 * Auswahl-Formular, teilt sich also `selected_contacts[]` mit den
 * Versand-Buttons; die einzelnen Aktionen setzen ihr eigenes `formaction`.
 *
 * @var array<int,array<string,mixed>> $categories
 * @var array<int,array<string,mixed>> $tags
 */
?>
<details class="admin-drawer bulk-edit-drawer">
    <summary><span><?= icon('sliders') ?></span><span>Sammelbearbeitung der Auswahl</span></summary>
    <div class="admin-drawer-body">
        <div class="bulk-editor">
            <label>
                <span>Kategorie für Auswahl</span>
                <select name="bulk_category_id">
                    <option value="">Kategorie unverändert</option>
                    <option value="__none__">Kategorie entfernen</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e((string) $category['id']) ?>"><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="inline-toggle">
                <input type="checkbox" name="bulk_category_only_if_empty" value="1">
                <span>Nur setzen, wenn noch keine Kategorie gepflegt ist</span>
            </label>
            <div role="group" aria-label="Tags ergänzen">
                <span>Tags ergänzen</span>
                <div class="tag-picker compact-picker">
                    <?php foreach ($tags as $tag): ?>
                        <label class="tag-option">
                            <input type="checkbox" name="bulk_tag_ids_add[]" value="<?= e((string) $tag['id']) ?>">
                            <span><?= e($tag['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                    <?php if ($tags === []): ?>
                        <p class="field-hint">Noch keine Tags angelegt.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div role="group" aria-label="Tags entfernen">
                <span>Tags entfernen</span>
                <div class="tag-picker compact-picker">
                    <?php foreach ($tags as $tag): ?>
                        <label class="tag-option">
                            <input type="checkbox" name="bulk_tag_ids_remove[]" value="<?= e((string) $tag['id']) ?>">
                            <span><?= e($tag['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                    <?php if ($tags === []): ?>
                        <p class="field-hint">Noch keine Tags angelegt.</p>
                    <?php endif; ?>
                </div>
            </div>
            <p class="field-hint" id="bulkSelectionHint" role="status">Keine Kontakte ausgewählt.</p>
            <div class="toolbar-actions">
                <button type="submit" formaction="<?= e(url('/contacts/bulk-update')) ?>" formmethod="post">
                    <?= icon('edit') ?><span>Auf Auswahl anwenden</span>
                </button>
            </div>
            <?php if (can('groups.manage')): ?>
                <div class="bulk-group-from-selection">
                    <label>
                        <span>Aus der Auswahl eine neue Gruppe machen</span>
                        <input type="text" name="group_name" maxlength="120" placeholder="Name der Gruppe">
                    </label>
                    <button type="submit" class="ghost-button" formaction="<?= e(url('/contacts/gruppe-aus-auswahl')) ?>" formmethod="post">
                        <?= icon('contacts') ?><span>Gruppe anlegen</span>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</details>

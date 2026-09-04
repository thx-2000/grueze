<?php
/**
 * Eine Link-Zeile im Ankündigungs-Formular – wiederverwendet für bestehende
 * Zeilen und als <template> für „Weiterer Link".
 *
 * @var array<string,mixed>|null $link       null = leere Vorlage
 * @var array<string,mixed>      $pickerData documents/pollEvents
 */
$l = $link ?? [];
$kind = (string) ($l['kind'] ?? 'extern');
// Die Auswahl selbst wird nicht gespeichert (nur die fertige URL) – beim
// Bearbeiten die ID aus der gespeicherten URL zurückgewinnen.
$linkedId = 0;
if (in_array($kind, ['dokument', 'abstimmung'], true) && preg_match('/[?&]id=(\d+)/', (string) ($l['url'] ?? ''), $m)) {
    $linkedId = (int) $m[1];
}
?>
<div class="link-row" data-link-row>
    <select name="link_kind[]" data-link-kind>
        <option value="extern" <?= $kind === 'extern' ? 'selected' : '' ?>>Extern</option>
        <option value="dokument" <?= $kind === 'dokument' ? 'selected' : '' ?>>Dokument</option>
        <option value="abstimmung" <?= $kind === 'abstimmung' ? 'selected' : '' ?>>Abstimmung</option>
    </select>
    <input type="text" name="link_label[]" value="<?= e((string) ($l['label'] ?? '')) ?>" placeholder="Beschriftung (optional)">
    <input type="url" name="link_url[]" value="<?= $kind === 'extern' ? e((string) ($l['url'] ?? '')) : '' ?>" placeholder="https://…" data-link-field="extern" <?= $kind !== 'extern' ? 'hidden' : '' ?>>
    <select name="link_document_id[]" data-link-field="dokument" <?= $kind !== 'dokument' ? 'hidden' : '' ?>>
        <option value="">– Dokument wählen –</option>
        <?php foreach ($pickerData['documents'] as $doc): ?>
            <option value="<?= (int) $doc['id'] ?>" <?= $kind === 'dokument' && $linkedId === (int) $doc['id'] ? 'selected' : '' ?>><?= e((string) $doc['folder_title']) ?> · <?= e((string) $doc['title']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="link_event_id[]" data-link-field="abstimmung" <?= $kind !== 'abstimmung' ? 'hidden' : '' ?>>
        <option value="">– Abstimmung wählen –</option>
        <?php foreach ($pickerData['pollEvents'] as $ev): ?>
            <option value="<?= (int) $ev['id'] ?>" <?= $kind === 'abstimmung' && $linkedId === (int) $ev['id'] ? 'selected' : '' ?>><?= e((string) $ev['title']) ?><?= trim((string) ($ev['group_name'] ?? '')) !== '' ? ' (Gruppe: ' . e((string) $ev['group_name']) . ')' : '' ?></option>
        <?php endforeach; ?>
    </select>
    <button type="button" class="danger-button icon-button" data-remove-link aria-label="Zeile entfernen"><?= icon('x') ?></button>
</div>

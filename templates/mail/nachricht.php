<?php
$draft = (array) ($draft ?? []);
$presetContacts = $presetContacts ?? [];
$hasPreset = $presetContacts !== [];
$fromFilter = (bool) ($fromFilter ?? false);
$eventId = isset($eventId) ? (int) $eventId : null;

$branding = app_branding();
$appName = (string) ($branding['branding_app_name'] ?? 'Adress-Zentrale');

$defaultSenderKey = $defaultSenderKey ?? ($identities[0]['key'] ?? '');
$defaultReplyToKey = $defaultReplyToKey ?? ($replyToOptions[0]['key'] ?? $defaultSenderKey);
$activePrefix = $draft['subject_prefix'] ?? $defaultSubjectPrefix;
$activeSalutation = $draft['salutation_mode'] ?? ($defaultSalutationMode ?? 'auto');
$subjectPreview = trim(($activePrefix !== '' ? $activePrefix . ' ' : '') . ($draft['subject'] ?? 'Dein Betreff'));

// Vorausgewählter Empfängerkreis: Entwurf > feste Auswahl > Filter > alle.
$activeMode = (string) ($draft['recipient_mode'] ?? '');
if ($activeMode === '') {
    $activeMode = $hasPreset ? 'selection' : ($fromFilter ? 'filter' : 'all');
}
$draftCategoryId = (string) ($draft['category_id'] ?? '');
$draftTagIds = array_map('intval', (array) ($draft['tag_ids'] ?? []));
$draftListId = (string) ($draft['list_id'] ?? '');
$presetCount = count($presetContacts);

$option = static function (string $value, string $active): string {
    return 'value="' . e($value) . '"' . ($value === $active ? ' checked' : '');
};
?>
<header class="msg-head">
    <p class="eyebrow">Nachrichten</p>
    <h1>Personalisierte E-Mail</h1>
    <p class="muted">Empfängerkreis wählen und Nachricht schreiben. Platzhalter: <code>{Anrede}</code>, <code>{Vorname}</code>, <code>{Nachname}</code><?= $eventId !== null ? ', <code>{Abstimmungslink}</code>' : '' ?>. Angeschrieben wird nur, wer eine Mailadresse hinterlegt hat.</p>
</header>

<form id="mailComposeForm" method="post" action="<?= e(url('/mail/start')) ?>" enctype="multipart/form-data" class="contact-detail-form" data-message-form data-count-url="<?= e(url('/rundmail/anzahl')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <?php if ($eventId !== null): ?><input type="hidden" name="event_id" value="<?= e((string) $eventId) ?>"><?php endif; ?>

    <section class="detail-card">
        <h2>Empfänger</h2>
        <div class="recipient-options" role="radiogroup" aria-label="Empfängerkreis">
            <?php if ($hasPreset): ?>
                <label class="recipient-option">
                    <input type="radio" name="recipient_mode" <?= $option('selection', $activeMode) ?>>
                    <span class="recipient-option-body">
                        <span class="recipient-option-title">Ausgewählte Kontakte <span class="recipient-badge"><?= e((string) $presetCount) ?></span></span>
                        <span class="recipient-option-hint"><?= e(implode(', ', array_map(static fn (array $c): string => trim($c['vorname'] . ' ' . $c['nachname']), array_slice($presetContacts, 0, 6)))) ?><?= $presetCount > 6 ? ' …' : '' ?></span>
                    </span>
                </label>
                <?php foreach ($presetContacts as $c): ?>
                    <input type="hidden" name="contact_ids[]" value="<?= e((string) $c['id']) ?>">
                <?php endforeach; ?>
            <?php endif; ?>

            <label class="recipient-option">
                <input type="radio" name="recipient_mode" <?= $option('all', $activeMode) ?>>
                <span class="recipient-option-body">
                    <span class="recipient-option-title">Alle mit Mailadresse <span class="recipient-badge"><?= e((string) $totalWithEmail) ?></span></span>
                </span>
            </label>

            <?php if ($fromFilter): ?>
                <label class="recipient-option">
                    <input type="radio" name="recipient_mode" <?= $option('filter', $activeMode) ?>>
                    <span class="recipient-option-body">
                        <span class="recipient-option-title">Aktuelle Filterauswahl <span class="recipient-badge"><?= e((string) $filterCount) ?></span></span>
                        <span class="recipient-option-hint"><?= e($filterSummary) ?></span>
                    </span>
                </label>
            <?php endif; ?>

            <label class="recipient-option">
                <input type="radio" name="recipient_mode" <?= $option('category', $activeMode) ?>>
                <span class="recipient-option-body">
                    <span class="recipient-option-title">Eine Kategorie</span>
                    <span class="recipient-option-sub">
                        <select name="category_id" aria-label="Kategorie für den Versand">
                            <option value="">Kategorie wählen …</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= e((string) $category['id']) ?>" <?= $draftCategoryId === (string) $category['id'] ? 'selected' : '' ?>>
                                    <?= e($category['name']) ?> (<?= e((string) ($categoryCounts[(int) $category['id']] ?? 0)) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </span>
                </span>
            </label>

            <?php if ($tags !== []): ?>
                <label class="recipient-option">
                    <input type="radio" name="recipient_mode" <?= $option('tags', $activeMode) ?>>
                    <span class="recipient-option-body">
                        <span class="recipient-option-title">Bestimmte Tags</span>
                        <span class="recipient-option-hint">Angeschrieben wird, wer mindestens einen der gewählten Tags hat.</span>
                        <span class="recipient-option-sub tag-picker">
                            <?php foreach ($tags as $tag): ?>
                                <label class="tag-option<?= in_array((int) $tag['id'], $draftTagIds, true) ? ' is-selected' : '' ?>">
                                    <input type="checkbox" name="tag_ids[]" value="<?= e((string) $tag['id']) ?>" <?= in_array((int) $tag['id'], $draftTagIds, true) ? 'checked' : '' ?>>
                                    <span><?= e($tag['name']) ?> (<?= e((string) ($tagCounts[(int) $tag['id']] ?? 0)) ?>)</span>
                                </label>
                            <?php endforeach; ?>
                        </span>
                    </span>
                </label>
            <?php endif; ?>

            <?php if ($recipientLists !== []): ?>
                <label class="recipient-option">
                    <input type="radio" name="recipient_mode" <?= $option('list', $activeMode) ?>>
                    <span class="recipient-option-body">
                        <span class="recipient-option-title">Gespeicherte Liste</span>
                        <span class="recipient-option-sub">
                            <select name="list_id" aria-label="Gespeicherte Empfängerliste">
                                <option value="">Liste wählen …</option>
                                <?php foreach ($recipientLists as $list): ?>
                                    <option value="<?= e((string) $list['id']) ?>" <?= $draftListId === (string) $list['id'] ? 'selected' : '' ?>>
                                        <?= e($list['name']) ?> (<?= e((string) $list['reachable']) ?><?php if ($list['reachable'] !== $list['total']): ?> von <?= e((string) $list['total']) ?><?php endif; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </span>
                    </span>
                </label>
            <?php endif; ?>
        </div>

        <div class="save-list-row" id="saveRecipientList" data-url="<?= e(url('/rundmail/liste-speichern')) ?>">
            <label for="saveListName">Diesen Empfängerkreis als Liste speichern</label>
            <div class="save-list-fields">
                <input type="text" id="saveListName" placeholder="Listenname, z. B. „Chor + Orga“" autocomplete="off">
                <button type="button" class="ghost-button"><?= icon('archive') ?><span>Speichern</span></button>
            </div>
            <p class="save-list-feedback" role="status" hidden></p>
        </div>
    </section>

    <section class="detail-card">
        <h2>Nachricht</h2>
        <div class="form-grid">
            <label>
                <span>Absenderadresse</span>
                <select name="sender_key" required>
                    <?php foreach ($identities as $identity): ?>
                        <option value="<?= e($identity['key']) ?>" <?= ($draft['sender_key'] ?? $defaultSenderKey) === $identity['key'] ? 'selected' : '' ?>>
                            <?= e($identity['name'] . ' <' . $identity['email'] . '>') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Antwort-an</span>
                <select name="reply_to_key" required>
                    <?php foreach ($replyToOptions as $replyTo): ?>
                        <option value="<?= e($replyTo['key']) ?>" <?= ($draft['reply_to_key'] ?? $defaultReplyToKey) === $replyTo['key'] ? 'selected' : '' ?>>
                            <?= e($replyTo['name'] . ' <' . $replyTo['email'] . '>') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="full-width">
                <span>Betreff-Präfix</span>
                <select name="subject_prefix" id="subjectPrefixField" required>
                    <?php foreach ($subjectPrefixOptions as $prefix): ?>
                        <option value="<?= e($prefix) ?>" <?= $activePrefix === $prefix ? 'selected' : '' ?>><?= e($prefix) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="field-hint">Weitere Präfixe in den Mail-Einstellungen. Das erste ist der Standard.</small>
            </label>
            <label class="full-width">
                <span>Betreff</span>
                <input type="text" name="subject" id="subjectField" value="<?= e($draft['subject'] ?? '') ?>" required>
            </label>
            <label class="full-width">
                <span>Anrede</span>
                <select name="salutation_mode">
                    <option value="auto" <?= $activeSalutation === 'auto' ? 'selected' : '' ?>>Automatisch aus dem Kontakt</option>
                    <option value="hallo" <?= $activeSalutation === 'hallo' ? 'selected' : '' ?>>Immer Hallo</option>
                    <option value="liebe" <?= $activeSalutation === 'liebe' ? 'selected' : '' ?>>Immer Liebe</option>
                    <option value="lieber" <?= $activeSalutation === 'lieber' ? 'selected' : '' ?>>Immer Lieber</option>
                </select>
                <small class="field-hint">Bei Automatik wird aus <code>m/w</code> automatisch <code>Lieber</code>/<code>Liebe</code>, sonst <code>Hallo</code>.</small>
            </label>
            <div class="mail-preview-block full-width">
                <strong>Betreff-Vorschau</strong>
                <div class="mail-footer-preview" id="subjectPreview"><?= e($subjectPreview) ?></div>
            </div>
            <label class="full-width">
                <span>Nachricht</span>
                <textarea name="message" rows="10" required><?= e($draft['message'] ?? "{Anrede} {Vorname},\n\n") ?></textarea>
                <small class="field-hint">Wird pro Person personalisiert und einzeln versendet.<?= $eventId !== null ? ' <code>{Abstimmungslink}</code> wird je Person durch den persönlichen Abstimmungs-Link ersetzt.' : '' ?></small>
            </label>
            <div class="mail-preview-block full-width">
                <strong>Automatisch ergänzter Mail-Fuß</strong>
                <div class="mail-footer-preview"><?= e($mailFooter) ?></div>
                <?php if (can('settings.manage')): ?>
                    <div class="card-actions">
                        <a class="ghost-button" href="<?= e(url('/settings/mail-footer')) ?>"><?= icon('sliders') ?><span>Mail-Fuß anpassen</span></a>
                    </div>
                <?php endif; ?>
            </div>
            <label class="full-width">
                <span>Dateianhänge</span>
                <input type="file" name="attachments[]" multiple>
                <small class="field-hint">Gesamtlimit aktuell 10 MB.</small>
            </label>
        </div>
    </section>

    <div class="detail-save-bar message-send-bar" data-message-bar>
        <span class="message-send-count"><strong data-recipient-count>…</strong> Empfänger</span>
        <div class="detail-save-actions">
            <button type="submit" class="ghost-button" formaction="<?= e(url('/mail/test')) ?>"><?= icon('mail') ?><span>Testmail an mich</span></button>
            <button type="submit"><?= icon('message-send') ?><span>Versand starten</span></button>
        </div>
    </div>
</form>

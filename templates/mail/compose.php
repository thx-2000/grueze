<?php $draft = $_SESSION['mail_draft'] ?? []; ?>
<?php
$defaultSenderKey = config('mail.default_sender_key', $identities[0]['key'] ?? '');
$defaultReplyToKey = config('mail.default_reply_to_key', $replyToOptions[0]['key'] ?? $defaultSenderKey);
$activeSubjectPrefix = $draft['subject_prefix'] ?? $defaultSubjectPrefix;
$activeSalutationMode = $draft['salutation_mode'] ?? ($defaultSalutationMode ?? 'auto');
$subjectPreview = trim(($activeSubjectPrefix ? $activeSubjectPrefix . ' ' : '') . ($draft['subject'] ?? 'Dein Betreff'));
$memberContactMode = (bool) ($memberContactMode ?? false);
?>
<section class="hero-card">
    <div class="hero-row">
        <div>
            <p class="eyebrow"><?= $memberContactMode ? 'Kontaktaufnahme' : 'Mailing' ?></p>
            <h2><?= $memberContactMode ? 'Einzelkontakt über das System' : 'Personalisierte E-Mail verfassen' ?></h2>
            <p class="muted"><?= count($contacts) ?> ausgewählte Kontakte. Platzhalter: <code>{Anrede}</code>, <code>{Vorname}</code> und <code>{Nachname}</code>.</p>
        </div>
        <div class="selection-status"><?= count($contacts) ?> Empfänger ausgewählt</div>
    </div>
</section>

<section class="panel">
    <form id="mailComposeForm" method="post" action="<?= e(url('/mail/start')) ?>" enctype="multipart/form-data" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <?php foreach ($contacts as $contact): ?>
            <input type="hidden" name="contact_ids[]" value="<?= e((string) $contact['id']) ?>">
        <?php endforeach; ?>

        <div class="form-grid">
            <?php if ($memberContactMode): ?>
                <input type="hidden" name="sender_key" value="<?= e($draft['sender_key'] ?? $defaultSenderKey) ?>">
                <input type="hidden" name="reply_to_key" value="<?= e($draft['reply_to_key'] ?? ($replyToOptions[0]['key'] ?? $defaultReplyToKey)) ?>">
                <input type="hidden" name="subject_prefix" id="subjectPrefixField" value="<?= e($activeSubjectPrefix) ?>">
                <div class="subsection-card full-width workflow-card">
                    <div class="workflow-card-head">
                        <span class="workflow-icon"><?= icon('mail-open') ?></span>
                        <div>
                            <strong>So läuft diese Kontaktaufnahme ab</strong>
                            <p class="detail-hint">Du schreibst genau einer Person. Die eigentliche Zieladresse bleibt verborgen, Antworten kommen direkt zu deiner Login-Mailadresse zurück.</p>
                        </div>
                    </div>
                    <ol class="workflow-list">
                        <li>Formuliere deine Nachricht wie gewohnt.</li>
                        <li>Das System verschickt sie technisch über den Mailer, aber Antworten landen bei dir.</li>
                        <li>Falls dir zu einem Kontakt noch eine fehlende Mailadresse bekannt ist, schicke sie bitte zusätzlich an <a href="mailto:kontakt@example.org">kontakt@example.org</a>.</li>
                    </ol>
                </div>
                <div class="subsection-card full-width">
                    <strong>Absender und Antwortweg</strong>
                    <div class="mail-footer-preview">Die Nachricht wird über den Mailer verschickt. Antworten gehen automatisch direkt an deine eigene Login-Mailadresse.</div>
                </div>
            <?php else: ?>
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
                            <option value="<?= e($prefix) ?>" <?= $activeSubjectPrefix === $prefix ? 'selected' : '' ?>><?= e($prefix) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="field-hint">Weitere Präfixe kannst du in den Mail-Einstellungen pflegen. Das erste ist der Standard.</small>
                </label>
            <?php endif; ?>
            <label class="full-width">
                <span>Betreff</span>
                <input type="text" name="subject" id="subjectField" value="<?= e($draft['subject'] ?? '') ?>" required>
            </label>
            <label class="full-width">
                <span>Anrede</span>
                <select name="salutation_mode">
                    <option value="auto" <?= $activeSalutationMode === 'auto' ? 'selected' : '' ?>>Automatisch aus dem Kontakt</option>
                    <option value="hallo" <?= $activeSalutationMode === 'hallo' ? 'selected' : '' ?>>Immer Hallo</option>
                    <option value="liebe" <?= $activeSalutationMode === 'liebe' ? 'selected' : '' ?>>Immer Liebe</option>
                    <option value="lieber" <?= $activeSalutationMode === 'lieber' ? 'selected' : '' ?>>Immer Lieber</option>
                </select>
                <small class="field-hint">Bei Automatik wird aus dem Kontaktfeld <code>m/w</code> automatisch <code>Lieber</code> oder <code>Liebe</code>. Ohne Angabe fällt die Anrede auf <code>Hallo</code> zurück.</small>
            </label>
            <div class="subsection-card full-width">
                <strong>Betreff-Vorschau</strong>
                <div class="mail-footer-preview" id="subjectPreview"><?= e($subjectPreview) ?></div>
            </div>
            <label class="full-width">
                <span>Nachricht</span>
                <textarea name="message" rows="10" required><?= e($draft['message'] ?? "{Anrede} {Vorname},\n\n") ?></textarea>
                <small class="field-hint">Die Nachricht wird pro Person personalisiert und einzeln versendet.</small>
            </label>
            <div class="subsection-card full-width">
                <strong>Automatisch ergänzter Mail-Fuß</strong>
                <div class="mail-footer-preview"><?= e($mailFooter) ?></div>
                <small class="field-hint">Dieser Abschnitt wird bei Testmails und beim Versand automatisch unter deine Nachricht gesetzt.</small>
                <?php if (can('settings.manage')): ?>
                    <div class="card-actions">
                        <a class="ghost-button compact-action" href="<?= e(url('/settings/mail-footer')) ?>"><?= icon('sliders') ?><span>Mail-Fuß anpassen</span></a>
                    </div>
                <?php endif; ?>
            </div>
            <label class="full-width">
                <span>Dateianhänge</span>
                <input type="file" name="attachments[]" multiple>
                <small class="field-hint">Gesamtlimit aktuell 10 MB.</small>
            </label>
        </div>

        <div class="form-actions">
            <button type="submit">Versand starten</button>
            <button type="submit" formaction="<?= e(url('/mail/test')) ?>">Testmail an mich senden</button>
            <a class="ghost-button" href="<?= e(url('/')) ?>">Zurück</a>
        </div>
    </form>

    <div id="mailProgress" class="progress-panel" hidden>
        <div class="progress-track"><div id="mailProgressBar" class="progress-bar"></div></div>
        <p id="mailProgressText">0 von 0 gesendet</p>
        <div id="mailResults" class="results-list"></div>
    </div>
</section>

<section class="panel">
    <div class="panel-head">
        <div>
            <h3>Empfängerliste</h3>
            <p class="muted"><?= $memberContactMode ? 'Die Zieladresse bleibt in diesem Modus bewusst verborgen.' : 'Verwendet wird jeweils die erste hinterlegte E-Mail-Adresse.' ?></p>
        </div>
    </div>
    <div class="recipient-grid">
        <?php foreach ($contacts as $contact): ?>
            <article class="recipient-chip">
                <strong><?= e($contact['vorname'] . ' ' . $contact['nachname']) ?></strong>
                <span><?= e($memberContactMode ? 'Adresse verborgen' : ($contact['emails'][0]['email'] ?? 'Keine Adresse')) ?></span>
            </article>
        <?php endforeach; ?>
    </div>
</section>

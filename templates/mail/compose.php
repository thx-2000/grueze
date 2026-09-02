<?php $draft = (array) ($draft ?? $_SESSION['mail_draft'] ?? []); ?>
<?php
$branding = app_branding();
$appName = (string) ($branding['branding_app_name'] ?? 'Adress-Zentrale');
$supportEmail = trim((string) ($branding['branding_support_email'] ?? ''));
$defaultSenderKey = $defaultSenderKey ?? ($identities[0]['key'] ?? '');
$defaultReplyToKey = $defaultReplyToKey ?? ($replyToOptions[0]['key'] ?? $defaultSenderKey);
$activeSubjectPrefix = $draft['subject_prefix'] ?? $defaultSubjectPrefix;
$activeSalutationMode = $draft['salutation_mode'] ?? ($defaultSalutationMode ?? 'auto');
$subjectPreview = trim(($activeSubjectPrefix ? $activeSubjectPrefix . ' ' : '') . ($draft['subject'] ?? 'Dein Betreff'));
$memberContactMode = (bool) ($memberContactMode ?? false);
?>
<header class="page-head page-head--split">
    <div>
        <p class="eyebrow"><?= $memberContactMode ? 'Kontaktaufnahme' : 'Mailing' ?></p>
        <h1><?= $memberContactMode ? 'Einzelkontakt über ' . e($appName) : 'Personalisierte E-Mail verfassen' ?></h1>
        <p class="muted"><?= count($contacts) ?> ausgewählte Kontakte. Platzhalter: <code>{Anrede}</code>, <code>{Vorname}</code> und <code>{Nachname}</code>.</p>
    </div>
    <span class="selection-status"><?= count($contacts) ?> Empfänger</span>
</header>

<?php if (!$memberContactMode && count($contacts) > 1): ?>
    <section class="panel">
        <div class="save-list-row" id="saveRecipientList" data-url="<?= e(url('/rundmail/liste-speichern')) ?>">
            <label for="saveListName">Diese Empfänger als Liste speichern</label>
            <div class="save-list-fields">
                <input type="text" id="saveListName" placeholder="Listenname, z. B. „Chor + Orga"" autocomplete="off">
                <button type="button" class="ghost-button compact-action"><?= icon('archive') ?><span>Speichern</span></button>
            </div>
            <p class="save-list-feedback" role="status" hidden></p>
        </div>
    </section>
<?php endif; ?>

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
                        <span class="workflow-icon"><?= icon('message-send') ?></span>
                        <div>
                            <strong>So läuft diese Kontaktaufnahme ab</strong>
                            <p class="detail-hint">Du schreibst genau einer Person. Die eigentliche Zieladresse bleibt verborgen, Antworten kommen direkt zu deiner Login-Mailadresse zurück.</p>
                        </div>
                    </div>
                    <ol class="workflow-list">
                        <li>Formuliere deine Nachricht wie gewohnt.</li>
                        <li><?= e($appName) ?> verschickt sie technisch über den Mailer, aber Antworten landen bei dir.</li>
                        <?php if ($supportEmail !== ''): ?>
                            <li>Falls dir zu einem Kontakt noch eine fehlende Mailadresse bekannt ist, schicke sie bitte zusätzlich an <a href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a>.</li>
                        <?php endif; ?>
                    </ol>
                </div>
                <div class="subsection-card full-width">
                    <strong>Absender und Antwortweg</strong>
                    <div class="mail-footer-preview">Die Nachricht wird über den <?= e($appName) ?>-Mailer verschickt. Antworten gehen automatisch direkt an deine eigene Login-Mailadresse.</div>
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
            <a class="ghost-button" href="<?= e(url('/kontakte')) ?>">Zurück</a>
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
                <span class="is-guarded"><?= e($memberContactMode ? 'Adresse verborgen' : ($contact['emails'][0]['email'] ?? 'Keine Adresse')) ?></span>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php if (!$memberContactMode && count($contacts) > 1): ?>
<script nonce="<?= e(csp_nonce()) ?>">
(function () {
    const box = document.getElementById('saveRecipientList');
    if (!box) return;
    const nameInput = box.querySelector('#saveListName');
    const button = box.querySelector('button');
    const feedback = box.querySelector('.save-list-feedback');
    const form = document.getElementById('mailComposeForm');

    const show = (msg, ok) => {
        feedback.textContent = msg;
        feedback.hidden = false;
        feedback.classList.toggle('is-error', !ok);
    };

    button.addEventListener('click', async () => {
        const name = nameInput.value.trim();
        if (name === '') { nameInput.focus(); return; }
        const ids = [...form.querySelectorAll('input[name="contact_ids[]"]')].map((i) => i.value);
        const body = new URLSearchParams();
        body.set('_csrf', (window.APP && window.APP.csrfToken) || form.querySelector('input[name="_csrf"]').value);
        body.set('name', name);
        ids.forEach((id) => body.append('contact_ids[]', id));

        button.disabled = true;
        try {
            const res = await fetch(box.dataset.url, {
                method: 'POST',
                headers: { 'X-Requested-With': 'fetch' },
                body,
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.ok) {
                show('Liste „' + data.name + '" gespeichert (' + data.count + ' Kontakte).', true);
                nameInput.value = '';
            } else {
                show(data.error || 'Speichern fehlgeschlagen.', false);
            }
        } catch (e) {
            show('Speichern fehlgeschlagen (Netzwerk).', false);
        } finally {
            button.disabled = false;
        }
    });
})();
</script>
<?php endif; ?>

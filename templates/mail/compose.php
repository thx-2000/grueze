<?php $draft = $_SESSION['mail_draft'] ?? []; ?>
<section class="hero-card">
    <p class="eyebrow">Mailing</p>
    <h2>Personalisierte E-Mail verfassen</h2>
    <p class="muted"><?= count($contacts) ?> ausgewählte Kontakte. Platzhalter: <code>{Vorname}</code> und <code>{Nachname}</code>.</p>
</section>

<section class="panel">
    <form id="mailComposeForm" method="post" action="<?= e(url('/mail/start')) ?>" enctype="multipart/form-data" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <?php foreach ($contacts as $contact): ?>
            <input type="hidden" name="contact_ids[]" value="<?= e((string) $contact['id']) ?>">
        <?php endforeach; ?>

        <div class="form-grid">
            <label>
                <span>Absenderadresse</span>
                <select name="sender_key" required>
                    <?php foreach ($identities as $identity): ?>
                        <option value="<?= e($identity['key']) ?>" <?= ($draft['sender_key'] ?? $identities[0]['key']) === $identity['key'] ? 'selected' : '' ?>>
                            <?= e($identity['name'] . ' <' . $identity['email'] . '>') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Antwort-an</span>
                <select name="reply_to_key" required>
                    <?php foreach ($identities as $identity): ?>
                        <option value="<?= e($identity['key']) ?>" <?= ($draft['reply_to_key'] ?? $identities[0]['key']) === $identity['key'] ? 'selected' : '' ?>>
                            <?= e($identity['name'] . ' <' . $identity['email'] . '>') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="full-width">
                <span>Betreff</span>
                <input type="text" name="subject" value="<?= e($draft['subject'] ?? '') ?>" required>
            </label>
            <label class="full-width">
                <span>Nachricht</span>
                <textarea name="message" rows="10" required><?= e($draft['message'] ?? "Hallo {Vorname},\n\n") ?></textarea>
            </label>
            <label class="full-width">
                <span>Dateianhänge</span>
                <input type="file" name="attachments[]" multiple>
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
    <h3>Empfängerliste</h3>
    <ul class="mini-list">
        <?php foreach ($contacts as $contact): ?>
            <li><?= e($contact['vorname'] . ' ' . $contact['nachname']) ?>: <?= e($contact['emails'][0]['email'] ?? 'Keine Adresse') ?></li>
        <?php endforeach; ?>
    </ul>
</section>


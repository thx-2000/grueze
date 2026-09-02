<?php
$firstName = trim((string) ($accountUser['name'] ?? ''));
$firstName = $firstName !== '' ? explode(' ', $firstName)[0] : '';

$ownContact = $ownContact ?? null;
$canEditOwn = !empty($canEditOwn);
$phoneLabels = $phoneLabels ?? [];

$oldInput = $_SESSION['_old'] ?? [];
$hasOld = $oldInput !== [];

// Formularwerte: nach einem Validierungsfehler die Session-Altwerte, sonst der
// verknüpfte Kontakt.
$field = static function (string $key, $fallback = '') use ($hasOld, $oldInput, $ownContact) {
    if ($hasOld) {
        return (string) ($oldInput[$key] ?? '');
    }

    return (string) ($ownContact[$key] ?? $fallback);
};

$emails = $hasOld ? (array) ($oldInput['emails'] ?? []) : ($ownContact['emails'] ?? []);
if ($emails === []) {
    $emails = [['email' => '', 'label' => '']];
}
$phones = $hasOld ? (array) ($oldInput['phones'] ?? []) : ($ownContact['phones'] ?? []);
if ($phones === []) {
    $phones = [['phone' => '', 'label' => $phoneLabels[0] ?? 'Mobil']];
}
foreach ($emails as $i => $entry) {
    $emails[$i]['email'] = preg_replace('/^\s*mailto:\s*/i', '', (string) ($entry['email'] ?? ''));
}
foreach ($phones as $i => $entry) {
    $phones[$i]['phone'] = preg_replace('/^\s*tel:\s*/i', '', (string) ($entry['phone'] ?? ''));
}
?>
<header class="contact-detail-head">
    <p class="eyebrow">Mein Eintrag</p>
    <h1><?= $firstName !== '' ? 'Hallo, ' . e($firstName) : 'Mein Eintrag' ?></h1>
    <p class="muted">Deine eigenen Angaben, offene Abstimmungen und dein Zugang – an einem Ort.</p>
</header>

<?php if ($ownContact !== null && $canEditOwn): ?>
    <form method="post" action="<?= e(url('/mein-eintrag')) ?>" class="contact-detail-form" data-detail-form>
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <section class="detail-card">
            <h2>Das haben wir zu dir</h2>
            <p class="field-hint">Ändere, was nicht mehr stimmt. Mit <span aria-hidden="true">*</span> markierte Felder sind Pflicht.</p>
            <div class="form-grid">
                <label><span>Vorname <span class="required-marker" aria-hidden="true">*</span></span><input type="text" name="vorname" value="<?= e($field('vorname')) ?>" required></label>
                <label><span>Nachname <span class="required-marker" aria-hidden="true">*</span></span><input type="text" name="nachname" value="<?= e($field('nachname')) ?>" required></label>
                <label><span>Geburtsname</span><input type="text" name="geburtsname" value="<?= e($field('geburtsname')) ?>"></label>
                <label>
                    <span>Anrede</span>
                    <select name="geschlecht">
                        <?php $g = $field('geschlecht'); ?>
                        <option value="">Nicht gesetzt</option>
                        <option value="m" <?= $g === 'm' ? 'selected' : '' ?>>Männlich (Lieber)</option>
                        <option value="w" <?= $g === 'w' ? 'selected' : '' ?>>Weiblich (Liebe)</option>
                    </select>
                </label>
                <label><span>Geburtstag</span><input type="date" name="geburtstag" value="<?= e($field('geburtstag')) ?>"></label>
            </div>
        </section>

        <section class="detail-card">
            <h2>Adresse</h2>
            <div class="form-grid">
                <label class="full-width"><span>Straße</span><input type="text" name="strasse" value="<?= e($field('strasse')) ?>"></label>
                <label><span>PLZ</span><input type="text" name="plz" value="<?= e($field('plz')) ?>"></label>
                <label><span>Ort</span><input type="text" name="ort" value="<?= e($field('ort')) ?>"></label>
                <label><span>Land</span><input type="text" name="land" value="<?= e($field('land', config('defaults.country', 'Deutschland'))) ?>"></label>
            </div>
        </section>

        <section class="detail-card">
            <h2>Kontaktwege</h2>
            <div class="repeater-block">
                <div class="section-head">
                    <h3>E-Mail-Adressen</h3>
                    <button type="button" class="ghost-button icon-button" data-add-row="emails" aria-label="Weitere E-Mail-Adresse hinzufügen"><?= icon('plus') ?><span class="visually-hidden">Weitere E-Mail-Adresse hinzufügen</span></button>
                </div>
                <div id="emailsRepeater">
                    <?php foreach ($emails as $index => $email): ?>
                        <div class="repeater-row">
                            <input type="text" name="emails[<?= e((string) $index) ?>][label]" value="<?= e($email['label'] ?? '') ?>" placeholder="Label, z. B. privat" aria-label="Label für E-Mail-Adresse <?= e((string) ($index + 1)) ?>">
                            <input type="text" inputmode="email" name="emails[<?= e((string) $index) ?>][email]" value="<?= e($email['email'] ?? '') ?>" placeholder="name@example.com" aria-label="E-Mail-Adresse <?= e((string) ($index + 1)) ?>">
                            <button type="button" class="danger-button icon-button" data-remove-row aria-label="E-Mail-Adresse entfernen"><?= icon('trash') ?><span class="visually-hidden">E-Mail-Adresse entfernen</span></button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="repeater-block">
                <div class="section-head">
                    <h3>Telefonnummern</h3>
                    <button type="button" class="ghost-button icon-button" data-add-row="phones" aria-label="Weitere Telefonnummer hinzufügen"><?= icon('plus') ?><span class="visually-hidden">Weitere Telefonnummer hinzufügen</span></button>
                </div>
                <div id="phonesRepeater">
                    <?php foreach ($phones as $index => $phone): ?>
                        <div class="repeater-row">
                            <select name="phones[<?= e((string) $index) ?>][label]" aria-label="Art der Telefonnummer <?= e((string) ($index + 1)) ?>">
                                <?php foreach ($phoneLabels as $label): ?>
                                    <option value="<?= e($label) ?>" <?= ($phone['label'] ?? '') === $label ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="phones[<?= e((string) $index) ?>][phone]" value="<?= e($phone['phone'] ?? '') ?>" placeholder="+49 …" aria-label="Telefonnummer <?= e((string) ($index + 1)) ?>">
                            <button type="button" class="danger-button icon-button" data-remove-row aria-label="Telefonnummer entfernen"><?= icon('trash') ?><span class="visually-hidden">Telefonnummer entfernen</span></button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <p class="detail-notes-hint"><?= icon('lock') ?><span>Interne Notizen der Verwaltung sind hier bewusst nicht sichtbar – auch nicht für dich.</span></p>
        </section>

        <div class="detail-save-bar" hidden data-save-bar>
            <span class="detail-save-hint">Ungespeicherte Änderungen.</span>
            <div class="detail-save-actions">
                <button type="button" class="ghost-button" data-detail-reset>Verwerfen</button>
                <button type="submit">Änderungen speichern</button>
            </div>
        </div>
    </form>

    <template id="emailRowTemplate">
        <div class="repeater-row">
            <input type="text" data-name="label" placeholder="Label, z. B. privat" aria-label="Label für weitere E-Mail-Adresse">
            <input type="text" inputmode="email" data-name="email" placeholder="name@example.com" aria-label="Weitere E-Mail-Adresse">
            <button type="button" class="danger-button icon-button" data-remove-row aria-label="E-Mail-Adresse entfernen"><?= icon('trash') ?><span class="visually-hidden">E-Mail-Adresse entfernen</span></button>
        </div>
    </template>
    <template id="phoneRowTemplate">
        <div class="repeater-row">
            <select data-name="label" aria-label="Art der weiteren Telefonnummer">
                <?php foreach ($phoneLabels as $label): ?>
                    <option value="<?= e($label) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" data-name="phone" placeholder="+49 …" aria-label="Weitere Telefonnummer">
            <button type="button" class="danger-button icon-button" data-remove-row aria-label="Telefonnummer entfernen"><?= icon('trash') ?><span class="visually-hidden">Telefonnummer entfernen</span></button>
        </div>
    </template>

<?php elseif ($ownContact !== null): ?>
    <section class="detail-card">
        <h2>Das haben wir zu dir</h2>
        <dl class="own-contact-list is-guarded">
            <div><dt>Name</dt><dd><?= e(trim(($ownContact['vorname'] ?? '') . ' ' . ($ownContact['nachname'] ?? ''))) ?></dd></div>
            <?php if (trim((string) ($ownContact['strasse'] ?? '') . ($ownContact['plz'] ?? '') . ($ownContact['ort'] ?? '')) !== ''): ?>
                <div><dt>Adresse</dt><dd>
                    <?= e(trim((string) ($ownContact['strasse'] ?? ''))) ?>
                    <?php if (trim((string) ($ownContact['plz'] ?? '') . ($ownContact['ort'] ?? '')) !== ''): ?>
                        <br><?= e(trim(($ownContact['plz'] ?? '') . ' ' . ($ownContact['ort'] ?? ''))) ?>
                    <?php endif; ?>
                </dd></div>
            <?php endif; ?>
            <?php if (($ownContact['emails'] ?? []) !== []): ?>
                <div><dt>E-Mail</dt><dd><?php foreach ($ownContact['emails'] as $mail): ?><?= e($mail['email']) ?><br><?php endforeach; ?></dd></div>
            <?php endif; ?>
            <?php if (($ownContact['phones'] ?? []) !== []): ?>
                <div><dt>Telefon</dt><dd><?php foreach ($ownContact['phones'] as $tel): ?><?= e($tel['phone']) ?><br><?php endforeach; ?></dd></div>
            <?php endif; ?>
        </dl>
        <p class="field-hint">Änderungen an deinen Daten macht zurzeit das Orga-Team – <a href="<?= e(url('/orga-team')) ?>">kurz Bescheid geben</a>.</p>
    </section>

<?php else: ?>
    <section class="detail-card">
        <h2>Dein Eintrag</h2>
        <p class="muted">Mit deinem Zugang ist noch kein Eintrag im Adressbuch verknüpft. Sobald das Orga-Team das verbindet, kannst du deine Daten hier selbst pflegen.</p>
        <p><a class="button-link" href="<?= e(url('/orga-team')) ?>"><?= icon('mail') ?><span>Orga-Team schreiben</span></a></p>
    </section>
<?php endif; ?>

<?php if (!empty($openEvents)): ?>
    <section class="panel" id="abstimmungen">
        <div class="panel-head">
            <div>
                <h3>Offene Abstimmungen</h3>
                <p class="muted">Termine, bei denen deine Rückmeldung fehlt oder noch geändert werden kann.</p>
            </div>
        </div>
        <ul class="account-events">
            <?php foreach ($openEvents as $ev): ?>
                <li>
                    <a href="<?= e(url('/abstimmen?token=' . $ev['token'])) ?>">
                        <span class="account-events-title"><?= e($ev['title']) ?></span>
                        <span class="account-events-meta">
                            <?php if ((int) $ev['has_answered'] === 1): ?>
                                <span class="status-chip is-ok">geantwortet</span> – ändern
                            <?php else: ?>
                                <span class="status-chip is-warn">offen</span> – jetzt abstimmen
                            <?php endif; ?>
                        </span>
                    </a>
                    <?= icon('chevron-right') ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<section class="detail-card">
    <h2>Zugang &amp; Sicherheit</h2>
    <div class="subsection-card account-overview-card">
        <div>
            <strong>Login-Adresse</strong>
            <p class="detail-hint"><?= e((string) ($accountUser['email'] ?? '')) ?></p>
        </div>
        <div>
            <strong>Rolle</strong>
            <p class="detail-hint role-chip-label"><?= e(role_label((string) ($accountUser['role_name'] ?? ''))) ?></p>
        </div>
    </div>
    <div class="account-quicknav">
        <a class="account-quicknav-item" href="#password">
            <span class="account-quicknav-icon"><?= icon('key') ?></span>
            <span>
                <strong>Passwort</strong>
                <small>Eigenes Kennwort ändern</small>
            </span>
        </a>
        <a class="account-quicknav-item" href="#passkeys">
            <span class="account-quicknav-icon"><?= icon('passkey') ?></span>
            <span>
                <strong>Passkeys</strong>
                <small>Geräte verwalten</small>
            </span>
        </a>
        <a class="account-quicknav-item" href="<?= e(url('/orga-team')) ?>">
            <span class="account-quicknav-icon"><?= icon('mail') ?></span>
            <span>
                <strong>Orga-Team schreiben</strong>
                <small>Frage oder Hinweis ans Team</small>
            </span>
        </a>
    </div>
</section>

<section class="panel compact-editor-shell" id="password">
    <div class="panel-head">
        <div>
            <h3>Passwort</h3>
            <p class="muted">Für Änderungen am eigenen Passwort brauchst du zur Sicherheit zuerst dein aktuelles Kennwort.</p>
        </div>
    </div>
    <div class="subsection-card account-section-card">
        <form method="post" action="<?= e(url('/account/password')) ?>" class="form-grid account-settings-form">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <label>
                <span>Aktuelles Passwort</span>
                <input type="password" name="current_password" autocomplete="current-password" required>
            </label>
            <label>
                <span>Neues Passwort</span>
                <input type="password" name="new_password" minlength="12" autocomplete="new-password" required>
            </label>
            <label>
                <span>Neues Passwort wiederholen</span>
                <input type="password" name="new_password_repeat" minlength="12" autocomplete="new-password" required>
            </label>
            <div class="form-actions">
                <button type="submit"><?= icon('key') ?><span>Passwort aktualisieren</span></button>
            </div>
        </form>
    </div>
</section>

<section class="panel compact-editor-shell" id="passkeys">
    <div class="panel-head">
        <div>
            <h3>Passkeys</h3>
            <p class="muted">Passkeys sind die schnelle Anmeldung per Face ID, Touch ID, Windows Hello oder Sicherheitsschlüssel. Du kannst mehrere Geräte hinterlegen und später wieder entfernen.</p>
        </div>
    </div>

    <?php if (empty($passkeysAvailable)): ?>
        <div class="flash flash-error">Für Passkeys fehlt noch die neue Datenbank-Tabelle. Sobald die Migration eingespielt ist, kannst du sie hier verwalten.</div>
    <?php else: ?>
        <div class="subsection-card">
            <strong>Neuen Passkey hinzufügen</strong>
            <p class="detail-hint">Vergib optional einen Namen wie „MacBook“, „iPhone“ oder „YubiKey“, damit du ihn später leichter wiedererkennst.</p>
            <div class="passkey-register-card">
                <label>
                    <span>Bezeichnung</span>
                    <input id="passkeyLabel" type="text" placeholder="z. B. MacBook Air">
                </label>
                <button
                    type="button"
                    id="registerPasskeyButton"
                    data-passkey-register
                    data-options-url="<?= e(url('/passkeys/register/options')) ?>"
                    data-register-url="<?= e(url('/passkeys/register')) ?>"
                >
                    <?= icon('passkey') ?><span>Passkey hinzufügen</span>
                </button>
            </div>
        </div>

        <div class="subsection-card">
            <strong>Gespeicherte Passkeys</strong>
            <p class="detail-hint">Beim Entfernen eines Geräts ist danach wieder Passwort oder ein anderer Passkey nötig.</p>

            <?php if ($passkeys === []): ?>
                <p class="muted account-empty-state">Für dieses Konto ist noch kein Passkey gespeichert.</p>
            <?php else: ?>
                <div class="passkey-list">
                    <?php foreach ($passkeys as $passkey): ?>
                        <article class="passkey-item">
                            <div class="passkey-item-copy">
                                <strong><?= e((string) ($passkey['label'] ?: 'Unbenannter Passkey')) ?></strong>
                                <p class="muted">
                                    Angelegt: <?= e(format_datetime((string) $passkey['created_at'])) ?>
                                    <?php if (!empty($passkey['last_used_at'])): ?>
                                        <br>Zuletzt genutzt: <?= e(format_datetime((string) $passkey['last_used_at'])) ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <form method="post" action="<?= e(url('/passkeys/delete')) ?>" onsubmit="return confirm('Diesen Passkey wirklich entfernen?');">
                                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="passkey_id" value="<?= e((string) $passkey['id']) ?>">
                                <button type="submit" class="danger-button compact-action"><?= icon('trash') ?><span>Passkey entfernen</span></button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

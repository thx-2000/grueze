<header class="page-head">
    <p class="eyebrow">Einstellungen</p>
    <h1>Mail-Einstellungen</h1>
    <p class="muted">Hier pflegst du Mail-Fuß, Betreff-Präfixe und als Admin auch die technischen Versanddaten.</p>
</header>

<section class="panel">
    <form method="post" action="<?= e(url('/settings/mail-footer')) ?>" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <div class="form-grid settings-grid-2">
            <div class="stack">
                <label>
                    <span>Mail-Fuß</span>
                    <textarea name="mail_footer" rows="10" required><?= e($mailFooter) ?></textarea>
                    <small class="field-hint">Im Mail-Kompositionsfenster wird dieser Abschnitt als Vorschau angezeigt, aber getrennt von der eigentlichen Nachricht gepflegt. Platzhalter <code>{name}</code> (Instanzname) und <code>{kurzname}</code> werden beim Versand ersetzt.</small>
                </label>

                <label>
                    <span>Betreff-Präfixe</span>
                    <textarea name="subject_prefixes" rows="4" required><?= e($subjectPrefixes) ?></textarea>
                    <small class="field-hint">Ein Präfix pro Zeile. Die erste Zeile ist der Standard, zum Beispiel <code><?= e($defaultSubjectPrefix) ?></code>. Platzhalter <code>{kurzname}</code> / <code>{name}</code> werden beim Versand ersetzt – z. B. <code>[{kurzname}]</code>.</small>
                </label>
            </div>

            <?php if (can('users.manage')): ?>
                <div class="stack">
                    <div class="subsection-card">
                        <strong>Mailserver & Versand</strong>
                        <div class="form-grid">
                            <label>
                                <span>Absendername</span>
                                <input type="text" name="mail_identity_name" value="<?= e((string) ($mailSettings['mail_identity_name'] ?? '')) ?>" required>
                            </label>
                            <label>
                                <span>Absender-E-Mail</span>
                                <input type="email" name="mail_identity_email" value="<?= e((string) ($mailSettings['mail_identity_email'] ?? '')) ?>" required>
                            </label>
                            <label>
                                <span>Antwort-an Name</span>
                                <input type="text" name="mail_reply_to_name" value="<?= e((string) ($mailSettings['mail_reply_to_name'] ?? '')) ?>" required>
                            </label>
                            <label>
                                <span>Antwort-an E-Mail</span>
                                <input type="email" name="mail_reply_to_email" value="<?= e((string) ($mailSettings['mail_reply_to_email'] ?? '')) ?>" required>
                            </label>
                            <label class="full-width">
                                <span>BCC-Kopie jeder Mail</span>
                                <input type="email" name="mail_bcc_email" value="<?= e((string) ($mailSettings['mail_bcc_email'] ?? '')) ?>" placeholder="optional@example.org">
                                <small class="field-hint">Leer lassen, wenn keine automatische BCC-Kopie verschickt werden soll.</small>
                            </label>
                            <label class="full-width">
                                <span>Feste Orga-Team-Adresse</span>
                                <input type="email" name="mail_orga_address" value="<?= e((string) ($mailSettings['mail_orga_address'] ?? '')) ?>" placeholder="orga@example.org">
                                <small class="field-hint">Der „Orga-Team schreiben"-Knopf geht an diese Adresse. Leer lassen, dann geht er an alle aktiven Nutzer:innen mit der Rolle „Orga-Team" (siehe Berechtigungen).</small>
                            </label>
                        </div>
                    </div>

                    <div class="subsection-card">
                        <strong>SMTP</strong>
                        <div class="form-grid">
                            <input type="hidden" name="mail_identity_key" value="<?= e((string) ($mailSettings['mail_identity_key'] ?? 'orga')) ?>">
                            <input type="hidden" name="mail_reply_to_key" value="<?= e((string) ($mailSettings['mail_reply_to_key'] ?? 'orga_reply')) ?>">
                            <label>
                                <span>Server</span>
                                <input type="text" name="mail_smtp_host" value="<?= e((string) ($mailSettings['mail_smtp_host'] ?? '')) ?>" required>
                            </label>
                            <label>
                                <span>Port</span>
                                <input type="text" name="mail_smtp_port" value="<?= e((string) ($mailSettings['mail_smtp_port'] ?? '587')) ?>" required>
                            </label>
                            <label>
                                <span>Verschlüsselung</span>
                                <select name="mail_smtp_encryption">
                                    <?php foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'notls' => 'Keine'] as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= (($mailSettings['mail_smtp_encryption'] ?? 'tls') === $value) ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span>Benutzername</span>
                                <input type="text" name="mail_smtp_username" value="<?= e((string) ($mailSettings['mail_smtp_username'] ?? '')) ?>" required>
                            </label>
                            <label class="full-width">
                                <span>Passwort</span>
                                <input type="password" name="mail_smtp_password" value="" autocomplete="new-password" placeholder="unverändert lassen">
                                <small class="field-hint">Aus Sicherheitsgründen wird das gespeicherte Passwort nicht angezeigt. Leer lassen bedeutet: unverändert übernehmen.</small>
                            </label>
                        </div>
                    </div>

                    <div class="subsection-card">
                        <strong>IMAP Gesendet-Ordner</strong>
                        <div class="form-grid">
                            <label class="full-width checkbox-row">
                                <input type="checkbox" name="mail_imap_save_sent" value="1" <?= (($mailSettings['mail_imap_save_sent'] ?? '1') === '1') ? 'checked' : '' ?>>
                                <span>Kopie jeder gesendeten Mail zusätzlich im Gesendet-Ordner speichern</span>
                            </label>
                            <label>
                                <span>IMAP-Server</span>
                                <input type="text" name="mail_imap_host" value="<?= e((string) ($mailSettings['mail_imap_host'] ?? '')) ?>">
                            </label>
                            <label>
                                <span>IMAP-Port</span>
                                <input type="text" name="mail_imap_port" value="<?= e((string) ($mailSettings['mail_imap_port'] ?? '993')) ?>">
                            </label>
                            <label>
                                <span>Verschlüsselung</span>
                                <select name="mail_imap_encryption">
                                    <?php foreach (['ssl' => 'SSL', 'tls' => 'TLS', 'notls' => 'Keine'] as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= (($mailSettings['mail_imap_encryption'] ?? 'ssl') === $value) ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span>IMAP-Benutzername</span>
                                <input type="text" name="mail_imap_username" value="<?= e((string) ($mailSettings['mail_imap_username'] ?? '')) ?>">
                            </label>
                            <label>
                                <span>IMAP-Passwort</span>
                                <input type="password" name="mail_imap_password" value="" autocomplete="new-password" placeholder="unverändert lassen">
                                <small class="field-hint">Auch dieses Passwort bleibt im Backend verborgen. Leer lassen bedeutet: unverändert übernehmen.</small>
                            </label>
                            <label class="full-width">
                                <span>Mögliche Gesendet-Ordner</span>
                                <textarea name="mail_imap_sent_mailboxes" rows="4"><?= e((string) ($mailSettings['mail_imap_sent_mailboxes'] ?? '')) ?></textarea>
                                <small class="field-hint">Ein Ordner pro Zeile. Das System probiert sie der Reihe nach aus.</small>
                            </label>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="subsection-card">
            <strong>Aktuelle Vorschau</strong>
            <div class="mail-footer-preview"><?= e($mailFooter) ?></div>
        </div>

        <div class="subsection-card">
            <strong>Aktueller Standard-Präfix</strong>
            <div class="mail-footer-preview"><?= e($defaultSubjectPrefix) ?> Beispielbetreff</div>
        </div>

        <div class="subsection-card">
            <strong>Standardtext</strong>
            <div class="mail-footer-preview"><?= e($defaultMailFooter) ?></div>
        </div>

        <div class="form-actions">
            <button type="submit">Speichern</button>
            <button type="submit" class="ghost-button" name="use_default" value="1">Standardwerte einsetzen</button>
            <a class="ghost-button" href="<?= e(url('/verwaltung')) ?>">Zurück</a>
        </div>
    </form>
</section>

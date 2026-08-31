<?php
$totalRows = array_sum($rowCounts);
$tableLabels = [
    'roles' => 'Rollen',
    'categories' => 'Kategorien',
    'tags' => 'Tags',
    'users' => 'Benutzerkonten',
    'contacts' => 'Kontakte',
    'contact_emails' => 'E-Mail-Adressen',
    'contact_phones' => 'Telefonnummern',
    'contact_tags' => 'Kontakt-Tags',
    'password_resets' => 'Passwort-Resets',
    'user_passkeys' => 'Passkeys',
    'app_settings' => 'Einstellungen',
    'schema_migrations' => 'Migrationen',
    'login_attempts' => 'Login-Versuche',
    'audit_log' => 'Änderungsprotokoll',
    'mail_log' => 'Versandprotokoll',
];
?>
<section class="hero-card narrow">
    <div>
        <p class="eyebrow">Administration</p>
        <h2>Datensicherung</h2>
        <p class="muted">Vollständiges Backup aller Daten als ZIP herunterladen oder aus einem Backup wiederherstellen. Gedacht für Sicherung, Serverumzug und Systemwechsel.</p>
    </div>
</section>

<section class="panel">
    <div class="panel-head">
        <div>
            <h3>Aktueller Datenbestand</h3>
            <p class="muted"><?= e((string) $totalRows) ?> Datensätze insgesamt.</p>
        </div>
    </div>
    <table class="contacts-table">
        <thead>
            <tr><th>Bereich</th><th>Datensätze</th></tr>
        </thead>
        <tbody>
            <?php foreach ($rowCounts as $table => $count): ?>
                <tr>
                    <td><?= e($tableLabels[$table] ?? $table) ?></td>
                    <td class="muted"><?= e((string) $count) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="panel">
    <div class="panel-head">
        <div>
            <h3>Backup herunterladen</h3>
            <p class="muted">Enthält alle Tabellen und hochgeladene Dateien (Kontaktfotos, Logo) als ZIP.</p>
        </div>
    </div>
    <div class="subsection-card">
        <strong>Wichtig</strong>
        <p class="detail-hint">Das Backup enthält Passwort-Hashes, Passkey-Daten und die hinterlegten Mailserver-Zugangsdaten. Bitte nur an einem sicheren Ort aufbewahren und nicht unverschlüsselt weitergeben.</p>
    </div>
    <form method="post" action="<?= e(url('/admin/backup/export')) ?>" class="stack" style="margin-top:0.9rem">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <label class="inline-toggle">
            <input type="checkbox" name="include_logs" value="1" checked>
            <span>Protokolle einschließen (Änderungs- und Versandprotokoll, Login-Versuche)</span>
        </label>
        <div class="toolbar-actions">
            <button type="submit"><?= icon('archive') ?><span>Backup herunterladen</span></button>
        </div>
    </form>
</section>

<section class="panel">
    <div class="panel-head">
        <div>
            <h3>Aus Backup wiederherstellen</h3>
            <p class="muted">Spielt ein zuvor heruntergeladenes Backup-ZIP wieder ein.</p>
        </div>
    </div>

    <div class="subsection-card">
        <strong>Achtung</strong>
        <p class="detail-hint"><strong>Alles ersetzen</strong> löscht den kompletten aktuellen Datenbestand unwiderruflich und ersetzt ihn durch den Inhalt des Backups – inklusive Benutzerkonten und Anmeldedaten. Danach ist ggf. eine neue Anmeldung nötig. <strong>Nur wenn leer</strong> funktioniert ausschließlich auf einer frischen Instanz ohne Kontakte.</p>
    </div>

    <form method="post" action="<?= e(url('/admin/backup/restore')) ?>" enctype="multipart/form-data" class="stack" style="margin-top:0.9rem">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <label>
            <span>Backup-Datei (.zip)</span>
            <input type="file" name="backup_file" accept=".zip,application/zip" required>
        </label>
        <fieldset class="stack" style="border:0;padding:0;margin:0">
            <label class="inline-toggle">
                <input type="radio" name="mode" value="fill" checked>
                <span><strong>Nur wenn leer</strong> – frische Instanz erstbefüllen</span>
            </label>
            <label class="inline-toggle">
                <input type="radio" name="mode" value="replace">
                <span><strong>Alles ersetzen</strong> – aktuellen Datenbestand vollständig überschreiben</span>
            </label>
        </fieldset>
        <label>
            <span>Nur für „Alles ersetzen": zur Bestätigung <code><?= e($restoreKeyword) ?></code> eintippen</span>
            <input type="text" name="confirm" autocomplete="off" placeholder="<?= e($restoreKeyword) ?>">
        </label>
        <div class="toolbar-actions">
            <button type="submit" class="danger-button" onclick="return confirm('Wiederherstellung jetzt starten? Bei „Alles ersetzen“ gehen die aktuellen Daten verloren.');">
                <?= icon('upload') ?><span>Wiederherstellung starten</span>
            </button>
        </div>
    </form>
</section>

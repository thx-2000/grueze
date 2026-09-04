<?php
$totalRows = array_sum($rowCounts);
$tableLabels = [
    'roles' => 'Rollen',
    'categories' => 'Kategorien',
    'tags' => 'Tags',
    'users' => 'Zugänge',
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
<header class="page-head">
    <p class="eyebrow">Administration</p>
    <h1>Datensicherung</h1>
    <p class="muted">Vollständiges Backup aller Daten als ZIP herunterladen oder aus einem Backup wiederherstellen. Gedacht für Sicherung, Serverumzug und Systemwechsel.</p>
</header>

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
            <p class="muted">Enthält alle Tabellen und hochgeladene Dateien (Kontaktfotos, Logo) als ZIP. <strong>Galerie-Medien (Fotos/Videos) sind <em>nicht</em> enthalten</strong> – dafür weiter unten „Galerie-Medien sichern".</p>
        </div>
    </div>
    <div class="subsection-card">
        <strong>Wichtig</strong>
        <p class="detail-hint">Das Backup enthält Passwort-Hashes, Passkey-Daten und alle personenbezogenen Daten. Die Mailserver-Passwörter sind bewusst <em>nicht</em> enthalten – die musst du nach einer Wiederherstellung neu eintragen. Bitte nur an einem sicheren Ort aufbewahren und nicht unverschlüsselt weitergeben.</p>
    </div>
    <form method="post" action="<?= e(url('/admin/backup/export')) ?>" class="stack" style="margin-top:0.9rem">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <label class="inline-toggle">
            <input type="checkbox" name="include_logs" value="1" checked>
            <span>Protokolle einschließen (Änderungs- und Versandprotokoll, Login-Versuche)</span>
        </label>
        <?php if (!empty($zipEncryption)): ?>
            <label>
                <span>Passwort für die ZIP-Datei (empfohlen, optional)</span>
                <input type="password" name="backup_password" autocomplete="new-password" minlength="8" placeholder="leer = unverschlüsselt">
                <small class="field-hint">Mit Passwort wird das ZIP <strong>AES-256-verschlüsselt</strong>. Ohne Passwort kann jeder mit Zugriff auf die Datei alle Kontaktdaten lesen. Das Passwort wird nirgends gespeichert – gut merken, es wird zum Wiederherstellen gebraucht.</small>
            </label>
        <?php endif; ?>
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
        <strong>Die drei Modi</strong>
        <ul class="detail-hint" style="margin:0.4rem 0 0;padding-left:1.1rem">
            <li><strong>Zusammenführen</strong> spielt nur die <em>Kontakte</em> aus dem Backup ins bestehende System ein – ohne etwas zu löschen. Gleiche Personen (Vor- und Nachname, ggf. Geburtsname) werden erkannt und nur um fehlende Mailadressen, Telefonnummern, Tags und leere Felder ergänzt. Zugänge, Rollen, Einstellungen und Protokolle bleiben unberührt.</li>
            <li><strong>Nur wenn leer</strong> befüllt eine frische Instanz vollständig – nur auf einem System ohne Kontakte.</li>
            <li><strong>Alles ersetzen</strong> löscht den kompletten aktuellen Datenbestand unwiderruflich und ersetzt ihn durch das Backup, inklusive der Zugänge. Danach ist ggf. eine neue Anmeldung nötig.</li>
        </ul>
    </div>

    <form method="post" action="<?= e(url('/admin/backup/restore')) ?>" enctype="multipart/form-data" class="stack" style="margin-top:0.9rem">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <label>
            <span>Backup-Datei (.zip)</span>
            <input type="file" name="backup_file" accept=".zip,application/zip" required>
        </label>
        <fieldset class="stack" style="border:0;padding:0;margin:0">
            <label class="inline-toggle">
                <input type="radio" name="mode" value="merge" checked>
                <span><strong>Zusammenführen</strong> – Kontakte ins bestehende System einspielen, nichts löschen</span>
            </label>
            <label class="inline-toggle">
                <input type="radio" name="mode" value="fill">
                <span><strong>Nur wenn leer</strong> – frische Instanz erstbefüllen</span>
            </label>
            <label class="inline-toggle">
                <input type="radio" name="mode" value="replace">
                <span><strong>Alles ersetzen</strong> – aktuellen Datenbestand vollständig überschreiben</span>
            </label>
        </fieldset>
        <label>
            <span>Passwort des Backups (nur bei verschlüsseltem ZIP)</span>
            <input type="password" name="backup_password" autocomplete="off" placeholder="leer lassen, wenn ohne Passwort erstellt">
        </label>
        <label>
            <span>Nur für „Alles ersetzen": zur Bestätigung <code><?= e($restoreKeyword) ?></code> eintippen</span>
            <input type="text" name="confirm" autocomplete="off" placeholder="<?= e($restoreKeyword) ?>">
        </label>
        <div class="toolbar-actions">
            <button type="submit" class="danger-button" data-confirm="Import jetzt starten? Bei „Alles ersetzen“ gehen die aktuellen Daten verloren.">
                <?= icon('upload') ?><span>Import starten</span>
            </button>
        </div>
    </form>
</section>

<section class="panel">
    <div class="panel-head">
        <div>
            <h3>Galerie-Medien sichern</h3>
            <p class="muted">
                Fotos und Videos aus <a href="<?= e(url('/galerien')) ?>">Galerien</a> als eigene ZIP – mit Manifest, unabhängig vom Backup oben und zusätzlich zur Sicherung beim Hoster.
                <?php if ($galleryCount > 0): ?>
                    Aktuell <?= e((string) $galleryCount) ?> <?= $galleryCount === 1 ? 'Galerie' : 'Galerien' ?><?= $mediaBytes > 0 ? ', ' . e(\App\Services\MediaService::humanBytes($mediaBytes)) : '' ?>.
                <?php endif; ?>
            </p>
        </div>
    </div>
    <?php $over = $mediaBackupMax > 0 && $mediaBytes > $mediaBackupMax; ?>
    <div class="toolbar-actions">
        <a class="ghost-button<?= $over || $galleryCount === 0 ? ' is-disabled' : '' ?>" href="<?= e(url('/admin/backup/medien')) ?>"<?= $over || $galleryCount === 0 ? ' aria-disabled="true" tabindex="-1"' : '' ?>>
            <?= icon('download') ?><span>Alle Galerie-Medien sichern</span>
        </a>
    </div>
    <?php if ($over): ?>
        <p class="field-hint">Zu groß für eine Gesamt-Sicherung (Limit <?= e(\App\Services\MediaService::humanBytes($mediaBackupMax)) ?>). Bitte einzelne Galerien direkt dort über „Als ZIP" sichern.</p>
    <?php endif; ?>
    <details class="gallery-restore">
        <summary>Medien-Sicherung einspielen</summary>
        <form method="post" action="<?= e(url('/admin/backup/medien')) ?>" enctype="multipart/form-data" class="stack" data-confirm="Sicherung jetzt einspielen? Die enthaltenen Galerien werden als NEUE Galerien angelegt (nichts wird überschrieben).">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <label>
                <span>ZIP-Sicherung (aus „Alle Galerie-Medien sichern")</span>
                <input type="file" name="backup_file" accept=".zip,application/zip" required>
            </label>
            <div class="form-actions"><button type="submit" class="ghost-button"><?= icon('upload') ?><span>Einspielen</span></button></div>
        </form>
    </details>
</section>

<section class="panel">
    <div class="panel-head">
        <div>
            <h3>Dokumente sichern</h3>
            <p class="muted">
                Dateien aus <a href="<?= e(url('/dokumente')) ?>">Dokumente</a> (inkl. Unterordner) als eigene ZIP – mit Manifest, unabhängig vom Backup oben und zusätzlich zur Sicherung beim Hoster.
                <?php if ($documentFolderCount > 0): ?>
                    Aktuell <?= e((string) $documentFolderCount) ?> Ordner auf oberster Ebene<?= $documentBytes > 0 ? ', ' . e(\App\Services\MediaService::humanBytes($documentBytes)) : '' ?>.
                <?php endif; ?>
            </p>
        </div>
    </div>
    <?php $docOver = $documentBackupMax > 0 && $documentBytes > $documentBackupMax; ?>
    <div class="toolbar-actions">
        <a class="ghost-button<?= $docOver || $documentFolderCount === 0 ? ' is-disabled' : '' ?>" href="<?= e(url('/admin/backup/dokumente')) ?>"<?= $docOver || $documentFolderCount === 0 ? ' aria-disabled="true" tabindex="-1"' : '' ?>>
            <?= icon('download') ?><span>Alle Dokumente sichern</span>
        </a>
    </div>
    <?php if ($docOver): ?>
        <p class="field-hint">Zu groß für eine Gesamt-Sicherung (Limit <?= e(\App\Services\MediaService::humanBytes($documentBackupMax)) ?>).</p>
    <?php endif; ?>
    <details class="gallery-restore">
        <summary>Dokumente-Sicherung einspielen</summary>
        <form method="post" action="<?= e(url('/admin/backup/dokumente')) ?>" enctype="multipart/form-data" class="stack" data-confirm="Sicherung jetzt einspielen? Die enthaltenen Ordner werden als NEUE Ordner angelegt (nichts wird überschrieben).">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <label>
                <span>ZIP-Sicherung (aus „Alle Dokumente sichern")</span>
                <input type="file" name="backup_file" accept=".zip,application/zip" required>
            </label>
            <div class="form-actions"><button type="submit" class="ghost-button"><?= icon('upload') ?><span>Einspielen</span></button></div>
        </form>
    </details>
</section>

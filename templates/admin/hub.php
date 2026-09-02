<?php
$groups = [
    'Zugänge' => [
        ['users.manage', '/users', 'user', 'Benutzer', 'Zugänge anlegen, sperren, Passkeys zurücksetzen.'],
        ['users.manage', '/verwaltung/registrierung', 'key', 'Selbst-Registrierung', 'Einladungslinks, Selbst-Anmeldung freischalten, Standard-Rolle.'],
        ['users.manage', '/settings/roles', 'key', 'Rollen', 'Rollen anlegen, umbenennen und löschen.'],
        ['users.manage', '/settings/permissions', 'sliders', 'Berechtigungen', 'Festlegen, welche Rolle welche Aktionen ausführen darf.'],
        ['users.manage', '/settings/visibility', 'eye', 'Sichtbarkeit', 'Steuern, welche Rolle welche Kontaktfelder sieht.'],
    ],
    'Erscheinungsbild' => [
        ['users.manage', '/settings/branding', 'sliders', 'Branding', 'Name, Kurzname, öffentliche Links, Login-Texte und Logo.'],
        ['users.manage', '/settings/themes', 'sparkles', 'Themes', 'Farben, Schriften und Ecken – Theme wechseln, duplizieren, anpassen.'],
        ['settings.manage', '/settings/mail-footer', 'mail', 'Mail-Einstellungen', 'Absender, Mailserver, Betreff-Präfixe und Mail-Fuß.'],
        ['users.manage', '/admin/legal/impressum', 'sliders', 'Rechtliches', 'Impressum und Datenschutzerklärung bearbeiten.'],
        ['categories.manage', '/verwaltung/kategorien-tags', 'sliders', 'Kategorien & Tags', 'Kategorien und Tags anlegen, umbenennen und löschen.'],
        ['settings.manage', '/verwaltung/gruesse', 'sparkles', 'Grüße-Pool', 'Standard-Wünsche für Geburtstag und Weihnachten pflegen; Weihnachtsgrüße gemischt verschicken.'],
    ],
    'System' => [
        ['users.manage', '/admin/aktualisieren', 'upload', 'Aktualisieren', 'Nach einem Upload die Datenbank auf den neuen Stand bringen.'],
        ['users.manage', '/admin/backup', 'archive', 'Datensicherung', 'Voll-Backup herunterladen oder wiederherstellen.'],
        ['contacts.manage', '/vollstaendigkeit', 'check', 'Vollständigkeit', 'Datenlücken sehen, pro Person nachtragen, Namensliste zum Abgleich weitergeben.'],
        ['audit.view', '/logs/audit', 'history', 'Änderungsprotokoll', 'Wer hat wann welchen Kontakt geändert.'],
        ['mail.view_log', '/logs/mail', 'mail', 'Versandprotokoll', 'Welche Mails wurden verschickt, was schlug fehl.'],
    ],
];
?>
<header class="contact-detail-head">
    <p class="eyebrow">Verwaltung</p>
    <h1>Einstellungen</h1>
    <p class="muted">Alles, was man selten braucht – gebündelt an einem Ort. Für die tägliche Arbeit reichen „Start" und „Adressbuch".</p>
</header>

<?php foreach ($groups as $groupTitle => $tiles): ?>
    <?php
    $visible = array_values(array_filter($tiles, static fn (array $t): bool => can($t[0])));
    if ($visible === []) {
        continue;
    }
    ?>
    <section class="panel stack">
        <div class="panel-head">
            <div><h3><?= e($groupTitle) ?></h3></div>
        </div>
        <div class="hub-grid">
            <?php foreach ($visible as [$perm, $path, $iconName, $title, $desc]): ?>
                <a class="hub-tile" href="<?= e(url($path)) ?>">
                    <span class="hub-tile-icon"><?= icon($iconName) ?></span>
                    <span class="hub-tile-title"><?= e($title) ?></span>
                    <span class="hub-tile-desc"><?= e($desc) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>

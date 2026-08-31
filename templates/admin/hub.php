<?php
$groups = [
    'Personen & Zugänge' => [
        ['users.manage', '/users', 'user', 'Benutzer', 'Zugänge anlegen, sperren, Passkeys zurücksetzen.'],
        ['users.manage', '/settings/permissions', 'sliders', 'Berechtigungen', 'Festlegen, welche Rolle welche Aktionen ausführen darf.'],
        ['users.manage', '/settings/visibility', 'eye', 'Sichtbarkeit', 'Steuern, welche Rolle welche Kontaktfelder sieht.'],
    ],
    'Erscheinungsbild & Texte' => [
        ['users.manage', '/settings/branding', 'sparkles', 'Design & Branding', 'Name, Farben, Fonts, Logo und sichtbare Texte.'],
        ['settings.manage', '/settings/mail-footer', 'mail', 'Mail-Einstellungen', 'Absender, Mailserver, Betreff-Präfixe und Mail-Fuß.'],
        ['users.manage', '/admin/legal/impressum', 'sliders', 'Rechtliches', 'Impressum und Datenschutzerklärung bearbeiten.'],
    ],
    'Werkzeuge' => [
        ['categories.manage', '/verwaltung/kategorien-tags', 'sliders', 'Kategorien & Tags', 'Kategorien und Tags anlegen, umbenennen und löschen.'],
        ['contacts.manage', '/namensliste', 'contacts', 'Namensliste', 'Namensliste als Kopiervorlage erzeugen und für den Vollständigkeitsabgleich verschicken.'],
    ],
    'System' => [
        ['users.manage', '/admin/backup', 'archive', 'Datensicherung', 'Voll-Backup herunterladen oder wiederherstellen.'],
        ['users.manage', '/admin/migrations', 'history', 'Migrationen', 'Stand der Datenbank-Migrationen einsehen.'],
        ['audit.view', '/logs/audit', 'history', 'Änderungsprotokoll', 'Wer hat wann welchen Kontakt geändert.'],
        ['mail.view_log', '/logs/mail', 'mail', 'Versandprotokoll', 'Welche Mails wurden verschickt, was schlug fehl.'],
    ],
];
?>
<section class="hero-card narrow">
    <div>
        <p class="eyebrow">Verwaltung</p>
        <h2>Einstellungen & Systempflege</h2>
        <p class="muted">Alles, was man selten braucht – gebündelt an einem Ort. Für die tägliche Arbeit reichen „Start" und „Kontakte".</p>
    </div>
</section>

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

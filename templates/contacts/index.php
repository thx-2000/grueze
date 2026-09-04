<?php
/**
 * Adressbuch-Übersicht. Der Seitenkopf, die Ansichts-/Auswahl-Leiste und das
 * gemeinsame Auswahl-Formular bleiben hier; die großen Blöcke liegen als
 * Teil-Templates unter contacts/_index/ (Filterleiste, Tabelle, Karten,
 * Sammelbearbeitung, eigener Kontakt).
 */
$contactCount = count($contacts);
$supportEmail = trim((string) branding_value('branding_support_email', ''));
$activeTagIds = array_map('intval', (array) ($filters['tag_ids'] ?? []));
$activeGroupIds = array_map('intval', (array) ($filters['group_ids'] ?? []));
$groups = $groups ?? [];
$ownContact = $ownContact ?? null;

$visibleContactFields = [
    'address' => can_view_contact_field('address'),
    'birthday' => can_view_contact_field('birthday'),
    'emails' => can_view_contact_field('emails'),
    'phones' => can_view_contact_field('phones'),
    'notes' => can_view_contact_field('notes'),
    'login' => can_view_contact_field('login'),
];
$canViewPrivateDetails = in_array(true, $visibleContactFields, true);

// Zusatzspalten der Tabelle. Standard: aus – zuschaltbar über „Spalten",
// gemerkt pro Gerät (localStorage). Reihenfolge = Anzeigereihenfolge.
$optionalColumns = ['tags' => 'Tags', 'gruppen' => 'Gruppen'];
if ($visibleContactFields['address']) {
    $optionalColumns['adresse'] = 'Adresse';
}
if ($visibleContactFields['birthday']) {
    $optionalColumns['geburtstag'] = 'Geburtstag';
}
if ($visibleContactFields['emails']) {
    $optionalColumns['emails'] = 'E-Mail';
}
if ($visibleContactFields['phones']) {
    $optionalColumns['phones'] = 'Telefon';
}
if ($visibleContactFields['login']) {
    $optionalColumns['login'] = 'Login / Rolle';
}

$canCopyVisibleEmails = $visibleContactFields['emails'] && can('contacts.copy_emails');
$canSendRegularMail = can('mail.send');
$canSendSingleContactMail = can('mail.contact_single');
$canManage = can('contacts.manage');
// Mitglieder-Sicht: darf genau eine Person kontaktieren, verwaltet aber nichts.
$isMemberContactView = $canSendSingleContactMail && !$canManage;
$canSelect = $canCopyVisibleEmails || $canSendRegularMail || $canSendSingleContactMail || $canManage;

// Ohne-Mailadresse-Zahl für die Kopfzeile (nur aus der aktuellen Liste).
$withoutEmailInList = 0;
foreach ($contacts as $contact) {
    if (($contact['emails'] ?? []) === []) {
        $withoutEmailInList++;
    }
}

$hasActiveFilter = ($filters['q'] ?? '') !== ''
    || ($filters['category_id'] ?? '') !== ''
    || $activeTagIds !== []
    || $activeGroupIds !== []
    || ($filters['without_email'] ?? '') === '1'
    || ($filters['without_phone'] ?? '') === '1';
$advancedFilterActive = ($filters['sort'] ?? 'vorname') !== 'vorname'
    || ($filters['direction'] ?? 'asc') !== 'asc'
    || $activeTagIds !== []
    || $activeGroupIds !== []
    || ($filters['without_email'] ?? '') === '1'
    || ($filters['without_phone'] ?? '') === '1';

$currentSort = (string) ($filters['sort'] ?? 'vorname');
$currentDirection = (string) ($filters['direction'] ?? 'asc');
$buildSortUrl = static function (string $sortKey) use ($filters, $currentSort, $currentDirection): string {
    $nextDirection = $currentSort === $sortKey && $currentDirection === 'asc' ? 'desc' : 'asc';
    $query = $filters;
    $query['sort'] = $sortKey;
    $query['direction'] = $nextDirection;

    return url('/kontakte?' . http_build_query($query));
};
$ariaSort = static function (string $sortKey) use ($currentSort, $currentDirection): string {
    if ($currentSort !== $sortKey) {
        return 'none';
    }

    return $currentDirection === 'asc' ? 'ascending' : 'descending';
};

// Datenstand einer Person als Chip-Liste: fehlt Mail? fehlt Telefon?
$statusChips = static function (array $contact): array {
    $hasEmail = ($contact['emails'] ?? []) !== [];
    $hasPhone = ($contact['phones'] ?? []) !== [];
    if ($hasEmail && $hasPhone) {
        return [['tone' => 'ok', 'label' => 'vollständig']];
    }
    $chips = [];
    if (!$hasEmail) {
        $chips[] = ['tone' => 'warn', 'label' => 'Mail fehlt'];
    }
    if (!$hasPhone) {
        $chips[] = ['tone' => 'warn', 'label' => 'Tel. fehlt'];
    }

    return $chips;
};
$renderChips = static function (array $chips): string {
    $html = '<div class="status-chips">';
    foreach ($chips as $chip) {
        $html .= '<span class="status-chip is-' . e($chip['tone']) . '">' . e($chip['label']) . '</span>';
    }

    return $html . '</div>';
};
?>
<header class="contacts-header">
    <div>
        <h1>Adressbuch</h1>
        <p class="muted">
            <?= e((string) $contactCount) ?> <?= $contactCount === 1 ? 'Kontakt' : 'Kontakte' ?><?= $hasActiveFilter ? ' (gefiltert)' : '' ?><?php if ($withoutEmailInList > 0): ?> · <?= e((string) $withoutEmailInList) ?> ohne Mailadresse<?php endif; ?>
        </p>
    </div>
    <?php if ($canManage): ?>
        <a class="button-link" href="<?= e(url('/contacts/create')) ?>"><?= icon('plus') ?><span>Person hinzufügen</span></a>
    <?php endif; ?>
</header>

<?php view_partial('contacts/_index/own-contact', get_defined_vars()); ?>

<?php view_partial('contacts/_index/toolbar', get_defined_vars()); ?>

<section class="panel contacts-view-root is-table" data-contacts-view-root aria-labelledby="contactListTitle">
    <h2 class="visually-hidden" id="contactListTitle">Kontaktliste</h2>
    <div class="list-bar">
        <div class="view-toggle" role="group" aria-label="Ansicht umschalten">
            <button type="button" class="view-toggle-button is-active" data-view-toggle="desktop" aria-pressed="true">Tabelle</button>
            <button type="button" class="view-toggle-button" data-view-toggle="mobile" aria-pressed="false">Karten</button>
        </div>
        <div class="list-bar-right">
            <?php if ($optionalColumns !== []): ?>
                <details class="column-menu">
                    <summary><?= icon('sliders') ?><span>Spalten</span></summary>
                    <div class="column-menu-body">
                        <p class="field-hint">Zusätzliche Spalten für die Tabelle. Bleibt auf diesem Gerät gespeichert.</p>
                        <?php foreach ($optionalColumns as $colKey => $colLabel): ?>
                            <label class="column-toggle">
                                <input type="checkbox" data-column-toggle="<?= e($colKey) ?>">
                                <span><?= e($colLabel) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>
            <?php if ($canSelect): ?>
                <button type="button" class="ghost-button select-mode-button" data-select-mode-toggle aria-pressed="false">
                    <?= icon('check-double') ?><span>Auswählen</span>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$canViewPrivateDetails): ?>
        <p class="field-hint list-role-hint">Für deine Rolle sind Kontaktdaten ausgeblendet – der Status zeigt nur, ob Angaben fehlen.</p>
    <?php endif; ?>

    <form id="contactSelectionForm" method="post" action="<?= e(url('/mail/compose')) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <?php if ($canSelect): ?>
            <div class="select-bar" role="group" aria-label="Aktionen für die Auswahl">
                <span class="select-bar-count" id="selectionStatus" role="status">Noch nichts ausgewählt</span>
                <div class="select-bar-quick">
                    <button type="button" class="linkish" data-select="all">Alle</button>
                    <button type="button" class="linkish" data-select="none">Keine</button>
                </div>
                <div class="select-bar-actions">
                    <?php if ($canCopyVisibleEmails): ?>
                        <button type="button" id="copyEmailsButton" class="ghost-button"><?= icon('copy') ?><span>E-Mails kopieren</span></button>
                    <?php endif; ?>
                    <?php if ($canSendRegularMail): ?>
                        <button type="submit" class="button-link"><?= icon('edit') ?><span>E-Mail verfassen</span></button>
                    <?php elseif ($canSendSingleContactMail): ?>
                        <button type="submit" class="button-link"><?= icon('message-send') ?><span>Person kontaktieren</span></button>
                    <?php endif; ?>
                    <?php if (can('contacts.export')): ?>
                        <button type="submit" class="ghost-button" formaction="<?= e(url('/contacts/vcard')) ?>" formmethod="post"><?= icon('contacts') ?><span>vCard</span></button>
                    <?php endif; ?>
                    <?php if (can('users.manage')): ?>
                        <button type="submit" class="ghost-button" formaction="<?= e(url('/verwaltung/einladungen/vorschau')) ?>" formmethod="post" name="mode" value="selection"><?= icon('mail') ?><span>Einladungen für Auswahl</span></button>
                    <?php endif; ?>
                    <button type="button" class="ghost-button" data-select-mode-exit><?= icon('close') ?><span>Fertig</span></button>
                </div>
            </div>
            <?php if ($isMemberContactView): ?>
                <p class="detail-hint select-bar-hint">Genau eine Person auswählen. Die Zieladresse bleibt verborgen; Antworten gehen an deine eigene Login-Mailadresse.<?php if ($supportEmail !== ''): ?> Fehlt eine Mailadresse und du kennst sie? Bitte an <a href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a>.<?php endif; ?></p>
            <?php endif; ?>
        <?php endif; ?>

        <?php view_partial('contacts/_index/table', get_defined_vars()); ?>

        <?php view_partial('contacts/_index/cards', get_defined_vars()); ?>

        <?php if ($canManage): ?>
            <?php view_partial('contacts/_index/bulk-edit', get_defined_vars()); ?>
        <?php endif; ?>
    </form>
</section>

<?php if (can('categories.manage')): ?>
    <section class="panel narrow stack">
        <details class="admin-drawer">
            <summary>
                <span><?= icon('plus') ?></span>
                <span>Kategorie oder Tag schnell anlegen</span>
            </summary>
            <div class="admin-drawer-body stack">
                <div>
                    <h2>Kategorie ergänzen</h2>
                    <form method="post" action="<?= e(url('/categories/store')) ?>" class="inline-form">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <input type="text" name="name" placeholder="Neue Kategorie" aria-label="Name der neuen Kategorie" required>
                        <button type="submit">Speichern</button>
                    </form>
                </div>
                <div>
                    <h2>Tag ergänzen</h2>
                    <form method="post" action="<?= e(url('/tags/store')) ?>" class="inline-form">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <input type="text" name="name" placeholder="Neuer Tag" aria-label="Name des neuen Tags" required>
                        <button type="submit">Speichern</button>
                    </form>
                </div>
                <p class="field-hint"><a href="<?= e(url('/verwaltung/kategorien-tags')) ?>">Kategorien &amp; Tags umbenennen oder löschen &rarr;</a></p>
            </div>
        </details>
    </section>
<?php endif; ?>

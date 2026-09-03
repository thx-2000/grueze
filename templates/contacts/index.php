<?php
$contactCount = count($contacts);
$supportEmail = trim((string) branding_value('branding_support_email', ''));
$activeTagIds = array_map('intval', (array) ($filters['tag_ids'] ?? []));
$activeGroupIds = array_map('intval', (array) ($filters['group_ids'] ?? []));
$groups = $groups ?? [];

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

<?php
// Eigene verknüpfte Kontaktdaten – auch für Rollen sichtbar, die sonst nichts
// sehen. Notizen sind bewusst ausgenommen.
$ownContact = $ownContact ?? null;
$ownFields = $ownContact !== null ? [
    'address'  => can_view_contact_field('address', $ownContact),
    'birthday' => can_view_contact_field('birthday', $ownContact),
    'emails'   => can_view_contact_field('emails', $ownContact),
    'phones'   => can_view_contact_field('phones', $ownContact),
    'login'    => can_view_contact_field('login', $ownContact),
] : [];
?>
<?php if ($ownContact !== null && in_array(true, $ownFields, true)): ?>
    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Deine Kontaktdaten</h2>
                <p class="muted">
                    Das ist bei uns zu dir hinterlegt.
                    <?php if ($canManage): ?>
                        <a href="<?= e(url('/contacts/edit?id=' . (int) $ownContact['id'])) ?>">Bearbeiten</a>.
                    <?php elseif ($supportEmail !== ''): ?>
                        Stimmt etwas nicht? Melde dich bei <a href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a>.
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <dl class="own-contact-list is-guarded">
            <div><dt>Name</dt><dd><?= e(trim(($ownContact['vorname'] ?? '') . ' ' . ($ownContact['nachname'] ?? ''))) ?></dd></div>
            <?php if ($ownFields['address']): ?>
                <div><dt>Adresse</dt><dd>
                    <?= e(trim((string) ($ownContact['strasse'] ?? ''))) ?>
                    <?php if (trim((string) ($ownContact['plz'] ?? '') . ($ownContact['ort'] ?? '')) !== ''): ?>
                        <br><?= e(trim(($ownContact['plz'] ?? '') . ' ' . ($ownContact['ort'] ?? ''))) ?>
                    <?php endif; ?>
                    <?php if (trim((string) ($ownContact['land'] ?? '')) !== '' && ($ownContact['land'] ?? '') !== 'Deutschland'): ?>
                        <br><?= e((string) $ownContact['land']) ?>
                    <?php endif; ?>
                </dd></div>
            <?php endif; ?>
            <?php if ($ownFields['birthday'] && trim((string) ($ownContact['geburtstag'] ?? '')) !== ''): ?>
                <div><dt>Geburtstag</dt><dd><?= e(format_date($ownContact['geburtstag'])) ?></dd></div>
            <?php endif; ?>
            <?php if ($ownFields['emails'] && ($ownContact['emails'] ?? []) !== []): ?>
                <div><dt>E-Mail</dt><dd>
                    <?php foreach ($ownContact['emails'] as $mail): ?>
                        <div><?= e($mail['email']) ?><?= trim((string) ($mail['label'] ?? '')) !== '' ? ' (' . e($mail['label']) . ')' : '' ?></div>
                    <?php endforeach; ?>
                </dd></div>
            <?php endif; ?>
            <?php if ($ownFields['phones'] && ($ownContact['phones'] ?? []) !== []): ?>
                <div><dt>Telefon</dt><dd>
                    <?php foreach ($ownContact['phones'] as $tel): ?>
                        <div><?= trim((string) ($tel['label'] ?? '')) !== '' ? e($tel['label']) . ': ' : '' ?><?= e($tel['phone']) ?></div>
                    <?php endforeach; ?>
                </dd></div>
            <?php endif; ?>
            <?php if ($ownFields['login'] && !empty($ownContact['linked_user'])): ?>
                <div><dt>Login</dt><dd><?= e($ownContact['linked_user']['email']) ?></dd></div>
            <?php endif; ?>
        </dl>
    </section>
<?php endif; ?>

<section class="panel addressbook-filter">
    <form method="get" action="<?= e(url('/kontakte')) ?>" class="filter-bar">
        <label class="filter-field filter-field--search">
            <span class="visually-hidden">Suche</span>
            <?= icon('search') ?>
            <input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Name oder Geburtsname">
        </label>
        <label class="filter-field">
            <span class="visually-hidden">Kategorie</span>
            <select name="category_id">
                <option value="">Alle Kategorien</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e((string) $category['id']) ?>" <?= (string) ($filters['category_id'] ?? '') === (string) $category['id'] ? 'selected' : '' ?>>
                        <?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="filter-apply">Filtern</button>

        <details class="filter-more"<?= $advancedFilterActive ? ' open' : '' ?>>
            <summary><?= icon('sliders') ?><span>Filter</span></summary>
            <div class="filter-more-body">
                <div class="filter-more-grid">
                    <label>
                        <span>Sortierung</span>
                        <select name="sort">
                            <option value="vorname" <?= $currentSort === 'vorname' ? 'selected' : '' ?>>Vorname</option>
                            <option value="nachname" <?= $currentSort === 'nachname' ? 'selected' : '' ?>>Nachname</option>
                            <option value="category_name" <?= $currentSort === 'category_name' ? 'selected' : '' ?>>Kategorie</option>
                            <option value="ort" <?= $currentSort === 'ort' ? 'selected' : '' ?>>Ort</option>
                            <option value="geburtstag" <?= $currentSort === 'geburtstag' ? 'selected' : '' ?>>Geburtstag</option>
                            <option value="tags" <?= $currentSort === 'tags' ? 'selected' : '' ?>>Tags</option>
                            <option value="groups" <?= $currentSort === 'groups' ? 'selected' : '' ?>>Gruppen</option>
                            <option value="created_at" <?= $currentSort === 'created_at' ? 'selected' : '' ?>>Angelegt</option>
                        </select>
                    </label>
                    <label>
                        <span>Richtung</span>
                        <select name="direction">
                            <option value="asc" <?= $currentDirection === 'asc' ? 'selected' : '' ?>>A bis Z</option>
                            <option value="desc" <?= $currentDirection === 'desc' ? 'selected' : '' ?>>Z bis A</option>
                        </select>
                    </label>
                    <div class="filter-tags" role="group" aria-label="Nach Tags filtern">
                        <span>Tags</span>
                        <div class="tag-picker">
                            <?php foreach ($tags as $tag): ?>
                                <?php $selected = in_array((int) $tag['id'], $activeTagIds, true); ?>
                                <label class="tag-option<?= $selected ? ' is-selected' : '' ?>">
                                    <input type="checkbox" name="tag_ids[]" value="<?= e((string) $tag['id']) ?>" <?= $selected ? 'checked' : '' ?>>
                                    <span><?= e($tag['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                            <?php if ($tags === []): ?>
                                <p class="field-hint">Noch keine Tags angelegt.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($groups !== []): ?>
                        <div class="filter-tags" role="group" aria-label="Nach Gruppen filtern">
                            <span>Gruppen</span>
                            <div class="tag-picker">
                                <?php foreach ($groups as $group): ?>
                                    <?php $selected = in_array((int) $group['id'], $activeGroupIds, true); ?>
                                    <label class="tag-option<?= $selected ? ' is-selected' : '' ?>">
                                        <input type="checkbox" name="group_ids[]" value="<?= e((string) $group['id']) ?>" <?= $selected ? 'checked' : '' ?>>
                                        <span><?= e($group['name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="filter-tags" role="group" aria-label="Fehlende Angaben">
                        <span>Fehlende Angaben</span>
                        <label class="inline-toggle">
                            <input type="checkbox" name="without_email" value="1" <?= ($filters['without_email'] ?? '') === '1' ? 'checked' : '' ?>>
                            <span>Nur Personen ohne Mailadresse</span>
                        </label>
                        <label class="inline-toggle">
                            <input type="checkbox" name="without_phone" value="1" <?= ($filters['without_phone'] ?? '') === '1' ? 'checked' : '' ?>>
                            <span>Nur Personen ohne Handynummer</span>
                        </label>
                    </div>
                </div>
                <div class="filter-more-actions">
                    <button type="submit">Filter anwenden</button>
                    <?php if ($hasActiveFilter): ?>
                        <a class="ghost-button" href="<?= e(url('/kontakte')) ?>"><?= icon('reset') ?><span>Zurücksetzen</span></a>
                    <?php endif; ?>
                </div>
            </div>
        </details>
    </form>

    <?php if ($hasActiveFilter): ?>
        <p class="filter-active-note">
            <?= icon('sliders') ?>
            <span>Gefilterte Ansicht.</span>
            <a href="<?= e(url('/kontakte')) ?>">Alle Kontakte zeigen</a>
        </p>
    <?php endif; ?>

    <?php if ($canSendRegularMail || can('contacts.export') || $canManage): ?>
        <div class="addressbook-tools">
            <?php if ($canSendRegularMail): ?>
                <a class="ghost-button" href="<?= e(url('/rundmail?' . http_build_query(array_merge($filters, ['from' => 'filter'])))) ?>"><?= icon('mail') ?><span>Rundmail an diese Liste</span></a>
            <?php endif; ?>
            <?php if ($canManage): ?>
                <a class="ghost-button" href="<?= e(url('/vollstaendigkeit' . (($filters['category_id'] ?? '') !== '' ? '?category_id=' . rawurlencode((string) $filters['category_id']) : ''))) ?>"><?= icon('check') ?><span>Vollständigkeit</span></a>
            <?php endif; ?>
            <?php if (can('contacts.export')): ?>
                <a class="ghost-button" href="<?= e(url('/contacts/export?' . http_build_query($filters))) ?>"><?= icon('upload') ?><span>CSV exportieren</span></a>
            <?php endif; ?>
            <?php if (can('contacts.delete')): ?>
                <a class="ghost-button" href="<?= e(url('/kontakte/archiv')) ?>"><?= icon('archive') ?><span>Archiv &amp; Papierkorb<?= $retiredCount > 0 ? ' (' . (int) $retiredCount . ')' : '' ?></span></a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

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
                    <button type="button" class="ghost-button" data-select-mode-exit><?= icon('close') ?><span>Fertig</span></button>
                </div>
            </div>
            <?php if ($isMemberContactView): ?>
                <p class="detail-hint select-bar-hint">Genau eine Person auswählen. Die Zieladresse bleibt verborgen; Antworten gehen an deine eigene Login-Mailadresse.<?php if ($supportEmail !== ''): ?> Fehlt eine Mailadresse und du kennst sie? Bitte an <a href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a>.<?php endif; ?></p>
            <?php endif; ?>
        <?php endif; ?>

        <div class="contacts-table-wrap">
            <table class="contacts-table contacts-table--lean">
                <thead>
                    <tr>
                        <th class="col-select" scope="col"><span class="visually-hidden">Auswahl</span></th>
                        <th scope="col" aria-sort="<?= e($ariaSort('nachname')) ?>"><a class="sort-link" href="<?= e($buildSortUrl('nachname')) ?>">Name</a></th>
                        <th scope="col" aria-sort="<?= e($ariaSort('category_name')) ?>"><a class="sort-link" href="<?= e($buildSortUrl('category_name')) ?>">Kategorie</a></th>
                        <th scope="col">Status</th>
                        <?php if (isset($optionalColumns['tags'])): ?><th data-col="tags" scope="col" aria-sort="<?= e($ariaSort('tags')) ?>"><a class="sort-link" href="<?= e($buildSortUrl('tags')) ?>">Tags</a></th><?php endif; ?>
                        <?php if (isset($optionalColumns['gruppen'])): ?><th data-col="gruppen" scope="col" aria-sort="<?= e($ariaSort('groups')) ?>"><a class="sort-link" href="<?= e($buildSortUrl('groups')) ?>">Gruppen</a></th><?php endif; ?>
                        <?php if (isset($optionalColumns['adresse'])): ?><th data-col="adresse" scope="col" aria-sort="<?= e($ariaSort('ort')) ?>"><a class="sort-link" href="<?= e($buildSortUrl('ort')) ?>">Adresse</a></th><?php endif; ?>
                        <?php if (isset($optionalColumns['geburtstag'])): ?><th data-col="geburtstag" scope="col" aria-sort="<?= e($ariaSort('geburtstag')) ?>"><a class="sort-link" href="<?= e($buildSortUrl('geburtstag')) ?>">Geburtstag</a></th><?php endif; ?>
                        <?php if (isset($optionalColumns['emails'])): ?><th data-col="emails" scope="col">E-Mail</th><?php endif; ?>
                        <?php if (isset($optionalColumns['phones'])): ?><th data-col="phones" scope="col">Telefon</th><?php endif; ?>
                        <?php if (isset($optionalColumns['login'])): ?><th data-col="login" scope="col">Login / Rolle</th><?php endif; ?>
                        <?php if ($canManage): ?><th class="col-open" scope="col"><span class="visually-hidden">Öffnen</span></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contacts as $contact): ?>
                        <tr class="contact-row" data-contact-selectable data-view="desktop" data-category-id="<?= e((string) ($contact['category_id'] ?? '')) ?>" data-tag-ids="<?= e(implode(',', array_map(static fn (array $tag): string => (string) $tag['id'], $contact['tags'] ?? []))) ?>">
                            <td class="col-select">
                                <label class="table-check">
                                    <input type="checkbox" name="selected_contacts[]" value="<?= e((string) $contact['id']) ?>" data-contact-checkbox aria-label="<?= e(trim($contact['vorname'] . ' ' . $contact['nachname']) . ' auswählen') ?>">
                                </label>
                            </td>
                            <td>
                                <div class="contact-name-cell">
                                    <strong><?= e(trim($contact['vorname'] . ' ' . $contact['nachname'])) ?></strong>
                                    <?php if (($bn = format_birth_name($contact)) !== ''): ?>
                                        <span class="birth-name-inline"><?= e($bn) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><span class="table-pill"><?= e($contact['category_name'] ?: '—') ?></span></td>
                            <td><?= $renderChips($statusChips($contact)) ?></td>
                            <?php if (isset($optionalColumns['tags'])): ?>
                                <td data-col="tags">
                                    <div class="tag-cluster">
                                        <?php foreach ($contact['tags'] as $tag): ?>
                                            <span class="tag tag-secondary" style="<?= e(tag_style($tag['name'])) ?>"><?= e($tag['name']) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            <?php endif; ?>
                            <?php if (isset($optionalColumns['gruppen'])): ?>
                                <td data-col="gruppen">
                                    <div class="tag-cluster">
                                        <?php foreach (($contact['groups'] ?? []) as $group): ?>
                                            <span class="tag tag-group"><?= e($group['name']) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            <?php endif; ?>
                            <?php if (isset($optionalColumns['adresse'])): ?>
                                <td data-col="adresse">
                                    <div class="table-stack is-guarded">
                                        <span><?= e($contact['strasse']) ?></span>
                                        <span><?= e(trim($contact['plz'] . ' ' . $contact['ort'])) ?></span>
                                        <?php if (($contact['land'] ?? '') !== '' && $contact['land'] !== 'Deutschland'): ?>
                                            <span class="muted"><?= e($contact['land']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            <?php endif; ?>
                            <?php if (isset($optionalColumns['geburtstag'])): ?>
                                <td data-col="geburtstag"><span class="is-guarded"><?= e($contact['geburtstag'] ? format_date($contact['geburtstag']) : '—') ?></span></td>
                            <?php endif; ?>
                            <?php if (isset($optionalColumns['emails'])): ?>
                                <td data-col="emails">
                                    <?php if ($contact['emails'] !== []): ?>
                                        <div class="table-stack is-guarded">
                                            <?php foreach ($contact['emails'] as $email): ?>
                                                <a href="mailto:<?= e($email['email']) ?>" data-email="<?= e($email['email']) ?>"><?= e($email['email']) ?></a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="status-chip is-warn">Mail fehlt</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <?php if (isset($optionalColumns['phones'])): ?>
                                <td data-col="phones">
                                    <?php if ($contact['phones'] !== []): ?>
                                        <div class="table-stack is-guarded">
                                            <?php foreach ($contact['phones'] as $phone): ?>
                                                <a href="tel:<?= e($phone['phone']) ?>"><?= e($phone['phone']) ?></a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="muted">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <?php if (isset($optionalColumns['login'])): ?>
                                <td data-col="login">
                                    <?php if (!empty($contact['linked_user'])): ?>
                                        <div class="table-stack">
                                            <span class="is-guarded"><?= e($contact['linked_user']['email']) ?></span>
                                            <span class="muted"><?= e(role_label((string) $contact['linked_user']['role_name'])) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="muted">Kein Login</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <?php if ($canManage): ?>
                                <td class="col-open">
                                    <a class="row-open" href="<?= e(url('/contacts/edit?id=' . $contact['id'])) ?>" aria-label="<?= e(trim($contact['vorname'] . ' ' . $contact['nachname']) . ' öffnen') ?>"><?= icon('chevron-right') ?></a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($contacts === []): ?>
                        <tr><td colspan="<?= count($optionalColumns) + ($canManage ? 5 : 4) ?>" class="table-empty">Keine Kontakte für diese Ansicht.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="contacts-grid contacts-mobile">
            <?php foreach ($contacts as $contact): ?>
                <article class="contact-card" data-contact-selectable data-view="mobile" data-category-id="<?= e((string) ($contact['category_id'] ?? '')) ?>" data-tag-ids="<?= e(implode(',', array_map(static fn (array $tag): string => (string) $tag['id'], $contact['tags'] ?? []))) ?>">
                    <label class="contact-select">
                        <input type="checkbox" name="selected_contacts[]" value="<?= e((string) $contact['id']) ?>" data-contact-checkbox aria-label="<?= e(trim($contact['vorname'] . ' ' . $contact['nachname']) . ' auswählen') ?>">
                        <span aria-hidden="true">Auswählen</span>
                    </label>
                    <div class="contact-head">
                        <div class="contact-title-row">
                            <h3><?= e(trim($contact['vorname'] . ' ' . $contact['nachname'])) ?></h3>
                            <?php if (($bn = format_birth_name($contact)) !== ''): ?>
                                <span class="birth-name-inline"><?= e($bn) ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="tag"><?= e($contact['category_name'] ?: '—') ?></span>
                    </div>

                    <?= $renderChips($statusChips($contact)) ?>

                    <?php if ($contact['tags'] !== [] || ($contact['groups'] ?? []) !== []): ?>
                        <div class="tag-cluster">
                            <?php foreach ($contact['tags'] as $tag): ?>
                                <span class="tag tag-secondary" style="<?= e(tag_style($tag['name'])) ?>"><?= e($tag['name']) ?></span>
                            <?php endforeach; ?>
                            <?php foreach (($contact['groups'] ?? []) as $group): ?>
                                <span class="tag tag-group"><?= e($group['name']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($canViewPrivateDetails): ?>
                        <div class="contact-body">
                            <?php if ($visibleContactFields['address']): ?>
                                <p class="is-guarded"><?= icon('location') ?><span><?= e(contact_address_line($contact)) ?><br><?= e(contact_country_label($contact)) ?></span></p>
                            <?php endif; ?>
                            <?php if ($visibleContactFields['birthday'] && trim((string) ($contact['geburtstag'] ?? '')) !== ''): ?>
                                <p class="is-guarded"><?= icon('cake') ?><span><?= e(format_date($contact['geburtstag'])) ?></span></p>
                            <?php endif; ?>
                            <?php if ($visibleContactFields['emails'] && $contact['emails'] !== []): ?>
                                <ul class="mini-list is-guarded">
                                    <?php foreach ($contact['emails'] as $email): ?>
                                        <li data-email="<?= e($email['email']) ?>"><a href="mailto:<?= e($email['email']) ?>"><?= e($email['email']) ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <?php if ($visibleContactFields['phones'] && $contact['phones'] !== []): ?>
                                <ul class="mini-list is-guarded">
                                    <?php foreach ($contact['phones'] as $phone): ?>
                                        <li><a href="tel:<?= e($phone['phone']) ?>"><?= e((trim((string) ($phone['label'] ?? '')) !== '' ? $phone['label'] . ': ' : '') . $phone['phone']) ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <?php if ($visibleContactFields['login'] && !empty($contact['linked_user'])): ?>
                                <p class="is-guarded"><?= icon('login') ?><span><?= e($contact['linked_user']['email']) ?> · <?= e(role_label((string) $contact['linked_user']['role_name'])) ?></span></p>
                            <?php endif; ?>
                            <?php if ($visibleContactFields['notes'] && !empty($contact['notizen'])): ?>
                                <p class="note is-guarded"><?= e($contact['notizen']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($canManage): ?>
                        <div class="card-actions">
                            <a class="ghost-button" href="<?= e(url('/contacts/edit?id=' . $contact['id'])) ?>"><?= icon('edit') ?><span>Bearbeiten</span></a>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
            <?php if ($contacts === []): ?>
                <p class="table-empty">Keine Kontakte für diese Ansicht.</p>
            <?php endif; ?>
        </div>

        <?php if ($canManage): ?>
            <details class="admin-drawer bulk-edit-drawer">
                <summary><span><?= icon('sliders') ?></span><span>Sammelbearbeitung der Auswahl</span></summary>
                <div class="admin-drawer-body">
                    <div class="bulk-editor">
                        <label>
                            <span>Kategorie für Auswahl</span>
                            <select name="bulk_category_id">
                                <option value="">Kategorie unverändert</option>
                                <option value="__none__">Kategorie entfernen</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= e((string) $category['id']) ?>"><?= e($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="inline-toggle">
                            <input type="checkbox" name="bulk_category_only_if_empty" value="1">
                            <span>Nur setzen, wenn noch keine Kategorie gepflegt ist</span>
                        </label>
                        <div role="group" aria-label="Tags ergänzen">
                            <span>Tags ergänzen</span>
                            <div class="tag-picker compact-picker">
                                <?php foreach ($tags as $tag): ?>
                                    <label class="tag-option">
                                        <input type="checkbox" name="bulk_tag_ids_add[]" value="<?= e((string) $tag['id']) ?>">
                                        <span><?= e($tag['name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                                <?php if ($tags === []): ?>
                                    <p class="field-hint">Noch keine Tags angelegt.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div role="group" aria-label="Tags entfernen">
                            <span>Tags entfernen</span>
                            <div class="tag-picker compact-picker">
                                <?php foreach ($tags as $tag): ?>
                                    <label class="tag-option">
                                        <input type="checkbox" name="bulk_tag_ids_remove[]" value="<?= e((string) $tag['id']) ?>">
                                        <span><?= e($tag['name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                                <?php if ($tags === []): ?>
                                    <p class="field-hint">Noch keine Tags angelegt.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p class="field-hint" id="bulkSelectionHint" role="status">Keine Kontakte ausgewählt.</p>
                        <div class="toolbar-actions">
                            <button type="submit" formaction="<?= e(url('/contacts/bulk-update')) ?>" formmethod="post">
                                <?= icon('edit') ?><span>Auf Auswahl anwenden</span>
                            </button>
                        </div>
                        <?php if (can('groups.manage')): ?>
                            <div class="bulk-group-from-selection">
                                <label>
                                    <span>Aus der Auswahl eine neue Gruppe machen</span>
                                    <input type="text" name="group_name" maxlength="120" placeholder="Name der Gruppe">
                                </label>
                                <button type="submit" class="ghost-button" formaction="<?= e(url('/contacts/gruppe-aus-auswahl')) ?>" formmethod="post">
                                    <?= icon('contacts') ?><span>Gruppe anlegen</span>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </details>
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

<?php
$contactCount = count($contacts);
$emailCount = 0;
$phoneCount = 0;
$supportEmail = trim((string) branding_value('branding_support_email', ''));
$activeTagIds = array_map('intval', (array) ($filters['tag_ids'] ?? []));
$currentSort = (string) ($filters['sort'] ?? 'vorname');
$currentDirection = (string) ($filters['direction'] ?? 'asc');
$visibleContactFields = [
    'address' => can_view_contact_field('address'),
    'birthday' => can_view_contact_field('birthday'),
    'emails' => can_view_contact_field('emails'),
    'phones' => can_view_contact_field('phones'),
    'notes' => can_view_contact_field('notes'),
    'login' => can_view_contact_field('login'),
];
$canViewPrivateDetails = in_array(true, $visibleContactFields, true);
$detailFieldLabels = [
    'address' => 'Adresse',
    'birthday' => 'Geburtstag',
    'emails' => 'E-Mail',
    'phones' => 'Telefon',
    'notes' => 'Notizen',
    'login' => 'Login',
];
$visibleDetailLabels = [];
foreach ($detailFieldLabels as $fieldKey => $fieldLabel) {
    if ($visibleContactFields[$fieldKey]) {
        $visibleDetailLabels[] = $fieldLabel;
    }
}
$canCopyVisibleEmails = $visibleContactFields['emails'] && can('contacts.copy_emails');
$canSendRegularMail = can('mail.send');
$canSendSingleContactMail = can('mail.contact_single');
$isMemberCompactView = $canSendSingleContactMail && !can('contacts.manage');
$isStaffCompactView = !$isMemberCompactView;
$contactWithEmailCount = 0;
$contactWithoutEmailCount = 0;
foreach ($contacts as $contact) {
    $emailCount += count($contact['emails']);
    $phoneCount += count($contact['phones']);
    if (($contact['emails'] ?? []) === []) {
        $contactWithoutEmailCount++;
    } else {
        $contactWithEmailCount++;
    }
}

$buildSortUrl = static function (string $sortKey) use ($filters, $currentSort, $currentDirection): string {
    $nextDirection = $currentSort === $sortKey && $currentDirection === 'asc' ? 'desc' : 'asc';
    $query = $filters;
    $query['sort'] = $sortKey;
    $query['direction'] = $nextDirection;

    return url('/kontakte?' . http_build_query($query));
};

$sortLabel = static function (string $sortKey, string $label) use ($currentSort, $currentDirection): string {
    if ($currentSort !== $sortKey) {
        return $label;
    }

    return $label . ' ' . ($currentDirection === 'asc' ? '↑' : '↓');
};
?>
<section class="hero-card<?= $isMemberCompactView ? ' is-member-compact' : '' ?><?= $isStaffCompactView ? ' is-staff-compact' : '' ?>">
    <div class="hero-row">
        <div>
            <p class="eyebrow">Kontaktverwaltung</p>
            <h2>Alle Kontakte an einem Ort</h2>
            <p class="muted">
                <?= $canSendSingleContactMail
                    ? 'Fehlende Mailadressen erkennen und einzelne Personen diskret kontaktieren.'
                    : 'Schnell filtern, markieren, exportieren oder für Mailings verwenden.' ?>
            </p>
        </div>
        <?php if (can('contacts.manage')): ?>
            <div class="hero-actions">
                <a class="button-link" href="<?= e(url('/contacts/create')) ?>"><?= icon('plus') ?><span>Neuen Kontakt anlegen</span></a>
            </div>
        <?php endif; ?>
    </div>

    <div class="stats-grid<?= $isMemberCompactView ? ' is-member-compact' : '' ?><?= $isStaffCompactView ? ' is-staff-compact' : '' ?>">
        <article class="stat-card">
            <?= icon('contacts') ?>
            <span class="stat-label">Kontakte</span>
            <strong><?= e((string) $contactCount) ?></strong>
        </article>
        <article class="stat-card">
            <?= icon('mail') ?>
            <span class="stat-label"><?= $canSendSingleContactMail ? 'Mit Mailadresse' : 'E-Mail-Adressen' ?></span>
            <strong><?= e((string) ($canSendSingleContactMail ? $contactWithEmailCount : $emailCount)) ?></strong>
        </article>
        <article class="stat-card">
            <?= $canSendSingleContactMail ? icon('mail-off') : icon('user') ?>
            <span class="stat-label"><?= $canSendSingleContactMail ? 'Mailadresse fehlt' : 'Telefonnummern' ?></span>
            <strong><?= e((string) ($canSendSingleContactMail ? $contactWithoutEmailCount : $phoneCount)) ?></strong>
        </article>
    </div>

    <form method="get" action="<?= e(url('/kontakte')) ?>" class="filter-grid<?= $isMemberCompactView ? ' is-member-compact' : '' ?><?= $isStaffCompactView ? ' is-staff-compact' : '' ?>">
        <label>
            <span>Suche</span>
            <input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Name oder Geburtsname">
        </label>
        <label>
            <span>Kategorie</span>
            <select name="category_id">
                <option value="">Alle Kategorien</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e((string) $category['id']) ?>" <?= (string) ($filters['category_id'] ?? '') === (string) $category['id'] ? 'selected' : '' ?>>
                        <?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="filter-actions">
            <button type="submit">Filtern</button>
            <?php if (can('contacts.export')): ?>
                <a class="ghost-button" href="<?= e(url('/contacts/export?' . http_build_query($filters))) ?>">CSV exportieren</a>
            <?php endif; ?>
        </div>
        <details class="admin-drawer filter-drawer">
            <summary>
                <span><?= icon('sliders') ?></span>
                <span>Weitere Filter</span>
            </summary>
            <div class="admin-drawer-body">
                <div class="filter-advanced-grid">
                    <label>
                        <span>Sortierung</span>
                        <select name="sort">
                            <option value="vorname" <?= ($filters['sort'] ?? 'vorname') === 'vorname' ? 'selected' : '' ?>>Vorname</option>
                            <option value="nachname" <?= ($filters['sort'] ?? '') === 'nachname' ? 'selected' : '' ?>>Nachname</option>
                            <option value="category_name" <?= ($filters['sort'] ?? '') === 'category_name' ? 'selected' : '' ?>>Kategorie</option>
                            <option value="ort" <?= ($filters['sort'] ?? '') === 'ort' ? 'selected' : '' ?>>Ort</option>
                            <option value="geburtstag" <?= ($filters['sort'] ?? '') === 'geburtstag' ? 'selected' : '' ?>>Geburtstag</option>
                            <option value="created_at" <?= ($filters['sort'] ?? '') === 'created_at' ? 'selected' : '' ?>>Angelegt</option>
                        </select>
                    </label>
                    <label>
                        <span>Richtung</span>
                        <select name="direction">
                            <option value="asc" <?= ($filters['direction'] ?? 'asc') === 'asc' ? 'selected' : '' ?>>A bis Z</option>
                            <option value="desc" <?= ($filters['direction'] ?? '') === 'desc' ? 'selected' : '' ?>>Z bis A</option>
                        </select>
                    </label>
                    <div class="filter-tags">
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
                    <div class="filter-tags">
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
            </div>
        </details>
    </form>
</section>

<section class="panel contacts-view-root is-table<?= $isMemberCompactView ? ' is-member-compact' : '' ?><?= $isStaffCompactView ? ' is-staff-compact' : '' ?>" data-contacts-view-root>
        <div class="panel-head">
        <div>
            <h3>Kontaktliste</h3>
            <p class="muted"><?= $isMemberCompactView ? 'Mailstatus prüfen, Person auswählen, Kontakt starten.' : 'Auswahl, Kopieren und Mailing starten direkt aus der Übersicht.' ?></p>
        </div>
        <div class="selection-tools">
            <div class="selection-status" id="selectionStatus">Noch nichts ausgewählt</div>
            <button type="button" class="ghost-button compact-action" data-select="none"><?= icon('reset') ?><span>Auswahl aufheben</span></button>
        </div>
    </div>

    <form id="contactSelectionForm" method="post" action="<?= e(url('/mail/compose')) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <div class="bulk-layout<?= $isMemberCompactView ? ' is-member-compact' : '' ?><?= $isStaffCompactView ? ' is-staff-compact' : '' ?>">
            <div class="bulk-card<?= $isMemberCompactView ? ' is-member-compact' : '' ?><?= $isStaffCompactView ? ' is-staff-compact' : '' ?>">
                <span class="bulk-title">Schnellauswahl</span>
                <div class="toolbar-actions">
                    <button type="button" class="compact-action" data-select="all"><?= icon('check-double') ?><span>Alle auswählen</span></button>
                    <button type="button" class="ghost-button compact-action" data-select="none"><?= icon('reset') ?><span>Auswahl aufheben</span></button>
                </div>
                <?php if ($categories !== []): ?>
                    <div class="quick-category-list">
                        <?php foreach ($categories as $category): ?>
                            <button type="button" class="ghost-button compact-action" data-select-category="<?= e((string) $category['id']) ?>"><?= e($category['name']) ?> auswählen</button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($tags !== []): ?>
                    <div class="quick-category-list">
                        <?php foreach ($tags as $tag): ?>
                            <button type="button" class="ghost-button compact-action" data-select-tag="<?= e((string) $tag['id']) ?>"><?= e($tag['name']) ?> markieren</button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bulk-card accent<?= $isMemberCompactView ? ' is-member-compact' : '' ?><?= $isStaffCompactView ? ' is-staff-compact' : '' ?>">
                <span class="bulk-title">Aktionen für Auswahl</span>
                <div class="toolbar-actions">
                    <?php if ($canCopyVisibleEmails): ?>
                        <button type="button" id="copyEmailsButton"><?= icon('copy') ?><span>E-Mail-Adressen kopieren</span></button>
                    <?php endif; ?>
                    <?php if ($canSendRegularMail): ?>
                        <button type="submit" class="button-link"><?= icon('edit') ?><span>E-Mail verfassen</span></button>
                    <?php elseif ($canSendSingleContactMail): ?>
                        <button type="submit" class="button-link"><?= icon('message-send') ?><span>Person kontaktieren</span></button>
                    <?php endif; ?>
                </div>
                <?php if ($canSendSingleContactMail): ?>
                    <p class="detail-hint">Je Auswahl ist genau eine Person erlaubt. Die Zieladresse bleibt verborgen, Antworten gehen direkt an deine eigene Login-Mailadresse.</p>
                    <?php if ($isMemberCompactView): ?>
                        <ul class="compact-help-list">
                            <li><strong>Mail fehlt</strong> bedeutet: Uns liegt noch keine Adresse vor.</li>
                            <?php if ($supportEmail !== ''): ?>
                                <li>Wenn du sie kennst, bitte an <a href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a> senden.</li>
                            <?php endif; ?>
                            <li>Für eine Nachricht genau eine Person auswählen und dann <strong>Person kontaktieren</strong>.</li>
                        </ul>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (!$canCopyVisibleEmails && !$canSendRegularMail && !$canSendSingleContactMail): ?>
                    <p class="detail-hint">Für diese Rolle sind hier gerade keine Sammelaktionen freigeschaltet.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($canSendSingleContactMail && !$isMemberCompactView): ?>
            <aside class="workflow-card" aria-label="Hinweise zur Kontaktaufnahme">
                <div class="workflow-card-head">
                    <span class="workflow-icon"><?= icon('message-send') ?></span>
                    <div>
                        <strong>So funktioniert die Kontaktaufnahme</strong>
                        <p class="detail-hint">Diese Ansicht ist bewusst auf zwei Aufgaben reduziert: fehlende Mailadressen erkennen und genau eine Person kontaktieren.</p>
                    </div>
                </div>
                <ol class="workflow-list">
                    <li>Wenn neben einem Namen <strong>Mail fehlt</strong> steht, liegt uns noch keine Adresse vor.</li>
                    <?php if ($supportEmail !== ''): ?>
                        <li>Wenn du die fehlende Adresse kennst, schicke sie bitte an <a href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a>.</li>
                    <?php endif; ?>
                    <li>Für eine Kontaktaufnahme genau eine Person auswählen und <strong>Person kontaktieren</strong> klicken. Die Zieladresse bleibt verborgen, Antworten gehen an dich.</li>
                </ol>
            </aside>
        <?php endif; ?>

        <div class="table-options">
            <div>
                <strong>Ansicht</strong>
                <p class="muted">
                    <?php if ($canViewPrivateDetails): ?>
                        Auf größeren Bildschirmen ist die Tabelle kompakter und besser sortierbar.
                        <?php if ($visibleDetailLabels !== []): ?>
                            Sichtbar sind aktuell: <?= e(implode(', ', $visibleDetailLabels)) ?>.
                        <?php endif; ?>
                    <?php else: ?>
                        Diese Rolle sieht bewusst nur Namen, Kategorie und Tags. <strong>Mail fehlt</strong> markiert Kontakte ohne hinterlegte Adresse.
                    <?php endif; ?>
                </p>
            </div>
            <?php if (!$isMemberCompactView): ?>
                <div class="view-toggle" role="group" aria-label="Ansicht umschalten">
                    <button type="button" class="view-toggle-button is-active" data-view-toggle="desktop">Tabelle</button>
                    <button type="button" class="view-toggle-button" data-view-toggle="mobile">Karten</button>
                </div>
                <div class="column-toggle-list">
                    <label class="column-toggle"><input type="checkbox" data-column-toggle="category" checked><span>Kategorie</span></label>
                    <label class="column-toggle"><input type="checkbox" data-column-toggle="tags" checked><span>Tags</span></label>
                    <?php if ($visibleContactFields['address']): ?>
                        <label class="column-toggle"><input type="checkbox" data-column-toggle="adresse" checked><span>Adresse</span></label>
                    <?php endif; ?>
                    <?php if ($visibleContactFields['birthday']): ?>
                        <label class="column-toggle"><input type="checkbox" data-column-toggle="geburtstag" checked><span>Geburtstag</span></label>
                    <?php endif; ?>
                    <?php if ($visibleContactFields['emails']): ?>
                        <label class="column-toggle"><input type="checkbox" data-column-toggle="emails" checked><span>E-Mail</span></label>
                    <?php endif; ?>
                    <?php if ($visibleContactFields['phones']): ?>
                        <label class="column-toggle"><input type="checkbox" data-column-toggle="phones" checked><span>Telefon</span></label>
                    <?php endif; ?>
                    <?php if ($visibleContactFields['login']): ?>
                        <label class="column-toggle"><input type="checkbox" data-column-toggle="login" checked><span>Login</span></label>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="contacts-table-wrap">
            <table class="contacts-table">
                <thead>
                    <tr>
                        <th class="col-select">Auswahl</th>
                        <th><a class="sort-link" href="<?= e($buildSortUrl('vorname')) ?>"><?= e($sortLabel('vorname', 'Vorname')) ?></a></th>
                        <th><a class="sort-link" href="<?= e($buildSortUrl('nachname')) ?>"><?= e($sortLabel('nachname', 'Nachname')) ?></a></th>
                        <th data-col="category"><a class="sort-link" href="<?= e($buildSortUrl('category_name')) ?>"><?= e($sortLabel('category_name', 'Kategorie')) ?></a></th>
                        <th data-col="tags">Tags</th>
                        <?php if ($visibleContactFields['address']): ?>
                            <th data-col="adresse"><a class="sort-link" href="<?= e($buildSortUrl('ort')) ?>"><?= e($sortLabel('ort', 'Adresse')) ?></a></th>
                        <?php endif; ?>
                        <?php if ($visibleContactFields['birthday']): ?>
                            <th data-col="geburtstag"><a class="sort-link" href="<?= e($buildSortUrl('geburtstag')) ?>"><?= e($sortLabel('geburtstag', 'Geburtstag')) ?></a></th>
                        <?php endif; ?>
                        <?php if ($visibleContactFields['emails']): ?>
                            <th data-col="emails">E-Mail</th>
                        <?php endif; ?>
                        <?php if ($visibleContactFields['phones']): ?>
                            <th data-col="phones">Telefon</th>
                        <?php endif; ?>
                        <?php if ($visibleContactFields['login']): ?>
                            <th data-col="login">Login / Rolle</th>
                        <?php endif; ?>
                        <?php if (can('contacts.manage')): ?>
                            <th class="col-actions">Aktionen</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contacts as $contact): ?>
                        <tr class="contact-row" data-contact-selectable data-view="desktop" data-category-id="<?= e((string) ($contact['category_id'] ?? '')) ?>" data-tag-ids="<?= e(implode(',', array_map(static fn (array $tag): string => (string) $tag['id'], $contact['tags'] ?? []))) ?>">
                            <td class="col-select">
                                <label class="table-check">
                                    <input type="checkbox" name="selected_contacts[]" value="<?= e((string) $contact['id']) ?>" data-contact-checkbox>
                                </label>
                            </td>
                            <td>
                                <div class="contact-name-cell">
                                    <strong><?= e($contact['vorname']) ?></strong>
                                </div>
                            </td>
                            <td>
                                <div class="contact-name-cell">
                                    <strong><?= e($contact['nachname']) ?></strong>
                                    <?php if (!empty($contact['geburtsname']) && $contact['geburtsname'] !== $contact['nachname']): ?>
                                        <span class="birth-name-inline">(<?= e($contact['geburtsname']) ?>)</span>
                                    <?php endif; ?>
                                    <?php if (($contact['emails'] ?? []) === [] && !$visibleContactFields['emails']): ?>
                                        <span class="missing-email-badge" title="Keine Mailadresse hinterlegt" aria-label="Keine Mailadresse hinterlegt"><?= icon('mail-off') ?><span>Mail fehlt</span></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td data-col="category"><span class="table-pill"><?= e($contact['category_name'] ?: '—') ?></span></td>
                            <td data-col="tags">
                                <div class="tag-cluster">
                                    <?php foreach ($contact['tags'] as $tag): ?>
                                        <span class="tag tag-secondary" style="<?= e(tag_style($tag['name'])) ?>"><?= e($tag['name']) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <?php if ($visibleContactFields['address']): ?>
                                <td data-col="adresse">
                                    <div class="table-stack is-guarded">
                                        <span><?= e($contact['strasse']) ?></span>
                                        <span><?= e($contact['plz']) ?> <?= e($contact['ort']) ?></span>
                                        <span class="muted"><?= e($contact['land'] ?: 'Deutschland') ?></span>
                                    </div>
                                </td>
                            <?php endif; ?>
                            <?php if ($visibleContactFields['birthday']): ?>
                                <td data-col="geburtstag"><span class="is-guarded"><?= e($contact['geburtstag'] ? format_date($contact['geburtstag']) : '—') ?></span></td>
                            <?php endif; ?>
                            <?php if ($visibleContactFields['emails']): ?>
                                <td data-col="emails">
                                    <?php if ($contact['emails'] !== []): ?>
                                        <div class="table-stack is-guarded">
                                            <?php foreach ($contact['emails'] as $email): ?>
                                                <a href="mailto:<?= e($email['email']) ?>" data-email="<?= e($email['email']) ?>"><?= e($email['email']) ?></a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="missing-email-badge" title="Keine Mailadresse hinterlegt" aria-label="Keine Mailadresse hinterlegt"><?= icon('mail-off') ?><span>Mail fehlt</span></span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <?php if ($visibleContactFields['phones']): ?>
                                <td data-col="phones">
                                    <div class="table-stack is-guarded">
                                        <?php foreach ($contact['phones'] as $phone): ?>
                                            <a href="tel:<?= e($phone['phone']) ?>"><?= e($phone['phone']) ?></a>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            <?php endif; ?>
                            <?php if ($visibleContactFields['login']): ?>
                                <td data-col="login">
                                    <?php if (!empty($contact['linked_user'])): ?>
                                        <div class="table-stack">
                                            <span class="is-guarded"><?= e($contact['linked_user']['email']) ?></span>
                                            <span class="muted"><?= e($contact['linked_user']['role_name']) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="muted">Kein Login</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <?php if (can('contacts.manage')): ?>
                                <td class="col-actions">
                                    <div class="table-actions">
                                        <a class="ghost-button icon-button" title="Kontakt bearbeiten" aria-label="Kontakt bearbeiten" href="<?= e(url('/contacts/edit?id=' . $contact['id'])) ?>"><?= icon('edit') ?><span class="visually-hidden">Bearbeiten</span></a>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="contacts-grid contacts-mobile">
            <?php foreach ($contacts as $contact): ?>
                <article class="contact-card" data-contact-selectable data-view="mobile" data-category-id="<?= e((string) ($contact['category_id'] ?? '')) ?>" data-tag-ids="<?= e(implode(',', array_map(static fn (array $tag): string => (string) $tag['id'], $contact['tags'] ?? []))) ?>">
                    <label class="contact-select">
                        <input type="checkbox" name="selected_contacts[]" value="<?= e((string) $contact['id']) ?>" data-contact-checkbox>
                        <span>Auswählen</span>
                    </label>
                    <div class="contact-head">
                        <div>
                            <div class="contact-title-row">
                                <h3><?= e($contact['vorname'] . ' ' . $contact['nachname']) ?></h3>
                                <?php if (!empty($contact['geburtsname']) && $contact['geburtsname'] !== $contact['nachname']): ?>
                                    <span class="birth-name-inline">(<?= e($contact['geburtsname']) ?>)</span>
                                <?php endif; ?>
                                <?php if (($contact['emails'] ?? []) === [] && !$visibleContactFields['emails']): ?>
                                    <span class="missing-email-badge" title="Keine Mailadresse hinterlegt" aria-label="Keine Mailadresse hinterlegt"><?= icon('mail-off') ?><span>Mail fehlt</span></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="tag"><?= e($contact['category_name'] ?: '—') ?></span>
                    </div>
                    <div class="tag-cluster">
                        <?php foreach ($contact['tags'] as $tag): ?>
                            <span class="tag tag-secondary" style="<?= e(tag_style($tag['name'])) ?>"><?= e($tag['name']) ?></span>
                        <?php endforeach; ?>
                        <?php if ($visibleContactFields['login'] && !empty($contact['linked_user'])): ?>
                            <span class="tag tag-account<?= (int) $contact['linked_user']['is_active'] === 1 ? ' is-active' : '' ?>">
                                <?= e($contact['linked_user']['role_name']) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="contact-body">
                        <?php if ($visibleContactFields['address'] || $visibleContactFields['birthday']): ?>
                            <div class="contact-meta-list is-guarded">
                                <?php if ($visibleContactFields['address']): ?>
                                    <p><?= icon('location') ?><span><strong><?= e(contact_value_label((int) ((trim((string) ($contact['strasse'] ?? '')) !== '' || trim((string) ($contact['plz'] ?? '')) !== '' || trim((string) ($contact['ort'] ?? '')) !== '') ? 1 : 0), 'Adresse', 'Adresse', 'Adressen')) ?></strong><?= e(contact_address_line($contact)) ?></span></p>
                                    <p><?= icon('globe') ?><span><?= e(contact_country_label($contact)) ?></span></p>
                                <?php endif; ?>
                                <?php if ($visibleContactFields['birthday']): ?>
                                    <p class="muted"><?= icon('cake') ?><span><?= e($contact['geburtstag'] ? format_date($contact['geburtstag']) : 'Kein Geburtstag hinterlegt') ?></span></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($visibleContactFields['emails']): ?>
                            <div>
                                <strong><?= e(contact_value_label(count($contact['emails']), 'E-Mail-Adresse', 'E-Mail-Adresse', 'E-Mail-Adressen')) ?></strong>
                                <?php if ($contact['emails'] !== []): ?>
                                    <ul class="mini-list is-guarded">
                                        <?php foreach ($contact['emails'] as $email): ?>
                                            <li data-email="<?= e($email['email']) ?>"><a href="mailto:<?= e($email['email']) ?>"><?= e($email['email']) ?></a></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="missing-contact-value"><span class="missing-email-badge" title="Keine Mailadresse hinterlegt" aria-label="Keine Mailadresse hinterlegt"><?= icon('mail-off') ?><span>Mail fehlt</span></span></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($visibleContactFields['phones']): ?>
                            <div>
                                <strong><?= e(contact_value_label(count($contact['phones']), 'Telefonnummer', 'Telefonnummer', 'Telefonnummern')) ?></strong>
                                <?php if ($contact['phones'] !== []): ?>
                                    <ul class="mini-list is-guarded">
                                        <?php foreach ($contact['phones'] as $phone): ?>
                                            <li><a href="tel:<?= e($phone['phone']) ?>"><?= e((trim((string) ($phone['label'] ?? '')) !== '' ? $phone['label'] . ': ' : '') . $phone['phone']) ?></a></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="missing-contact-value"><?= icon('phone-off') ?><span>–</span></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($visibleContactFields['login'] && !empty($contact['linked_user'])): ?>
                            <div class="account-summary">
                                <strong>Login</strong>
                                <p class="is-guarded"><?= e($contact['linked_user']['email']) ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if ($visibleContactFields['notes'] && !empty($contact['notizen'])): ?><p class="note is-guarded"><?= e($contact['notizen']) ?></p><?php endif; ?>

                        <?php if (!$canViewPrivateDetails): ?>
                            <p class="detail-hint">Weitere Kontaktdaten sind für diese Rolle ausgeblendet.</p>
                        <?php endif; ?>
                    </div>

                    <?php if (can('contacts.manage')): ?>
                        <div class="card-actions">
                            <a class="ghost-button icon-button" title="Kontakt bearbeiten" aria-label="Kontakt bearbeiten" href="<?= e(url('/contacts/edit?id=' . $contact['id'])) ?>"><?= icon('edit') ?><span class="visually-hidden">Bearbeiten</span></a>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if (can('contacts.manage')): ?>
            <details class="admin-drawer">
                <summary>
                    <span><?= icon('sliders') ?></span>
                    <span>Massenbearbeitung und Verwaltung</span>
                </summary>
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
                        <div>
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
                        <div>
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
                        <p class="field-hint" id="bulkSelectionHint">Keine Kontakte ausgewählt.</p>
                        <div class="toolbar-actions">
                            <button type="submit" formaction="<?= e(url('/contacts/bulk-update')) ?>" formmethod="post">
                                <?= icon('edit') ?><span>Auf Auswahl anwenden</span>
                            </button>
                        </div>
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
                <span>Kategorien und Tags verwalten</span>
            </summary>
            <div class="admin-drawer-body stack">
                <div>
                    <h3>Kategorie ergänzen</h3>
                    <form method="post" action="<?= e(url('/categories/store')) ?>" class="inline-form">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <input type="text" name="name" placeholder="Neue Kategorie" required>
                        <button type="submit">Speichern</button>
                    </form>
                </div>
                <div>
                    <h3>Tag ergänzen</h3>
                    <form method="post" action="<?= e(url('/tags/store')) ?>" class="inline-form">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <input type="text" name="name" placeholder="Neuer Tag" required>
                        <button type="submit">Speichern</button>
                    </form>
                </div>
            </div>
        </details>
    </section>
<?php endif; ?>

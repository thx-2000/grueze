<?php
/**
 * Tabellenansicht des Adressbuchs (Desktop). Zusatzspalten kommen aus
 * $optionalColumns und werden clientseitig über „Spalten" zu-/abgeschaltet.
 *
 * @var array<int,array<string,mixed>> $contacts
 * @var array<string,string> $optionalColumns
 * @var callable $statusChips
 * @var callable $renderChips
 * @var callable $buildSortUrl
 * @var callable $ariaSort
 * @var bool $canManage
 */
?>
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

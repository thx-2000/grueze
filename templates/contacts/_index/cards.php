<?php
/**
 * Kartenansicht des Adressbuchs (Mobil / umschaltbar). Zeigt je nach
 * Rollen-Sichtbarkeit auch die Kontaktdetails, sonst nur Status und Tags.
 *
 * @var array<int,array<string,mixed>> $contacts
 * @var array<string,bool> $visibleContactFields
 * @var bool $canViewPrivateDetails
 * @var callable $statusChips
 * @var callable $renderChips
 * @var bool $canManage
 */
?>
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

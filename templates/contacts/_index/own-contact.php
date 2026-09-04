<?php
/**
 * „Deine Kontaktdaten" – der eigene verknüpfte Kontakt, auch für Rollen
 * sichtbar, die sonst nichts sehen. Notizen sind bewusst ausgenommen.
 *
 * @var array<string,mixed>|null $ownContact
 * @var bool $canManage
 * @var string $supportEmail
 */
$ownFields = $ownContact !== null ? [
    'address'  => can_view_contact_field('address', $ownContact),
    'birthday' => can_view_contact_field('birthday', $ownContact),
    'emails'   => can_view_contact_field('emails', $ownContact),
    'phones'   => can_view_contact_field('phones', $ownContact),
    'login'    => can_view_contact_field('login', $ownContact),
] : [];

if ($ownContact === null || !in_array(true, $ownFields, true)) {
    return;
}
?>
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
        <?php if (trim((string) ($ownContact['beruf'] ?? '')) !== ''): ?>
            <div><dt>Beruf/Tätigkeit</dt><dd><?= e((string) $ownContact['beruf']) ?></dd></div>
        <?php endif; ?>
        <?php if (trim((string) ($ownContact['webseite'] ?? '')) !== ''): ?>
            <div><dt>Webseite</dt><dd><a href="<?= e((string) $ownContact['webseite']) ?>" target="_blank" rel="noopener noreferrer"><?= e(preg_replace('#^https?://#i', '', (string) $ownContact['webseite'])) ?></a></dd></div>
        <?php endif; ?>
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

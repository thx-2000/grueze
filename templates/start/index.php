<?php
$firstName = trim((string) ($currentUser['name'] ?? ''));
$firstName = $firstName !== '' ? explode(' ', $firstName)[0] : '';
$canManage = can('contacts.manage');
$canMail = can('mail.send');
?>
<section class="start-hero">
    <p class="eyebrow">Willkommen<?= $firstName !== '' ? ', ' . e($firstName) : '' ?></p>
    <h2>Was möchtest du tun?</h2>

    <form method="get" action="<?= e(url('/kontakte')) ?>" class="start-search" role="search">
        <label for="startSearch" class="visually-hidden">Kontakt suchen</label>
        <?= icon('search') ?>
        <input type="search" id="startSearch" name="q" placeholder="Kontakt suchen – Name, Geburtsname …" autocomplete="off" autofocus>
        <button type="submit">Suchen</button>
    </form>

    <div class="start-actions">
        <?php if ($canManage): ?>
            <a class="button-link" href="<?= e(url('/contacts/create')) ?>"><?= icon('plus') ?><span>Neuen Kontakt anlegen</span></a>
        <?php endif; ?>
        <?php if ($canMail): ?>
            <form method="post" action="<?= e(url('/mail/compose-all')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <button type="submit" class="ghost-button"><?= icon('mail') ?><span>Rundmail schreiben</span></button>
            </form>
        <?php endif; ?>
        <a class="ghost-button" href="<?= e(url('/kontakte')) ?>"><?= icon('contacts') ?><span>Alle Kontakte</span></a>
    </div>
</section>

<section class="start-stats" aria-label="Kennzahlen">
    <a class="start-stat" href="<?= e(url('/kontakte')) ?>">
        <span class="start-stat-value"><?= e((string) $stats['total']) ?></span>
        <span class="start-stat-label">Kontakte gesamt</span>
    </a>
    <a class="start-stat<?= $stats['without_email'] > 0 ? ' is-attention' : '' ?>" href="<?= e(url('/kontakte?without_email=1')) ?>">
        <span class="start-stat-value"><?= e((string) $stats['without_email']) ?></span>
        <span class="start-stat-label">ohne Mailadresse</span>
    </a>
    <a class="start-stat<?= $stats['without_phone'] > 0 ? ' is-attention' : '' ?>" href="<?= e(url('/kontakte?without_phone=1')) ?>">
        <span class="start-stat-value"><?= e((string) $stats['without_phone']) ?></span>
        <span class="start-stat-label">ohne Handynummer</span>
    </a>
</section>

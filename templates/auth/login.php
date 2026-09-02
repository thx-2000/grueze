<?php
$branding = app_branding();
$publicSiteLabel = trim((string) ($branding['branding_public_site_label'] ?? ''));
$publicSiteUrl = trim((string) ($branding['branding_public_site_url'] ?? ''));
$loginIntro = trim((string) ($branding['branding_login_intro'] ?? ''));
$loginPublicHint = trim((string) ($branding['branding_login_public_hint'] ?? ''));
$loginHeadline = trim((string) ($branding['branding_login_headline'] ?? ''));
?>
<header class="page-head">
    <p class="eyebrow">Anmeldung</p>
    <h1><?= e($loginHeadline !== '' ? $loginHeadline : 'Interner Bereich') ?></h1>
    <p class="muted"><?= e($loginIntro !== '' ? $loginIntro : 'Hier pflegt ihr Kontakte, Mailings und interne Organisationsdaten.') ?></p>
</header>

<section class="panel stack auth-card">
    <form method="post" action="<?= e(url('/login')) ?>" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <label>
            <span>E-Mail-Adresse</span>
            <input type="email" name="email" required autocomplete="email">
        </label>
        <label>
            <span>Passwort</span>
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit"><?= icon('login') ?><span>Anmelden</span></button>
    </form>

    <?php if (!empty($passkeysAvailable)): ?>
        <div class="auth-alt">
            <button
                type="button"
                class="ghost-button"
                data-passkey-login
                data-options-url="<?= e(url('/passkeys/auth/options')) ?>"
                data-auth-url="<?= e(url('/passkeys/authenticate')) ?>"
            >
                <?= icon('passkey') ?><span>Mit Passkey anmelden</span>
            </button>
            <p class="detail-hint">Schneller per Face ID, Touch ID, Windows Hello oder Sicherheitsschlüssel – sofern für dein Konto ein Passkey hinterlegt ist.</p>
        </div>
    <?php endif; ?>

    <div class="link-row">
        <a href="<?= e(url('/forgot-password')) ?>">Passwort vergessen?</a>
        <a href="<?= e(url('/registrieren')) ?>">Noch keinen Zugang?</a>
    </div>
</section>

<?php if ($publicSiteUrl !== ''): ?>
    <p class="auth-aside">
        <?= e($loginPublicHint !== '' ? $loginPublicHint : 'Infos zur Gruppe und die öffentliche Startseite:') ?>
        <a href="<?= e($publicSiteUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($publicSiteLabel !== '' ? $publicSiteLabel : $publicSiteUrl) ?></a>
    </p>
<?php endif; ?>
<?php if (empty($adminExists)): ?>
    <p class="auth-aside"><a href="<?= e(url('/setup/admin')) ?>">Ersten Admin anlegen</a></p>
<?php endif; ?>

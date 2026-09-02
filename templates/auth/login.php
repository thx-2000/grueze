<?php
$branding = app_branding();
$publicSiteLabel = trim((string) ($branding['branding_public_site_label'] ?? ''));
$publicSiteUrl = trim((string) ($branding['branding_public_site_url'] ?? ''));
$loginIntro = trim((string) ($branding['branding_login_intro'] ?? ''));
$loginPublicHint = trim((string) ($branding['branding_login_public_hint'] ?? ''));
$loginHeadline = trim((string) ($branding['branding_login_headline'] ?? ''));
?>
<section class="hero-card narrow">
    <div class="hero-row">
        <div>
            <p class="eyebrow">Anmeldung</p>
            <h2><?= e($loginHeadline !== '' ? $loginHeadline : 'Interner Bereich') ?></h2>
            <p class="muted"><?= e($loginIntro !== '' ? $loginIntro : 'Hier pflegt ihr Kontakte, Mailings und interne Organisationsdaten.') ?></p>
        </div>
        <div class="floating-icon"><?= icon('mail-open') ?></div>
    </div>

    <?php if ($publicSiteUrl !== ''): ?>
        <div class="subsection-card">
            <strong>Öffentliche Seite</strong>
            <p class="detail-hint">
                <?= e($loginPublicHint !== '' ? $loginPublicHint : 'Infos zur Gruppe und die öffentliche Startseite findet ihr hier.') ?>
                <a href="<?= e($publicSiteUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($publicSiteLabel !== '' ? $publicSiteLabel : $publicSiteUrl) ?></a>.
            </p>
        </div>
    <?php endif; ?>

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
        <div class="subsection-card passkey-login-box">
            <strong>Schnell anmelden</strong>
            <p class="detail-hint">Wenn für dein Konto bereits ein Passkey hinterlegt ist, kannst du dich damit direkt per Gerätefreigabe anmelden.</p>
            <button
                type="button"
                class="ghost-button"
                data-passkey-login
                data-options-url="<?= e(url('/passkeys/auth/options')) ?>"
                data-auth-url="<?= e(url('/passkeys/authenticate')) ?>"
            >
                <?= icon('passkey') ?><span>Mit Passkey anmelden</span>
            </button>
        </div>
    <?php endif; ?>

    <div class="link-row">
        <p><a href="<?= e(url('/forgot-password')) ?>">Passwort vergessen?</a></p>
        <p><a href="<?= e(url('/registrieren')) ?>">Noch keinen Zugang?</a></p>
        <?php if ($publicSiteUrl !== ''): ?>
            <p><a href="<?= e($publicSiteUrl) ?>" target="_blank" rel="noopener noreferrer">Zur öffentlichen Seite</a></p>
        <?php endif; ?>
    </div>
    <?php if (empty($adminExists)): ?>
        <p><a href="<?= e(url('/setup/admin')) ?>">Ersten Admin anlegen</a></p>
    <?php endif; ?>
</section>

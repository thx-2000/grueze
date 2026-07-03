<?php
$flashes = [
    'success' => flash('success'),
    'error' => flash('error'),
];
$pageErrors = errors();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$globalSearchQuery = trim((string) ($_GET['q'] ?? ''));
$branding = app_branding();
$appName = (string) ($branding['branding_app_name'] ?? config('app.name', 'Adress-Zentrale'));
$shortName = trim((string) ($branding['branding_short_name'] ?? 'App'));
$publicSiteLabel = trim((string) ($branding['branding_public_site_label'] ?? ''));
$publicSiteUrl = trim((string) ($branding['branding_public_site_url'] ?? ''));
$sidebarCopy = trim((string) ($branding['branding_sidebar_copy'] ?? ''));
$supportEmail = trim((string) ($branding['branding_support_email'] ?? ''));
$logoPath = trim((string) ($branding['branding_logo_path'] ?? ''));
$appVersion = system_version();
$systemLabel = system_label();
$themeStyle = branding_theme_style();
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($appName) ?></title>
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/theme.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/app.css')) ?>">
    <?php if ($themeStyle !== ''): ?>
        <style><?= $themeStyle ?></style>
    <?php endif; ?>
</head>
<body>
    <div class="signal-bar">
        <div class="signal-bar-inner">
            <div class="signal-bar-main">
                <a class="signal-bar-label" href="<?= e(url('/')) ?>"><?= e($appName) ?></a>
                <?php if (!empty($currentUser)): ?>
                    <form method="get" action="<?= e(url('/search')) ?>" class="signal-search">
                        <input type="search" name="q" value="<?= e($globalSearchQuery) ?>" placeholder="Global suchen: Kontakte, Benutzer ...">
                        <button type="submit" class="signal-bar-button"><?= icon('search') ?><span>Suchen</span></button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="signal-bar-secondary">
                <div class="signal-bar-userzone">
                    <?php if (!empty($currentUser)): ?>
                        <?php if (!empty($isImpersonating) && !empty($originalUser)): ?>
                            <span class="signal-bar-meta">Angemeldet als <?= e($currentUser['name']) ?></span>
                            <form method="post" action="<?= e(url('/users/impersonate/stop')) ?>">
                                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                <button type="submit" class="signal-bar-button">Zurück zu <?= e($originalUser['name']) ?></button>
                            </form>
                        <?php else: ?>
                            <span class="signal-bar-meta">Angemeldet als <?= e($currentUser['name']) ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php if (!empty($currentUser)): ?>
                    <div class="signal-bar-tools">
                        <?php if (!empty($signalHint)): ?>
                            <span class="signal-bar-hint"><?= e($signalHint) ?></span>
                        <?php endif; ?>
                        <span id="signalSelectionStatus" class="signal-bar-hint" hidden></span>
                        <div class="signal-bar-actions">
                            <?php if (can('mail.send')): ?>
                                <form method="post" action="<?= e(url('/mail/compose-all')) ?>">
                                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                    <button type="submit" class="signal-bar-button"><?= icon('mail') ?><span>Neue Mail an alle</span></button>
                                </form>
                            <?php endif; ?>
                            <button type="submit" id="signalComposeSelection" form="contactSelectionForm" class="signal-bar-button" hidden><?= icon('mail') ?><span>Mail an Auswahl</span></button>
                            <button type="button" id="signalClearSelection" class="signal-bar-button" data-select="none" hidden><?= icon('reset') ?><span>Auswahl aufheben</span></button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="page-shell">
        <aside class="sidebar">
            <a class="sidebar-brand" href="<?= e(url('/')) ?>">
                <?php if ($logoPath !== ''): ?>
                    <span class="brand-mark brand-mark-image">
                        <img src="<?= e(asset_url('/' . ltrim($logoPath, '/'))) ?>" alt="<?= e($shortName !== '' ? $shortName : $appName) ?>">
                    </span>
                <?php else: ?>
                    <span class="brand-mark"><?= e($shortName !== '' ? $shortName : $appName) ?></span>
                <?php endif; ?>
                <div>
                    <p class="eyebrow">Organisation</p>
                    <h1><?= e($appName) ?></h1>
                    <p class="muted sidebar-copy"><?= e($sidebarCopy !== '' ? $sidebarCopy : 'Kontakte, Mailings und Organisation an einem Ort.') ?></p>
                </div>
            </a>
            <?php if (!empty($currentUser)): ?>
                <nav class="nav">
                    <?php if (($currentUser['role_name'] ?? '') === 'stufenmitglied' && $publicSiteUrl !== ''): ?>
                        <a href="<?= e($publicSiteUrl) ?>" target="_blank" rel="noopener noreferrer"><?= icon('globe') ?><span><?= e($publicSiteLabel !== '' ? $publicSiteLabel : 'Startseite') ?></span></a>
                    <?php endif; ?>
                    <a class="<?= $currentPath === '/' ? 'is-active' : '' ?>" href="<?= e(url('/')) ?>"><?= icon('contacts') ?><span>Kontakte</span></a>
                    <?php if (can('users.manage')): ?><a class="<?= str_starts_with($currentPath, '/settings/branding') ? 'is-active' : '' ?>" href="<?= e(url('/settings/branding')) ?>"><?= icon('sparkles') ?><span>Design & Branding</span></a><?php endif; ?>
                    <?php if (can('settings.manage')): ?><a class="<?= str_starts_with($currentPath, '/settings/mail-footer') ? 'is-active' : '' ?>" href="<?= e(url('/settings/mail-footer')) ?>"><?= icon('sliders') ?><span>Mail-Einstellungen</span></a><?php endif; ?>
                    <?php if (can('users.manage')): ?><a class="<?= str_starts_with($currentPath, '/users') ? 'is-active' : '' ?>" href="<?= e(url('/users')) ?>"><?= icon('user') ?><span>Benutzer</span></a><?php endif; ?>
                    <?php if (can('audit.view')): ?><a class="<?= str_starts_with($currentPath, '/logs/audit') ? 'is-active' : '' ?>" href="<?= e(url('/logs/audit')) ?>"><?= icon('history') ?><span>Audit-Log</span></a><?php endif; ?>
                    <?php if (can('mail.view_log')): ?><a class="<?= str_starts_with($currentPath, '/logs/mail') ? 'is-active' : '' ?>" href="<?= e(url('/logs/mail')) ?>"><?= icon('mail') ?><span>Versandprotokoll</span></a><?php endif; ?>
                </nav>
                <div class="sidebar-footer">
                    <a class="profile-chip<?= str_starts_with($currentPath, '/account') ? ' is-active' : '' ?>" href="<?= e(url('/account')) ?>">
                        <strong><?= e($currentUser['name']) ?></strong>
                        <span><?= e($currentUser['role_name'] ?? '') ?></span>
                        <small>Konto verwalten</small>
                    </a>
                    <form method="post" action="<?= e(url('/logout')) ?>">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit" class="ghost-button">Abmelden</button>
                    </form>
                </div>
            <?php endif; ?>
        </aside>

        <main class="content">
            <header class="content-topbar">
                <div>
                    <p class="eyebrow">Arbeitsbereich</p>
                    <h2 class="topbar-title"><a href="<?= e(url('/')) ?>"><?= e($appName) ?></a></h2>
                </div>
            </header>
            <?php foreach ($flashes as $type => $message): ?>
                <?php if ($message): ?>
                    <div class="flash flash-<?= e($type) ?>"><?= e($message) ?></div>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($pageErrors !== []): ?>
                <div class="flash flash-error">
                    <?= e(implode(' ', array_values($pageErrors))) ?>
                </div>
            <?php endif; ?>

            <?php require $templatePath; ?>
        </main>
    </div>

    <div class="privacy-note<?= !empty($currentUser) ? ' is-authenticated' : '' ?>">
        <div class="privacy-note-shell">
            <?php if (!empty($currentUser)): ?>
                <div class="privacy-note-spacer" aria-hidden="true"></div>
            <?php endif; ?>
            <div class="privacy-note-inner">
            <a href="<?= e(url('/impressum')) ?>">Impressum</a>
            <span>|</span>
            <a href="<?= e(url('/datenschutz')) ?>">Datenschutz</a>
            <?php if ($appVersion !== ''): ?>
                <span>|</span>
                <span class="privacy-note-version"><?= e($systemLabel) ?> v.<?= e($appVersion) ?></span>
            <?php endif; ?>
            <?php if ($publicSiteUrl !== ''): ?>
                <span>|</span>
                <a href="<?= e($publicSiteUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($publicSiteLabel !== '' ? $publicSiteLabel : $publicSiteUrl) ?></a>
            <?php endif; ?>
            <?php if ($supportEmail !== ''): ?>
                <span>|</span>
                <a href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a>
            <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="toast" class="toast" hidden></div>
    <script>
        window.APP = {
            csrfToken: <?= json_encode($csrfToken, JSON_THROW_ON_ERROR) ?>,
            batchUrl: <?= json_encode(url('/mail/batch'), JSON_THROW_ON_ERROR) ?>
        };
    </script>
    <script src="<?= e(asset_url('/assets/js/app.js')) ?>" defer></script>
</body>
</html>

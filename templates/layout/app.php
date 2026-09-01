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

// Seiten-Titel: Template darf via $pageTitle einen genaueren Wert vorgeben
// (z. B. Kontaktname), sonst greift die zentrale Abschnittsliste.
$sectionTitle = trim((string) ($pageTitle ?? '')) !== ''
    ? trim((string) $pageTitle)
    : page_title($currentPath);
$documentTitle = $sectionTitle !== '' ? $sectionTitle . ' · ' . $appName : $appName;
$canonicalPath = rtrim($currentPath, '/') ?: '/';
$metaDescription = trim((string) ($branding['branding_login_intro'] ?? ''));
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($documentTitle) ?></title>
    <?php /* Interne Anwendung: keine öffentlichen Inhalte, nie indexieren. */ ?>
    <meta name="robots" content="noindex, nofollow">
    <link rel="canonical" href="<?= e(url($canonicalPath)) ?>">
    <?php if ($metaDescription !== ''): ?>
        <meta name="description" content="<?= e($metaDescription) ?>">
    <?php endif; ?>
    <link rel="icon" href="<?= e(theme_favicon()) ?>">
    <script>
        // Blickschutz-Zustand vor dem ersten Paint setzen, damit Kontaktdaten nicht kurz aufblitzen.
        try {
            if (window.localStorage.getItem('grueze_privacy_guard') === 'on') {
                document.documentElement.dataset.privacyGuard = 'on';
            }
        } catch (error) {}
    </script>
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/theme.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/app.css')) ?>">
    <?php if ($themeStyle !== ''): ?>
        <style><?= $themeStyle ?></style>
    <?php endif; ?>
</head>
<body>
    <a class="skip-link" href="#main">Zum Inhalt springen</a>
    <div class="signal-bar">
        <div class="signal-bar-inner">
            <div class="signal-bar-main">
                <?php if (!empty($currentUser)): ?>
                    <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="pageSidebar">
                        <span class="nav-toggle-icon nav-toggle-icon--menu"><?= icon('menu') ?></span>
                        <span class="nav-toggle-icon nav-toggle-icon--close"><?= icon('close') ?></span>
                        <span class="nav-toggle-label">Menü</span>
                    </button>
                <?php endif; ?>
                <a class="signal-bar-label" href="<?= e(url('/')) ?>"><?= e($appName) ?></a>
                <?php if (!empty($currentUser)): ?>
                    <form method="get" action="<?= e(url('/search')) ?>" class="signal-search" role="search">
                        <label class="visually-hidden" for="globalSearch">Global suchen: Kontakte, Benutzer</label>
                        <input type="search" id="globalSearch" name="q" value="<?= e($globalSearchQuery) ?>" placeholder="Global suchen: Kontakte, Benutzer ...">
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
                        <button type="button" id="privacyGuardToggle" class="signal-bar-button" aria-pressed="false" title="Kontaktdaten (E-Mail, Telefon, Adresse, Geburtstag, Notizen) aus- oder einblenden – falls jemand mitliest">
                            <?= icon('eye-off') ?><span data-privacy-guard-label>Blickschutz</span>
                        </button>
                        <?php if (!empty($signalHint)): ?>
                            <span class="signal-bar-hint"><?= e($signalHint) ?></span>
                        <?php endif; ?>
                        <span id="signalSelectionStatus" class="signal-bar-hint" role="status" hidden></span>
                        <div class="signal-bar-actions">
                            <button type="submit" id="signalComposeSelection" form="contactSelectionForm" class="signal-bar-button" hidden><?= icon('mail') ?><span>Mail an Auswahl</span></button>
                            <button type="button" id="signalClearSelection" class="signal-bar-button" data-select="none" hidden><?= icon('reset') ?><span>Auswahl aufheben</span></button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="nav-backdrop" hidden></div>
    <div class="page-shell">
        <aside class="sidebar" id="pageSidebar">
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
                <?php
                $showAdminHub = can('users.manage') || can('settings.manage') || can('audit.view') || can('mail.view_log');
                $onContacts = $currentPath === '/kontakte' || str_starts_with($currentPath, '/kontakte/')
                    || str_starts_with($currentPath, '/contacts') || str_starts_with($currentPath, '/search');
                $onRundmail = str_starts_with($currentPath, '/rundmail') || str_starts_with($currentPath, '/mail');
                $onAdminHub = str_starts_with($currentPath, '/verwaltung')
                    || str_starts_with($currentPath, '/settings') || str_starts_with($currentPath, '/admin')
                    || str_starts_with($currentPath, '/users') || str_starts_with($currentPath, '/logs');
                ?>
                <nav class="nav">
                    <?php if (($currentUser['role_name'] ?? '') === 'stufenmitglied' && $publicSiteUrl !== ''): ?>
                        <a href="<?= e($publicSiteUrl) ?>" target="_blank" rel="noopener noreferrer"><?= icon('globe') ?><span><?= e($publicSiteLabel !== '' ? $publicSiteLabel : 'Startseite') ?></span></a>
                    <?php endif; ?>
                    <a class="<?= $currentPath === '/' ? 'is-active' : '' ?>" href="<?= e(url('/')) ?>"><?= icon('home') ?><span>Start</span></a>
                    <a class="<?= $onContacts ? 'is-active' : '' ?>" href="<?= e(url('/kontakte')) ?>"><?= icon('contacts') ?><span>Kontakte</span></a>
                    <?php if (can('mail.send')): ?>
                        <a class="<?= $onRundmail ? 'is-active' : '' ?>" href="<?= e(url('/rundmail')) ?>"><?= icon('mail') ?><span>Rundmail</span></a>
                    <?php endif; ?>
                    <?php if ($showAdminHub): ?>
                        <a class="<?= $onAdminHub ? 'is-active' : '' ?>" href="<?= e(url('/verwaltung')) ?>"><?= icon('sliders') ?><span>Verwaltung</span></a>
                    <?php endif; ?>
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
                    <?php if ($systemLabel !== ''): ?>
                        <p class="sidebar-product">läuft mit
                            <a href="<?= e(product_url()) ?>" target="_blank" rel="noopener noreferrer"><?= e($systemLabel) ?></a>
                            <span class="sidebar-product-version">v<?= e($appVersion) ?></span>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </aside>

        <main class="content" id="main">
            <header class="content-topbar">
                <div>
                    <p class="eyebrow">Arbeitsbereich</p>
                    <p class="topbar-title"><a href="<?= e(url('/')) ?>"><?= e($appName) ?></a></p>
                </div>
            </header>
            <?php foreach ($flashes as $type => $message): ?>
                <?php if ($message): ?>
                    <div class="flash flash-<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>"><?= e($message) ?></div>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($pageErrors !== []): ?>
                <div class="flash flash-error" role="alert">
                    <?= e(implode(' ', array_values($pageErrors))) ?>
                </div>
            <?php endif; ?>

            <?php require $templatePath; ?>
        </main>
    </div>

    <footer class="site-footer<?= !empty($currentUser) ? ' is-authenticated' : '' ?>">
        <div class="site-footer-shell">
            <?php if (!empty($currentUser)): ?>
                <div class="site-footer-spacer" aria-hidden="true"></div>
            <?php endif; ?>
            <div class="site-footer-inner">
            <a href="<?= e(url('/impressum')) ?>">Impressum</a>
            <span aria-hidden="true">|</span>
            <a href="<?= e(url('/datenschutz')) ?>">Datenschutz</a>
            <?php if ($appVersion !== ''): ?>
                <span aria-hidden="true">|</span>
                <span class="site-footer-version"<?= $systemLabel === 'GRUEZE' ? ' title="GRUEZE: Anspielung auf „Grüezi“ und Kurzform von „Grüß-Zentrale“"' : '' ?>><?= $systemLabel !== '' ? e($systemLabel) . ' ' : '' ?>v<?= e($appVersion) ?></span>
            <?php endif; ?>
            <?php if ($publicSiteUrl !== ''): ?>
                <span aria-hidden="true">|</span>
                <a href="<?= e($publicSiteUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($publicSiteLabel !== '' ? $publicSiteLabel : $publicSiteUrl) ?></a>
            <?php endif; ?>
            <?php if ($supportEmail !== ''): ?>
                <span aria-hidden="true">|</span>
                <a href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a>
            <?php endif; ?>
            </div>
        </div>
    </footer>

    <div id="toast" class="toast" role="status" aria-live="polite" hidden></div>
    <script>
        window.APP = {
            csrfToken: <?= json_encode($csrfToken, JSON_THROW_ON_ERROR) ?>,
            batchUrl: <?= json_encode(url('/mail/batch'), JSON_THROW_ON_ERROR) ?>
        };
    </script>
    <script src="<?= e(asset_url('/assets/js/app.js')) ?>" defer></script>
</body>
</html>

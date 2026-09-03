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
    <script nonce="<?= e(csp_nonce()) ?>">
        // Blickschutz-Zustand vor dem ersten Paint setzen, damit Kontaktdaten nicht kurz aufblitzen.
        try {
            if (window.localStorage.getItem('grueze_privacy_guard') === 'on') {
                document.documentElement.dataset.privacyGuard = 'on';
            }
        } catch (error) {}
    </script>
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/fonts.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/theme.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/app.css')) ?>">
    <?php if ($themeStyle !== ''): ?>
        <style><?= $themeStyle ?></style>
    <?php endif; ?>
</head>
<body>
    <a class="skip-link" href="#main">Zum Inhalt springen</a>
    <?php
    $navInitials = '';
    if (!empty($currentUser['name'])) {
        $parts = preg_split('/\s+/', trim((string) $currentUser['name'])) ?: [];
        $navInitials = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr(end($parts) ?: '', 0, 1));
    }
    $showAdminHub = !empty($currentUser) && (can('users.manage') || can('settings.manage') || can('audit.view') || can('mail.view_log'));
    $onContacts = $currentPath === '/kontakte' || str_starts_with($currentPath, '/kontakte/')
        || str_starts_with($currentPath, '/contacts') || str_starts_with($currentPath, '/search')
        || str_starts_with($currentPath, '/vollstaendigkeit');
    $onRundmail = str_starts_with($currentPath, '/rundmail') || str_starts_with($currentPath, '/mail');
    $onEvents = str_starts_with($currentPath, '/termine');
    $onGroups = $currentPath === '/gruppen';
    $onAdminHub = str_starts_with($currentPath, '/verwaltung')
        || str_starts_with($currentPath, '/settings') || str_starts_with($currentPath, '/admin')
        || str_starts_with($currentPath, '/users') || str_starts_with($currentPath, '/logs');
    $onAccount = str_starts_with($currentPath, '/account') || str_starts_with($currentPath, '/security');
    ?>
    <div class="app-shell<?= empty($currentUser) ? ' is-guest' : '' ?>">
        <?php if (!empty($currentUser)): ?>
        <aside class="app-rail" id="pageSidebar">
            <a class="rail-brand" href="<?= e(url('/')) ?>">
                <?php if ($logoPath !== ''): ?>
                    <img class="rail-logo" src="<?= e(asset_url('/' . ltrim($logoPath, '/'))) ?>" alt="">
                <?php else: ?>
                    <span class="rail-dot" aria-hidden="true"></span>
                <?php endif; ?>
                <span class="rail-wordmark"><?= e($shortName !== '' ? $shortName : $appName) ?></span>
            </a>

            <a class="rail-me<?= $onAccount ? ' is-active' : '' ?>" href="<?= e(url('/account')) ?>">
                <span class="rail-me-ava" aria-hidden="true"><?= e($navInitials) ?></span>
                <span class="rail-me-text">
                    <strong><?= e($currentUser['name']) ?></strong>
                    <span>Mein Eintrag</span>
                </span>
            </a>

            <nav class="rail-nav" aria-label="Hauptnavigation">
                <?php if ($publicSiteUrl !== '' && !can('contacts.manage') && can('mail.contact_single')): ?>
                    <a href="<?= e($publicSiteUrl) ?>" target="_blank" rel="noopener noreferrer"><span class="rail-ic"><?= icon('globe') ?></span><?= e($publicSiteLabel !== '' ? $publicSiteLabel : 'Startseite') ?></a>
                <?php endif; ?>
                <a class="<?= $currentPath === '/' ? 'is-active' : '' ?>" href="<?= e(url('/')) ?>"><span class="rail-ic"><?= icon('home') ?></span>Start</a>
                <a class="<?= $onContacts ? 'is-active' : '' ?>" href="<?= e(url('/kontakte')) ?>"><span class="rail-ic"><?= icon('contacts') ?></span>Adressbuch</a>
                <?php if (can('mail.send')): ?>
                    <a class="<?= $onRundmail ? 'is-active' : '' ?>" href="<?= e(url('/rundmail')) ?>"><span class="rail-ic"><?= icon('mail') ?></span>Nachrichten</a>
                <?php endif; ?>
                <?php if (can('events.manage')): ?>
                    <a class="<?= $onEvents ? 'is-active' : '' ?>" href="<?= e(url('/termine')) ?>"><span class="rail-ic"><?= icon('calendar') ?></span>Termine</a>
                <?php endif; ?>
                <?php if (nav_show_groups()): ?>
                    <a class="<?= $onGroups ? 'is-active' : '' ?>" href="<?= e(url('/gruppen')) ?>"><span class="rail-ic"><?= icon('contacts') ?></span>Gruppen</a>
                <?php endif; ?>
                <?php if ($showAdminHub): ?>
                    <span class="rail-group">Verwaltung</span>
                    <a class="<?= $onAdminHub ? 'is-active' : '' ?>" href="<?= e(url('/verwaltung')) ?>"><span class="rail-ic"><?= icon('sliders') ?></span>Einstellungen</a>
                <?php endif; ?>
            </nav>

            <div class="rail-foot">
                <a class="rail-orga" href="<?= e(url('/orga-team')) ?>"><?= icon('mail') ?><span>Orga-Team schreiben</span></a>
                <?php if (!empty($isImpersonating) && !empty($originalUser)): ?>
                    <form method="post" action="<?= e(url('/users/impersonate/stop')) ?>" class="rail-impersonate">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit">Angemeldet als <?= e($currentUser['name']) ?> — zurück zu <?= e($originalUser['name']) ?></button>
                    </form>
                <?php endif; ?>
                <div class="rail-foot-row">
                    <span class="rail-role"><?= e(role_label((string) ($currentUser['role_name'] ?? ''))) ?></span>
                    <form method="post" action="<?= e(url('/logout')) ?>">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit" class="rail-logout">Abmelden</button>
                    </form>
                </div>
                <nav class="rail-legal" aria-label="Rechtliches">
                    <a href="<?= e(url('/impressum')) ?>">Impressum</a>
                    <a href="<?= e(url('/datenschutz')) ?>">Datenschutz</a>
                </nav>
                <?php if ($systemLabel !== ''): ?>
                    <p class="rail-product">läuft mit
                        <a href="<?= e(product_url()) ?>" target="_blank" rel="noopener noreferrer"><?= e($systemLabel) ?></a>
                        v<?= e($appVersion) ?></p>
                <?php endif; ?>
            </div>
        </aside>
        <div class="nav-backdrop" hidden></div>
        <?php endif; ?>

        <div class="app-main">
            <?php if (!empty($currentUser)): ?>
            <header class="app-topbar">
                <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="pageSidebar">
                    <span class="nav-toggle-icon nav-toggle-icon--menu"><?= icon('menu') ?></span>
                    <span class="nav-toggle-icon nav-toggle-icon--close"><?= icon('close') ?></span>
                    <span class="visually-hidden">Menü</span>
                </button>
                <form method="get" action="<?= e(url('/search')) ?>" class="topbar-search" role="search">
                    <label class="visually-hidden" for="globalSearch">Suchen: Kontakte, Benutzer</label>
                    <span class="topbar-search-ic" aria-hidden="true"><?= icon('search') ?></span>
                    <input type="search" id="globalSearch" name="q" value="<?= e($globalSearchQuery) ?>" placeholder="Suchen: Name, Ort …">
                </form>
                <div class="topbar-tools">
                    <?php if (!empty($signalHint)): ?><span class="topbar-hint"><?= e($signalHint) ?></span><?php endif; ?>
                    <span id="signalSelectionStatus" class="topbar-hint" role="status" hidden></span>
                    <button type="submit" id="signalComposeSelection" form="contactSelectionForm" class="topbar-btn" hidden><?= icon('mail') ?><span>Mail an Auswahl</span></button>
                    <button type="button" id="signalClearSelection" class="topbar-btn" data-select="none" hidden><?= icon('reset') ?><span>Auswahl aufheben</span></button>
                    <button type="button" id="privacyGuardToggle" class="topbar-icon" aria-pressed="false" title="Kontaktdaten aus- oder einblenden – falls jemand mitliest">
                        <?= icon('eye-off') ?><span class="visually-hidden" data-privacy-guard-label>Blickschutz</span>
                    </button>
                </div>
            </header>
            <?php endif; ?>

        <main class="content" id="main"><?php if (empty($currentUser)): ?>
            <div class="guest-brand"><span class="rail-dot" aria-hidden="true"></span><span><?= e($shortName !== '' ? $shortName : $appName) ?></span></div>
        <?php endif; ?>
            <?php if (!empty($currentUser) && can('users.manage') && $currentPath !== '/admin/aktualisieren' && system_update_pending()): ?>
                <div class="update-banner" role="status">
                    <span><?= icon('upload') ?> Nach dem letzten Upload steht noch ein Datenbank-Update aus.</span>
                    <a class="button-link" href="<?= e(url('/admin/aktualisieren')) ?>">Jetzt aktualisieren</a>
                </div>
            <?php endif; ?>
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
        </div><!-- /.app-main -->
    </div><!-- /.app-shell -->

    <?php if (empty($currentUser)): ?>
    <footer class="site-footer">
        <div class="site-footer-shell">
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
            </div>
        </div>
    </footer>
    <?php endif; ?>

    <div id="toast" class="toast" role="status" aria-live="polite" hidden></div>
    <script nonce="<?= e(csp_nonce()) ?>">
        window.APP = {
            csrfToken: <?= json_encode($csrfToken, JSON_THROW_ON_ERROR) ?>,
            batchUrl: <?= json_encode(url('/mail/batch'), JSON_THROW_ON_ERROR) ?>
        };
    </script>
    <script src="<?= e(asset_url('/assets/js/app.js')) ?>" defer></script>
</body>
</html>

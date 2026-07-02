<?php
$flashes = [
    'success' => flash('success'),
    'error' => flash('error'),
];
$pageErrors = errors();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(config('app.name', 'Abi Adress Zentrale')) ?></title>
    <link rel="stylesheet" href="<?= e(url('/assets/css/theme.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">
</head>
<body>
    <div class="page-shell">
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="brand-mark">GRUEZE</div>
                <div>
                    <p class="eyebrow">Abi-Stufe</p>
                    <h1><?= e(config('app.name', 'Abi Adress Zentrale')) ?></h1>
                    <p class="muted sidebar-copy">Kontakte, Mailings und Organisation an einem Ort.</p>
                </div>
            </div>
            <?php if (!empty($currentUser)): ?>
                <nav class="nav">
                    <a class="<?= $currentPath === '/' ? 'is-active' : '' ?>" href="<?= e(url('/')) ?>"><?= icon('contacts') ?><span>Kontakte</span></a>
                    <?php if (can('contacts.manage')): ?><a class="<?= str_starts_with($currentPath, '/contacts/import') ? 'is-active' : '' ?>" href="<?= e(url('/contacts/import')) ?>"><?= icon('upload') ?><span>Import</span></a><?php endif; ?>
                    <?php if (can('users.manage')): ?><a class="<?= str_starts_with($currentPath, '/users') ? 'is-active' : '' ?>" href="<?= e(url('/users')) ?>"><?= icon('user') ?><span>Benutzer</span></a><?php endif; ?>
                    <?php if (can('audit.view')): ?><a class="<?= str_starts_with($currentPath, '/logs/audit') ? 'is-active' : '' ?>" href="<?= e(url('/logs/audit')) ?>"><?= icon('history') ?><span>Audit-Log</span></a><?php endif; ?>
                    <?php if (can('mail.view_log')): ?><a class="<?= str_starts_with($currentPath, '/logs/mail') ? 'is-active' : '' ?>" href="<?= e(url('/logs/mail')) ?>"><?= icon('mail') ?><span>Versandprotokoll</span></a><?php endif; ?>
                </nav>
                <div class="sidebar-footer">
                    <div class="profile-chip">
                        <strong><?= e($currentUser['name']) ?></strong>
                        <span><?= e($currentUser['role_name'] ?? '') ?></span>
                    </div>
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
                    <h2 class="topbar-title"><?= e(config('app.name', 'Abi Adress Zentrale')) ?></h2>
                </div>
                <?php if (!empty($currentUser)): ?>
                    <div class="topbar-meta">
                        <?= icon('sparkles') ?>
                        <span>Angemeldet als <?= e($currentUser['name']) ?></span>
                    </div>
                <?php endif; ?>
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

    <div class="privacy-note">
        Datenschutzhinweis-Platzhalter: Bitte vor dem Livegang mit eurem finalen Text ersetzen.
    </div>

    <div id="toast" class="toast" hidden></div>
    <script>
        window.APP = {
            csrfToken: <?= json_encode($csrfToken, JSON_THROW_ON_ERROR) ?>,
            batchUrl: <?= json_encode(url('/mail/batch'), JSON_THROW_ON_ERROR) ?>
        };
    </script>
    <script src="<?= e(url('/assets/js/app.js')) ?>" defer></script>
</body>
</html>

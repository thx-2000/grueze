<?php
$flashes = [
    'success' => flash('success'),
    'error' => flash('error'),
];
$pageErrors = errors();
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
            <div>
                <p class="eyebrow">Abi-Stufe</p>
                <h1><?= e(config('app.name', 'Abi Adress Zentrale')) ?></h1>
                <p class="muted">Platzhalter für Logo oder Abi-Motto</p>
            </div>
            <?php if (!empty($currentUser)): ?>
                <nav class="nav">
                    <a href="<?= e(url('/')) ?>">Kontakte</a>
                    <?php if (can('users.manage')): ?><a href="<?= e(url('/users')) ?>">Benutzer</a><?php endif; ?>
                    <?php if (can('audit.view')): ?><a href="<?= e(url('/logs/audit')) ?>">Audit-Log</a><?php endif; ?>
                    <?php if (can('mail.view_log')): ?><a href="<?= e(url('/logs/mail')) ?>">Versandprotokoll</a><?php endif; ?>
                </nav>
                <div class="sidebar-footer">
                    <p>Angemeldet als <?= e($currentUser['name']) ?></p>
                    <form method="post" action="<?= e(url('/logout')) ?>">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit" class="ghost-button">Abmelden</button>
                    </form>
                </div>
            <?php endif; ?>
        </aside>

        <main class="content">
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

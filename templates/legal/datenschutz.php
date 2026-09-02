<header class="page-head page-head--split">
    <div>
        <p class="eyebrow">Rechtliches</p>
        <h1>Datenschutzerkl&auml;rung</h1>
    </div>
    <?php if (!empty($currentUser) && ($currentUser['role_name'] ?? '') === 'admin'): ?>
        <a href="<?= e(url('/admin/legal/datenschutz')) ?>" class="ghost-button">Bearbeiten</a>
    <?php endif; ?>
</header>

<section class="panel narrow legal-copy">
    <?= $content ?>
</section>

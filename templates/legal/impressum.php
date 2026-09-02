<header class="page-head page-head--split">
    <div>
        <p class="eyebrow">Rechtliches</p>
        <h1>Impressum</h1>
        <p class="muted">Angaben gem&auml;&szlig; &sect; 5 TMG</p>
    </div>
    <?php if (!empty($currentUser) && ($currentUser['role_name'] ?? '') === 'admin'): ?>
        <a href="<?= e(url('/admin/legal/impressum')) ?>" class="ghost-button">Bearbeiten</a>
    <?php endif; ?>
</header>

<section class="panel narrow legal-copy">
    <?= $content ?>
</section>

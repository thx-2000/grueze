<section class="hero-card narrow">
    <div>
        <p class="eyebrow">Rechtliches</p>
        <h2>Datenschutzerkl&auml;rung</h2>
    </div>
    <?php if (!empty($currentUser) && ($currentUser['role_name'] ?? '') === 'admin'): ?>
        <div>
            <a href="<?= e(url('/admin/legal/datenschutz')) ?>" class="button-secondary">Bearbeiten</a>
        </div>
    <?php endif; ?>
</section>

<section class="panel narrow legal-copy">
    <?= $content ?>
</section>

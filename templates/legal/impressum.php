<section class="hero-card narrow">
    <div>
        <p class="eyebrow">Rechtliches</p>
        <h2>Impressum</h2>
        <p class="muted">Angaben gem&auml;&szlig; &sect; 5 TMG</p>
    </div>
    <?php if (!empty($currentUser) && ($currentUser['role_name'] ?? '') === 'admin'): ?>
        <div>
            <a href="<?= e(url('/admin/legal/impressum')) ?>" class="button-secondary">Bearbeiten</a>
        </div>
    <?php endif; ?>
</section>

<section class="panel narrow legal-copy">
    <?= $content ?>
</section>

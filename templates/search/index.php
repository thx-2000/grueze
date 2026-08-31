<section class="hero-card">
    <div class="hero-row">
        <div>
            <p class="eyebrow">Globale Suche</p>
            <h2><?= $query === '' ? 'Schnell finden' : 'Ergebnisse für „' . e($query) . '“' ?></h2>
            <p class="muted">Kontakte und, für Admin/Orga, auch Benutzerkonten werden gemeinsam durchsucht.</p>
        </div>
        <div class="selection-status">
            <?= e((string) (count($contactResults) + count($userResults))) ?> Treffer
        </div>
    </div>
</section>

<section class="panel stack">
    <form method="get" action="<?= e(url('/search')) ?>" class="global-search-panel">
        <input type="search" name="q" value="<?= e($query) ?>" placeholder="Vorname, Nachname, Ort, E-Mail, Benutzername ...">
        <button type="submit"><?= icon('search') ?><span>Suchen</span></button>
        <a class="ghost-button" href="<?= e(url('/kontakte')) ?>">Zurück zur Kontaktliste</a>
    </form>

    <?php if ($query === ''): ?>
        <p class="muted">Einfach oben einen Begriff eingeben, zum Beispiel einen Vornamen, Nachnamen, Ort oder eine Mailadresse.</p>
    <?php endif; ?>
</section>

<?php if ($contactResults !== []): ?>
    <section class="panel stack">
        <div class="panel-head">
            <div>
                <h3>Kontakte</h3>
                <p class="muted"><?= e((string) count($contactResults)) ?> Treffer</p>
            </div>
        </div>
        <div class="search-result-grid">
            <?php foreach ($contactResults as $contact): ?>
                <article class="search-result-card">
                    <div class="search-result-head">
                        <div>
                            <strong><?= e(trim(($contact['vorname'] ?? '') . ' ' . ($contact['nachname'] ?? ''))) ?></strong>
                            <?php if (!empty($contact['geburtsname']) && $contact['geburtsname'] !== ($contact['nachname'] ?? '')): ?>
                                <span class="muted">(<?= e($contact['geburtsname']) ?>)</span>
                            <?php endif; ?>
                        </div>
                        <?php if (($contact['has_email'] ?? 0) == 0): ?>
                            <span class="missing-email-badge"><?= icon('mail-off') ?><span>Mail fehlt</span></span>
                        <?php endif; ?>
                    </div>
                    <div class="table-stack">
                        <span><?= e($contact['category_name'] ?: '—') ?></span>
                        <span class="muted is-guarded"><?= e($contact['ort'] ?: 'Ort unbekannt') ?></span>
                    </div>
                    <div class="card-actions">
                        <a class="ghost-button compact-action" href="<?= e(url('/contacts/edit?id=' . $contact['id'])) ?>">
                            <?= icon('edit') ?><span>Öffnen</span>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($userResults !== []): ?>
    <section class="panel stack">
        <div class="panel-head">
            <div>
                <h3>Benutzer</h3>
                <p class="muted"><?= e((string) count($userResults)) ?> Treffer</p>
            </div>
        </div>
        <div class="search-result-grid">
            <?php foreach ($userResults as $user): ?>
                <article class="search-result-card">
                    <div class="search-result-head">
                        <strong><?= e($user['name']) ?></strong>
                        <span class="table-pill"><?= e($user['role_name']) ?></span>
                    </div>
                    <div class="table-stack">
                        <span class="is-guarded"><?= e($user['email']) ?></span>
                        <span class="muted">
                            <?= e(trim((string) ($user['vorname'] ?? '') . ' ' . (string) ($user['nachname'] ?? '')) ?: 'Kein Kontakt verknüpft') ?>
                        </span>
                    </div>
                    <div class="card-actions">
                        <a class="ghost-button compact-action" href="<?= e(url('/users#user-' . $user['id'])) ?>">
                            <?= icon('user') ?><span>Zum Benutzer</span>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($query !== '' && $contactResults === [] && $userResults === []): ?>
    <section class="panel">
        <p class="muted">Keine Treffer gefunden. Probiere einen anderen Namen, Ort oder eine andere Mailadresse.</p>
    </section>
<?php endif; ?>

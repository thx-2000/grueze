<?php
$total = count($contactResults) + count($userResults);
$hasQuery = $query !== '';
?>
<section class="panel search-bar-panel">
    <form method="get" action="<?= e(url('/search')) ?>" class="start-search" role="search">
        <label for="searchField" class="visually-hidden">Suchen</label>
        <?= icon('search') ?>
        <input type="search" id="searchField" name="q" value="<?= e($query) ?>" placeholder="Name, Geburtsname, Ort …" autocomplete="off" autofocus>
        <button type="submit">Suchen</button>
    </form>
    <div class="search-bar-meta">
        <?php if ($hasQuery): ?>
            <span><strong><?= e((string) $total) ?></strong> Treffer für „<?= e($query) ?>"</span>
        <?php endif; ?>
        <a class="ghost-button compact-action" href="<?= e(url('/kontakte')) ?>"><?= icon('contacts') ?><span>Zur Kontaktliste</span></a>
    </div>
</section>

<?php if (!$hasQuery): ?>
    <section class="panel">
        <p class="muted">Tippe oben einen Namen, Geburtsnamen oder Ort ein. Für Admin/Orga werden auch Benutzerkonten durchsucht.</p>
    </section>
<?php endif; ?>

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
                <a class="search-result-card" href="<?= e(url('/contacts/edit?id=' . $contact['id'])) ?>">
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
                    <span class="search-result-open"><?= icon('edit') ?><span>Öffnen</span></span>
                </a>
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
                <a class="search-result-card" href="<?= e(url('/users#user-' . $user['id'])) ?>">
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
                    <span class="search-result-open"><?= icon('user') ?><span>Zum Benutzer</span></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($hasQuery && $contactResults === [] && $userResults === []): ?>
    <section class="panel">
        <p class="muted">Keine Treffer für „<?= e($query) ?>". Probiere einen anderen Namen, Geburtsnamen oder Ort.</p>
    </section>
<?php endif; ?>

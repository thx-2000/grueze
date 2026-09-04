<?php
/**
 * @var string $query
 * @var list<array<string,mixed>> $contactResults
 * @var list<array<string,mixed>> $userResults
 * @var list<string> $resultCategories
 */
$total = count($contactResults) + count($userResults);
$hasQuery = $query !== '';

// Welche Fundstellen-Arten kommen in den Treffern vor? (für die Filter-Chips)
$matchKinds = [];
foreach ($contactResults as $c) {
    foreach (($c['_matched'] ?? []) as $m) {
        $matchKinds[$m['key']] = $m['label'];
    }
}
?>
<section class="panel search-bar-panel">
    <h2 class="visually-hidden">Suche</h2>
    <form method="get" action="<?= e(url('/search')) ?>" class="start-search" role="search">
        <label for="searchField" class="visually-hidden">Suchen</label>
        <?= icon('search') ?>
        <input type="search" id="searchField" name="q" value="<?= e($query) ?>" placeholder="Name, Ort, E-Mail, Webseite, Tag …" autocomplete="off" autofocus>
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
        <p class="muted">Durchsucht wird alles, was deine Rolle sehen darf: Name und Geburtsname, Kategorie, Tags und Gruppen, Beruf, Webseite – und (falls sichtbar) Adresse, E-Mail, Telefon und Notizen. Für Admin/Orga auch die Zugänge.</p>
    </section>
<?php endif; ?>

<?php if ($contactResults !== []): ?>
    <section class="panel stack" data-search-results>
        <div class="panel-head">
            <div>
                <h3>Kontakte</h3>
                <p class="muted"><span data-result-count><?= e((string) count($contactResults)) ?></span> von <?= e((string) count($contactResults)) ?></p>
            </div>
        </div>

        <?php if ($resultCategories !== [] || count($matchKinds) > 1): ?>
            <div class="search-filter" data-search-filter>
                <?php if (count($resultCategories) > 1): ?>
                    <label class="search-filter-cat">
                        <span>Kategorie</span>
                        <select data-filter-category>
                            <option value="">Alle</option>
                            <?php foreach ($resultCategories as $cat): ?>
                                <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>
                <?php if (count($matchKinds) > 1): ?>
                    <div class="search-filter-kinds" role="group" aria-label="Nach Fundstelle eingrenzen">
                        <span>Fundstelle</span>
                        <?php foreach ($matchKinds as $key => $label): ?>
                            <label class="search-kind-toggle">
                                <input type="checkbox" data-filter-kind value="<?= e($key) ?>">
                                <span><?= e($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <button type="button" class="linkish" data-filter-reset hidden>Filter zurücksetzen</button>
            </div>
        <?php endif; ?>

        <div class="search-result-grid">
            <?php foreach ($contactResults as $contact): ?>
                <?php
                $matched = $contact['_matched'] ?? [];
                $kinds = implode(' ', array_map(static fn (array $m): string => $m['key'], $matched));
                $hasEmail = ($contact['emails'] ?? []) !== [];
                ?>
                <a class="search-result-card"
                   href="<?= e(url('/contacts/edit?id=' . (int) $contact['id'])) ?>"
                   data-result-card
                   data-cat="<?= e((string) ($contact['category_name'] ?? '')) ?>"
                   data-kinds="<?= e($kinds) ?>">
                    <div class="search-result-head">
                        <div>
                            <strong><?= e(trim(($contact['vorname'] ?? '') . ' ' . ($contact['nachname'] ?? ''))) ?></strong>
                            <?php if (($bn = format_birth_name($contact)) !== ''): ?>
                                <span class="muted"><?= e($bn) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!$hasEmail): ?>
                            <span class="missing-email-badge"><?= icon('mail-off') ?><span>Mail fehlt</span></span>
                        <?php endif; ?>
                    </div>
                    <div class="table-stack">
                        <span><?= e(($contact['category_name'] ?? '') ?: '—') ?></span>
                        <?php if (($contact['ort'] ?? '') !== ''): ?>
                            <span class="muted is-guarded"><?= e($contact['ort']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($matched !== []): ?>
                        <div class="search-match">
                            <?php foreach ($matched as $m): ?>
                                <span class="search-match-chip">
                                    <b><?= e($m['label']) ?>:</b>
                                    <span class="is-guarded"><?= e($m['snippet'] !== '' ? $m['snippet'] : '—') ?></span>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <span class="search-result-open"><?= icon('edit') ?><span>Öffnen</span></span>
                </a>
            <?php endforeach; ?>
        </div>
        <p class="muted search-no-match" hidden>Kein Treffer passt zu den gewählten Filtern.</p>
    </section>
<?php endif; ?>

<?php if ($userResults !== []): ?>
    <section class="panel stack">
        <div class="panel-head">
            <div>
                <h3>Zugänge</h3>
                <p class="muted"><?= e((string) count($userResults)) ?> Treffer</p>
            </div>
        </div>
        <div class="search-result-grid">
            <?php foreach ($userResults as $user): ?>
                <a class="search-result-card" href="<?= e(url('/users#user-' . (int) $user['id'])) ?>">
                    <div class="search-result-head">
                        <strong><?= e($user['name']) ?></strong>
                        <span class="table-pill"><?= e(role_label((string) $user['role_name'])) ?></span>
                    </div>
                    <div class="table-stack">
                        <span class="is-guarded"><?= e($user['email']) ?></span>
                        <span class="muted">
                            <?= e(trim((string) ($user['vorname'] ?? '') . ' ' . (string) ($user['nachname'] ?? '')) ?: 'Kein Kontakt verknüpft') ?>
                        </span>
                    </div>
                    <span class="search-result-open"><?= icon('user') ?><span>Zum Zugang</span></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($hasQuery && $contactResults === [] && $userResults === []): ?>
    <section class="panel">
        <p class="muted">Keine Treffer für „<?= e($query) ?>". Andere Schreibweise probieren, oder einen Teil des Begriffs.</p>
    </section>
<?php endif; ?>

<?php if ($contactResults !== []): ?>
<script nonce="<?= e(csp_nonce()) ?>">
(function () {
    var root = document.querySelector('[data-search-results]');
    if (!root) return;
    var cards = Array.prototype.slice.call(root.querySelectorAll('[data-result-card]'));
    var catSel = root.querySelector('[data-filter-category]');
    var kindBoxes = Array.prototype.slice.call(root.querySelectorAll('[data-filter-kind]'));
    var resetBtn = root.querySelector('[data-filter-reset]');
    var countEl = root.querySelector('[data-result-count]');
    var noMatch = root.querySelector('.search-no-match');

    function apply() {
        var cat = catSel ? catSel.value : '';
        var kinds = kindBoxes.filter(function (b) { return b.checked; }).map(function (b) { return b.value; });
        var shown = 0;
        cards.forEach(function (card) {
            var okCat = !cat || card.getAttribute('data-cat') === cat;
            var cardKinds = (card.getAttribute('data-kinds') || '').split(' ');
            var okKind = kinds.length === 0 || kinds.some(function (k) { return cardKinds.indexOf(k) !== -1; });
            var visible = okCat && okKind;
            card.hidden = !visible;
            if (visible) shown++;
        });
        if (countEl) countEl.textContent = String(shown);
        if (noMatch) noMatch.hidden = shown !== 0;
        var active = !!cat || kinds.length > 0;
        if (resetBtn) resetBtn.hidden = !active;
    }

    if (catSel) catSel.addEventListener('change', apply);
    kindBoxes.forEach(function (b) { b.addEventListener('change', apply); });
    if (resetBtn) resetBtn.addEventListener('click', function () {
        if (catSel) catSel.value = '';
        kindBoxes.forEach(function (b) { b.checked = false; });
        apply();
    });
})();
</script>
<?php endif; ?>

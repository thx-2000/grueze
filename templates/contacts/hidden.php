<?php
/** @var list<array<string,mixed>> $hidden */
$fullName = static fn (array $c): string => trim($c['vorname'] . ' ' . $c['nachname']);
?>
<p class="detail-backlink"><a href="<?= e(url('/kontakte')) ?>"><?= icon('chevron-right') ?>Zurück zum Adressbuch</a></p>

<header class="contacts-header">
    <div>
        <h1>Versteckte Kontakte</h1>
        <p class="muted">Nur für Admins sichtbar – diese Kontakte tauchen für niemand sonst im Adressbuch, in der Suche, in Mailings oder bei Geburtstagen auf. Die Daten bleiben vollständig erhalten.</p>
    </div>
</header>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Versteckt</h2>
            <p class="muted"><?= count($hidden) ?> <?= count($hidden) === 1 ? 'Kontakt' : 'Kontakte' ?></p>
        </div>
    </div>
    <?php if ($hidden === []): ?>
        <p class="completeness-clear"><?= icon('check') ?><span>Niemand ist aktuell versteckt.</span></p>
    <?php else: ?>
        <ul class="retired-list">
            <?php foreach ($hidden as $c): ?>
                <li class="retired-row">
                    <div class="retired-person">
                        <strong><?= e($fullName($c)) ?></strong>
                        <?php if (trim((string) ($c['geburtsname'] ?? '')) !== ''): ?>
                            <span class="birth-name-inline">(ehem. <?= e((string) $c['geburtsname']) ?>)</span>
                        <?php endif; ?>
                        <?php if (trim((string) ($c['category_name'] ?? '')) !== ''): ?>
                            <span class="muted"><?= e((string) $c['category_name']) ?></span>
                        <?php endif; ?>
                        <span class="retired-meta muted">
                            versteckt seit <?= e(format_date(substr((string) $c['hidden_at'], 0, 10))) ?>
                            <?php if (trim((string) ($c['hidden_by_name'] ?? '')) !== ''): ?>
                                · von <?= e((string) $c['hidden_by_name']) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="retired-actions">
                        <a class="ghost-button" href="<?= e(url('/contacts/edit?id=' . (int) $c['id'])) ?>"><?= icon('edit') ?><span>Öffnen</span></a>
                        <form method="post" action="<?= e(url('/contacts/einblenden')) ?>">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="id" value="<?= e((string) $c['id']) ?>">
                            <button type="submit" class="ghost-button"><?= icon('eye') ?><span>Wieder einblenden</span></button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

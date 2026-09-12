<?php
/**
 * @var list<array<string,mixed>> $people
 * @var bool $canManage
 */
$addressLine = static function (array $p): string {
    $parts = array_filter([
        trim((string) ($p['strasse'] ?? '')),
        trim(($p['plz'] ?? '') . ' ' . ($p['ort'] ?? '')),
        trim((string) ($p['land'] ?? '')),
    ], static fn (string $v): bool => $v !== '');

    return implode(', ', $parts);
};

// „12.03.1950 – 04.07.2020 · 70 Jahre" (lebt: nur Geburtsdatum + aktuelles Alter).
$lifespan = static function (array $p): string {
    $born = !empty($p['born_on']) ? format_date((string) $p['born_on']) : '';
    $died = !empty($p['died_on']) ? format_date((string) $p['died_on']) : '';
    if ($born === '' && $died === '') {
        return '';
    }
    $span = $died !== '' ? ($born !== '' ? $born . ' – ' . $died : '† ' . $died) : $born;
    if ($p['age'] !== null) {
        $span .= ' · ' . (int) $p['age'] . ' Jahre';
    }

    return $span;
};
?>
<header class="contact-detail-head">
    <p class="eyebrow">Zusätzliche Liste</p>
    <h1><?= e(roster_label()) ?></h1>
    <p class="muted">Getrennt vom Adressbuch – landet nirgends in Rundmails, Abstimmungen oder Geburtstagslisten.</p>
    <?php if ($canManage): ?>
        <div class="toolbar-actions">
            <a class="button-link" href="<?= e(url('/weitere-personen/neu')) ?>"><?= icon('plus') ?><span>Eintrag hinzufügen</span></a>
        </div>
    <?php endif; ?>
</header>

<?php if ($people === []): ?>
    <section class="panel">
        <p class="muted">
            Noch kein Eintrag.
            <?php if ($canManage): ?>
                Über <a href="<?= e(url('/weitere-personen/neu')) ?>">Eintrag hinzufügen</a> eine möglichst vollständige Liste aufbauen.
            <?php endif; ?>
        </p>
    </section>
<?php else: ?>
    <section class="panel">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Wer</th>
                        <th>Rolle</th>
                        <th>Kontakt</th>
                        <th>Geburts-/Sterbedatum</th>
                        <th>Adresse</th>
                        <?php if ($canManage): ?><th><span class="visually-hidden">Aktionen</span></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($people as $p): ?>
                        <tr>
                            <td>
                                <div class="contact-name-cell contact-name-cell--avatar">
                                    <?= contact_avatar($p, 'sm', true) ?>
                                    <div>
                                        <strong><?= e((string) $p['name']) ?></strong>
                                        <?php if ($p['is_deceased']): ?><span title="verstorben"><?= icon('cross') ?></span><?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?= $p['role_label'] !== null ? e((string) $p['role_label']) : '—' ?></td>
                            <td>
                                <div class="table-stack">
                                    <?php if (!empty($p['email'])): ?><a href="mailto:<?= e((string) $p['email']) ?>"><?= e((string) $p['email']) ?></a><?php endif; ?>
                                    <?php if (!empty($p['mobile'])): ?><a href="tel:<?= e((string) $p['mobile']) ?>"><?= e((string) $p['mobile']) ?></a><?php endif; ?>
                                    <?php if (empty($p['email']) && empty($p['mobile'])): ?><span class="muted">—</span><?php endif; ?>
                                </div>
                            </td>
                            <td><?= ($span = $lifespan($p)) !== '' ? e($span) : '<span class="muted">—</span>' ?></td>
                            <td><?= ($addr = $addressLine($p)) !== '' ? e($addr) : '<span class="muted">—</span>' ?></td>
                            <?php if ($canManage): ?>
                                <td class="col-open">
                                    <a class="ghost-button compact-action" href="<?= e(url('/weitere-personen/bearbeiten?id=' . (int) $p['id'])) ?>"><?= icon('edit') ?><span>Bearbeiten</span></a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

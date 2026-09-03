<?php
/** @var list<array{reason:string,contacts:list<array<string,mixed>>}> $clusters */
/** @var bool $canMerge */
$fullName = static fn (array $c): string => trim($c['vorname'] . ' ' . $c['nachname']);
?>
<p class="detail-backlink"><a href="<?= e(url('/kontakte')) ?>"><?= icon('chevron-right') ?>Zurück zum Adressbuch</a></p>

<header class="contacts-header">
    <div>
        <h1>Mögliche Doppel-Einträge</h1>
        <p class="muted">Kontakte mit gleichem Namen oder gleicher Mailadresse. Beim Zusammenführen wandert alles in den Hauptkontakt (Kontaktwege, Tags, Gruppen, Termin-Rückmeldungen, Verlauf); leere Felder werden aus den anderen aufgefüllt, Notizen zusammengehängt. Die anderen Einträge werden danach gelöscht.</p>
    </div>
</header>

<?php if ($clusters === []): ?>
    <section class="panel">
        <p class="completeness-clear"><?= icon('check') ?><span>Keine offensichtlichen Doppel-Einträge gefunden.</span></p>
    </section>
<?php else: ?>
    <?php foreach ($clusters as $ci => $cluster): ?>
        <section class="detail-card dup-cluster">
            <div class="dup-cluster-head">
                <h2><?= e($fullName($cluster['contacts'][0])) ?><?= count($cluster['contacts']) > 2 ? ' u. a.' : '' ?></h2>
                <span class="status-chip is-warn"><?= e($cluster['reason']) ?></span>
            </div>

            <form method="post" action="<?= e(url('/contacts/zusammenfuehren')) ?>" class="dup-form" data-confirm="Diese Kontakte zusammenführen? Die nicht als Hauptkontakt gewählten Einträge werden gelöscht.">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <ol class="dup-list">
                    <?php foreach ($cluster['contacts'] as $k => $c): ?>
                        <?php $cid = (int) $c['id']; ?>
                        <li class="dup-entry">
                            <label class="dup-primary">
                                <input type="radio" name="primary_id" value="<?= $cid ?>" <?= $k === 0 ? 'checked' : '' ?>>
                                <span>Hauptkontakt</span>
                            </label>
                            <label class="dup-merge">
                                <input type="checkbox" name="secondary_ids[]" value="<?= $cid ?>" checked>
                                <span>einbeziehen</span>
                            </label>
                            <div class="dup-card">
                                <p class="dup-name">
                                    <strong><?= e($fullName($c)) ?></strong>
                                    <?php if (trim((string) ($c['geburtsname'] ?? '')) !== ''): ?>
                                        <span class="birth-name-inline">(ehem. <?= e((string) $c['geburtsname']) ?>)</span>
                                    <?php endif; ?>
                                    <?php if (!empty($c['linked_user'])): ?><span class="status-chip is-ok">Zugang</span><?php endif; ?>
                                </p>
                                <dl class="dup-fields">
                                    <?php if (trim((string) ($c['category_name'] ?? '')) !== ''): ?>
                                        <div><dt>Kategorie</dt><dd><?= e((string) $c['category_name']) ?></dd></div>
                                    <?php endif; ?>
                                    <?php if (($c['emails'] ?? []) !== []): ?>
                                        <div><dt>E-Mail</dt><dd><?= e(implode(', ', array_map(static fn ($m) => (string) $m['email'], $c['emails']))) ?></dd></div>
                                    <?php endif; ?>
                                    <?php if (($c['phones'] ?? []) !== []): ?>
                                        <div><dt>Telefon</dt><dd><?= e(implode(', ', array_map(static fn ($p) => (string) $p['phone'], $c['phones']))) ?></dd></div>
                                    <?php endif; ?>
                                    <?php if (trim((string) ($c['strasse'] ?? '') . ($c['plz'] ?? '') . ($c['ort'] ?? '')) !== ''): ?>
                                        <div><dt>Adresse</dt><dd><?= e(trim(($c['strasse'] ?? '') . ', ' . ($c['plz'] ?? '') . ' ' . ($c['ort'] ?? ''), ', ')) ?></dd></div>
                                    <?php endif; ?>
                                    <?php if (trim((string) ($c['geburtstag'] ?? '')) !== ''): ?>
                                        <div><dt>Geburtstag</dt><dd><?= e(format_date((string) $c['geburtstag'])) ?></dd></div>
                                    <?php endif; ?>
                                    <?php if (($c['groups'] ?? []) !== []): ?>
                                        <div><dt>Gruppen</dt><dd><?= e(implode(', ', array_map(static fn ($g) => (string) $g['name'], $c['groups']))) ?></dd></div>
                                    <?php endif; ?>
                                    <div><dt>angelegt</dt><dd><?= e(format_date(substr((string) ($c['created_at'] ?? ''), 0, 10))) ?></dd></div>
                                </dl>
                                <p class="dup-open"><a href="<?= e(url('/contacts/edit?id=' . $cid)) ?>" target="_blank" rel="noopener">Eintrag ansehen</a></p>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <div class="toolbar-actions">
                    <?php if ($canMerge): ?>
                        <button type="submit"><?= icon('check') ?><span>Zusammenführen</span></button>
                    <?php else: ?>
                        <p class="field-hint">Zum Zusammenführen fehlt dir die Berechtigung zum Löschen von Kontakten.</p>
                    <?php endif; ?>
                </div>
            </form>
        </section>
    <?php endforeach; ?>
<?php endif; ?>

<?php
/**
 * @var list<array{id:int,name:string,email:string}> $eligible
 * @var list<array{name:string,reason:string}> $skipped
 * @var int $linkHours
 * @var string $defaultRoleLabel
 */
?>
<header class="contacts-header">
    <div>
        <p class="eyebrow"><a href="<?= e(url('/verwaltung/einladungen')) ?>">Sammel-Einladung</a></p>
        <h1>Vorschau</h1>
        <p class="muted">Bitte prüfen, bevor die Mails rausgehen. Rolle: <strong><?= e($defaultRoleLabel) ?></strong>, Link <?= (int) $linkHours ?> Stunden gültig.</p>
    </div>
</header>

<?php if ($eligible === []): ?>
    <section class="panel">
        <p class="muted">Niemand aus der Auswahl kann eingeladen werden – siehe Gründe unten.</p>
    </section>
<?php else: ?>
    <section class="panel">
        <div class="panel-head">
            <div>
                <h3>Wird eingeladen</h3>
                <p class="muted"><?= count($eligible) ?> <?= count($eligible) === 1 ? 'Person' : 'Personen' ?>.</p>
            </div>
        </div>
        <div class="recipient-grid">
            <?php foreach ($eligible as $c): ?>
                <article class="recipient-chip">
                    <strong><?= e($c['name']) ?></strong>
                    <span class="is-guarded"><?= e($c['email']) ?></span>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <form method="post" action="<?= e(url('/verwaltung/einladungen/start')) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <div class="form-actions">
            <button type="submit" class="button-link" data-confirm="Jetzt <?= count($eligible) ?> Einladungen verschicken?">
                <?= icon('mail') ?><span>Jetzt <?= count($eligible) ?> Einladungen verschicken</span>
            </button>
            <a class="ghost-button" href="<?= e(url('/verwaltung/einladungen')) ?>">Abbrechen</a>
        </div>
    </form>
<?php endif; ?>

<?php if ($skipped !== []): ?>
    <section class="panel top-gap">
        <div class="panel-head">
            <div>
                <h3>Übersprungen</h3>
                <p class="muted"><?= count($skipped) ?> <?= count($skipped) === 1 ? 'Person' : 'Personen' ?> – keine Einladung ohne Aktion nötig.</p>
            </div>
        </div>
        <ul class="events-list">
            <?php foreach ($skipped as $s): ?>
                <li class="events-row">
                    <span class="events-row-main">
                        <span class="events-row-title"><?= e($s['name']) ?></span>
                        <span class="events-row-meta"><?= e($s['reason']) ?></span>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

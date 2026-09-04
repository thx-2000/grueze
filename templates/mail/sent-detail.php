<?php
/**
 * @var array<string,mixed> $entry
 * @var list<array<string,mixed>> $recipients
 * @var array<int,bool> $liveContactIds
 * @var int $reachableCount
 */
$fullSubject = trim(((string) $entry['subject_prefix'] !== '' ? $entry['subject_prefix'] . ' ' : '') . (string) $entry['subject']);
?>
<p class="detail-backlink"><a href="<?= e(url('/rundmail/verlauf')) ?>"><?= icon('chevron-right') ?>Zurück zur Übersicht</a></p>

<header class="contact-detail-head">
    <p class="eyebrow">Gesendete Nachricht</p>
    <h1><?= e($fullSubject ?: '(ohne Betreff)') ?></h1>
    <div class="contact-detail-meta">
        <span class="muted"><?= e(format_datetime((string) $entry['created_at'])) ?></span>
        <span class="muted">von <?= e((string) ($entry['current_sender_name'] ?: $entry['sender_name']) ?: 'unbekannt') ?></span>
        <span class="table-pill"><?= e((string) $entry['sent_count']) ?>/<?= e((string) $entry['recipient_count']) ?> zugestellt</span>
        <?php if ((int) $entry['failed_count'] > 0): ?>
            <span class="status-chip is-warn"><?= e((string) $entry['failed_count']) ?> fehlgeschlagen</span>
        <?php endif; ?>
    </div>
</header>

<section class="detail-card">
    <h2>Text</h2>
    <p class="field-hint">So, wie er geschrieben wurde – <code>{Anrede}</code>, <code>{Vorname}</code> usw. werden beim Versand je Person ersetzt.</p>
    <div class="mail-footer-preview"><?= nl2br(e((string) $entry['body'])) ?></div>
</section>

<section class="detail-card">
    <form method="post" action="<?= e(url('/rundmail/verlauf/erneut')) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="id" value="<?= e((string) $entry['id']) ?>">

        <div class="panel-head">
            <div>
                <h2>Empfänger</h2>
                <p class="muted">
                    <?= e((string) $reachableCount) ?> von <?= e((string) count($recipients)) ?> sind noch im Adressbuch.
                    Ohne Auswahl geht die Nachricht erneut an alle noch vorhandenen.
                </p>
            </div>
            <button type="submit" class="button-link"><?= icon('mail') ?><span>Als Entwurf übernehmen</span></button>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th><span class="visually-hidden">Erneut senden</span></th>
                        <th>Name</th>
                        <th>Mailadresse</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recipients as $r): ?>
                        <?php
                        $cid = (int) ($r['contact_id'] ?? 0);
                        $live = $cid > 0 && isset($liveContactIds[$cid]);
                        $failed = ($r['status'] ?? '') !== 'gesendet';
                        ?>
                        <tr>
                            <td>
                                <?php if ($live): ?>
                                    <label class="table-check">
                                        <input type="checkbox" name="recipient_ids[]" value="<?= e((string) $cid) ?>" aria-label="<?= e((string) ($r['name'] ?? '') . ' erneut anschreiben') ?>">
                                    </label>
                                <?php else: ?>
                                    <span class="muted" title="Nicht mehr im Adressbuch">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e((string) ($r['name'] ?? '—')) ?></td>
                            <td><?= e((string) ($r['email'] ?? '')) ?></td>
                            <td>
                                <?php if ($failed): ?>
                                    <span class="status-chip is-warn"><?= e((string) ($r['error'] ?? 'fehlgeschlagen')) ?></span>
                                <?php else: ?>
                                    <span class="status-chip is-ok">zugestellt</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="field-hint">„Als Entwurf übernehmen" öffnet die Schreiben-Seite mit Text und Empfängerkreis vorbelegt – abgeschickt wird erst dort.</p>
    </form>
</section>

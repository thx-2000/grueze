<?php
$statusLabel = ['open' => 'Abstimmung läuft', 'closed' => 'Abstimmung beendet', 'decided' => 'Termin steht', 'archived' => 'Archiviert'];
$today = (new DateTimeImmutable('now'))->format('Y-m-d');
$closesAt = trim((string) ($event['closes_at'] ?? ''));
$closesAtLocal = $closesAt !== '' ? str_replace(' ', 'T', substr($closesAt, 0, 16)) : '';
$resultRecipients = (string) ($event['result_recipients'] ?? '');
$recipientLabels = [
    'voted' => 'Alle, die abgestimmt haben',
    'invited' => 'Alle Eingeladenen',
    'orga' => 'Nur das Orga-Team',
    'admin' => 'Nur die Admins',
];
$options = $event['options'];
$participants = $event['participants'];
$tally = $event['tally'];
$tokenStats = $event['token_stats'];
$decidedId = (int) ($event['decided_option_id'] ?? 0);
$kind = (string) ($event['kind'] ?? 'date_poll');
$kindLabel = ['date_poll' => 'Datumsabstimmung', 'fixed_date' => 'Fester Termin', 'poll' => 'Abstimmung'][$kind] ?? 'Termin';
$isPoll = $kind === 'poll';
$isFixed = $kind === 'fixed_date';

$answerShort = ['yes' => 'Ja', 'maybe' => 'Vielleicht', 'no' => 'Nein'];
$optionTitle = static fn (array $option): string => event_option_label($option);

// Kontakte für den Teilnehmer-Picker nach Kategorie gruppieren.
$byCategory = [];
foreach ($contacts as $contact) {
    $byCategory[(string) ($contact['category_name'] ?: 'Ohne Kategorie')][] = $contact;
}
ksort($byCategory);
?>
<p class="detail-backlink"><a href="<?= e(url('/termine')) ?>"><?= icon('chevron-right') ?>Zurück zu den Terminen</a></p>

<header class="contact-detail-head">
    <p class="eyebrow">Termin · <?= e($kindLabel) ?></p>
    <h1><?= e($event['title']) ?></h1>
    <div class="contact-detail-meta">
        <span class="events-status is-<?= e($event['status']) ?>"><?= e($statusLabel[$event['status']] ?? $event['status']) ?></span>
        <span class="muted">angelegt von <?= e($event['creator_name']) ?></span>
    </div>
</header>

<?php if ($closesAt !== '' && !$isFixed): ?>
    <section class="detail-card event-deadline-card">
        <div>
            <h2>Frist</h2>
            <p>
                <?php if ($event['status'] === 'open'): ?>
                    Endet <strong><?= e(format_deadline($closesAt)) ?></strong> · <?= e(time_until_hint($closesAt)) ?>
                <?php else: ?>
                    War auf <strong><?= e(format_deadline($closesAt)) ?></strong> gesetzt.
                <?php endif; ?>
            </p>
            <?php if ($resultRecipients !== '' && isset($recipientLabels[$resultRecipients])): ?>
                <p class="muted">Ergebnis-Mail nach dem Schließen: <?= e($recipientLabels[$resultRecipients]) ?><?= $event['result_mail_sent_at'] ? ' · bereits verschickt' : '' ?>.</p>
            <?php endif; ?>
        </div>
        <form method="post" action="<?= e(url('/termine/frist')) ?>" class="event-deadline-form">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= e((string) $event['id']) ?>">
            <label><span>Neue Frist</span><input type="datetime-local" name="closes_at" value="<?= e($closesAtLocal) ?>" min="<?= e(date('Y-m-d\TH:i')) ?>"></label>
            <button type="submit" class="ghost-button"><?= icon('clock') ?><span>Frist setzen</span></button>
        </form>
    </section>
<?php endif; ?>

<?php if (!$isPoll && $event['status'] === 'decided'): ?>
    <?php foreach ($options as $option): ?>
        <?php if ((int) $option['id'] === $decidedId): ?>
            <section class="detail-card event-decided">
                <h2><?= $isFixed ? 'Termin &amp; Zusagen' : 'Festgelegter Termin' ?></h2>
                <p class="event-decided-date"><?= e($optionTitle($option)) ?></p>
                <?php
                $yesList = array_values(array_filter($participants, static fn (array $p): bool => ($p['answers'][(int) $option['id']] ?? '') === 'yes'));
                $maybeList = array_values(array_filter($participants, static fn (array $p): bool => ($p['answers'][(int) $option['id']] ?? '') === 'maybe'));
                ?>
                <p class="muted"><?= count($yesList) ?> Zusagen, <?= count($maybeList) ?> Vielleicht.</p>
                <?php if ($yesList !== []): ?>
                    <p class="event-decided-names"><strong>Dabei:</strong> <?= e(implode(', ', array_map(static fn (array $p): string => trim($p['vorname'] . ' ' . $p['nachname']), $yesList))) ?></p>
                <?php endif; ?>
                <?php if (!$isFixed): ?>
                    <form method="post" action="<?= e(url('/termine/ergebnis')) ?>" class="event-inline-form">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="id" value="<?= e((string) $event['id']) ?>">
                        <input type="hidden" name="option_id" value="0">
                        <button type="submit" class="ghost-button">Festlegung aufheben</button>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>

<form method="post" action="<?= e(url('/termine/speichern')) ?>" class="contact-detail-form" data-detail-form>
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="id" value="<?= e((string) $event['id']) ?>">

    <section class="detail-card">
        <h2>Worum geht es?</h2>
        <div class="form-grid">
            <label class="full-width"><span>Titel <span class="required-marker" aria-hidden="true">*</span></span><input type="text" name="title" value="<?= e($event['title']) ?>" required></label>
            <label class="full-width"><span>Beschreibung</span><textarea name="description" rows="3"><?= e((string) ($event['description'] ?? '')) ?></textarea></label>
        </div>
    </section>

    <?php if ($isPoll): ?>
        <section class="detail-card">
            <h2>Antwortmöglichkeiten</h2>
            <p class="field-hint">Gleiche Möglichkeiten behalten ihre Stimmen. Entfernte löschen die zugehörigen Stimmen.</p>
            <div class="text-options" data-text-options>
                <?php foreach ($options as $i => $option): ?>
                    <div class="text-option-row">
                        <input type="text" name="option_label[]" value="<?= e((string) ($option['label'] ?? '')) ?>" aria-label="Antwortmöglichkeit <?= $i + 1 ?>">
                        <button type="button" class="danger-button icon-button" data-remove-text aria-label="Zeile entfernen"><?= icon('x') ?></button>
                    </div>
                <?php endforeach; ?>
                <?php if ($options === []): ?>
                    <div class="text-option-row">
                        <input type="text" name="option_label[]" value="" aria-label="Antwortmöglichkeit">
                        <button type="button" class="danger-button icon-button" data-remove-text aria-label="Zeile entfernen"><?= icon('x') ?></button>
                    </div>
                <?php endif; ?>
            </div>
            <button type="button" class="ghost-button" data-add-text><?= icon('plus') ?><span>Weitere Möglichkeit</span></button>
            <template id="textOptionTemplate">
                <div class="text-option-row">
                    <input type="text" name="option_label[]" value="" aria-label="Antwortmöglichkeit">
                    <button type="button" class="danger-button icon-button" data-remove-text aria-label="Zeile entfernen"><?= icon('x') ?></button>
                </div>
            </template>
        </section>
    <?php elseif ($isFixed): ?>
        <section class="detail-card">
            <h2>Termin</h2>
            <?php $only = $options[0] ?? ['option_date' => '', 'option_time' => '']; ?>
            <div class="form-grid">
                <label><span>Datum <span class="required-marker" aria-hidden="true">*</span></span><input type="date" name="option_date[]" value="<?= e((string) $only['option_date']) ?>" min="<?= e($today) ?>" required></label>
                <label><span>Uhrzeit</span><input type="text" name="option_time[]" value="<?= e((string) ($only['option_time'] ?? '')) ?>" placeholder="z. B. 18:00"></label>
            </div>
        </section>
    <?php else: ?>
        <section class="detail-card">
            <h2>Datumsvorschläge</h2>
            <p class="field-hint">Vorhandene Vorschläge behalten ihre Rückmeldungen. Entfernte Vorschläge löschen die zugehörigen Stimmen.</p>
            <div class="date-options" data-date-options>
                <?php foreach ($options as $i => $option): ?>
                    <div class="date-option-row">
                        <input type="date" name="option_date[]" value="<?= e((string) $option['option_date']) ?>" aria-label="Datum <?= $i + 1 ?>" min="<?= e($today) ?>">
                        <input type="text" name="option_time[]" value="<?= e((string) ($option['option_time'] ?? '')) ?>" aria-label="Uhrzeit <?= $i + 1 ?>" placeholder="Uhrzeit (optional)">
                        <button type="button" class="danger-button icon-button" data-remove-date aria-label="Zeile entfernen"><?= icon('x') ?></button>
                    </div>
                <?php endforeach; ?>
                <?php if ($options === []): ?>
                    <div class="date-option-row">
                        <input type="date" name="option_date[]" value="" aria-label="Datum" min="<?= e($today) ?>">
                        <input type="text" name="option_time[]" value="" aria-label="Uhrzeit" placeholder="Uhrzeit (optional)">
                        <button type="button" class="danger-button icon-button" data-remove-date aria-label="Zeile entfernen"><?= icon('x') ?></button>
                    </div>
                <?php endif; ?>
            </div>
            <button type="button" class="ghost-button" data-add-date><?= icon('plus') ?><span>Weiterer Vorschlag</span></button>
        </section>
    <?php endif; ?>

    <section class="detail-card">
        <h2>Eckdaten</h2>
        <div class="form-grid">
            <label><span>Ort</span><input type="text" name="location" value="<?= e((string) ($event['location'] ?? '')) ?>"></label>
            <?php if (!$isPoll): ?>
                <label><span>Uhrzeit</span><input type="text" name="time_note" value="<?= e((string) ($event['time_note'] ?? '')) ?>" placeholder="z. B. ab 18 Uhr"></label>
                <label><span>Kosten</span><input type="text" name="cost_note" value="<?= e((string) ($event['cost_note'] ?? '')) ?>"></label>
                <label><span>Mitbringen</span><input type="text" name="bring_note" value="<?= e((string) ($event['bring_note'] ?? '')) ?>"></label>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!$isFixed): ?>
        <section class="detail-card">
            <h2>Frist &amp; Ergebnis</h2>
            <p class="field-hint">Frist leer lassen = die Abstimmung schließt nicht von selbst. 48&nbsp;Stunden vor der Frist geht eine Erinnerung an alle raus, die noch nicht abgestimmt haben.</p>
            <div class="form-grid">
                <label>
                    <span>Abstimmung endet am</span>
                    <input type="datetime-local" name="closes_at" value="<?= e($closesAtLocal) ?>">
                </label>
                <label>
                    <span>Ergebnis danach mailen an</span>
                    <select name="result_recipients">
                        <option value="" <?= $resultRecipients === '' ? 'selected' : '' ?>>Niemanden automatisch</option>
                        <?php foreach ($recipientLabels as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $resultRecipients === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        </section>
    <?php endif; ?>

    <div class="detail-save-bar" hidden data-save-bar>
        <span class="detail-save-hint">Ungespeicherte Änderungen.</span>
        <div class="detail-save-actions">
            <button type="button" class="ghost-button" data-detail-reset>Verwerfen</button>
            <button type="submit">Speichern</button>
        </div>
    </div>

    <template id="dateOptionTemplate">
        <div class="date-option-row">
            <input type="date" name="option_date[]" value="" aria-label="Datum" min="<?= e($today) ?>">
            <input type="text" name="option_time[]" value="" aria-label="Uhrzeit" placeholder="Uhrzeit (optional)">
            <button type="button" class="danger-button icon-button" data-remove-date aria-label="Zeile entfernen"><?= icon('x') ?></button>
        </div>
    </template>
</form>

<section class="detail-card">
    <h2>Teilnehmerkreis</h2>
    <?php if ($participants === []): ?>
        <p class="field-hint">Noch niemand ausgewählt. Wähle die Personen, die abstimmen sollen – alle aus dem Adressbuch.</p>
    <?php else: ?>
        <p class="muted"><?= count($participants) ?> <?= count($participants) === 1 ? 'Person' : 'Personen' ?> · <?= (int) $event['answered_count'] ?> haben geantwortet.</p>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/termine/teilnehmer')) ?>" data-participant-picker>
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="id" value="<?= e((string) $event['id']) ?>">

        <details class="admin-drawer"<?= $participants === [] ? ' open' : '' ?>>
            <summary><span><?= icon('contacts') ?></span><span>Teilnehmer wählen</span></summary>
            <div class="admin-drawer-body">
                <div class="participant-picker-tools">
                    <button type="button" class="linkish" data-pick="all">Alle</button>
                    <button type="button" class="linkish" data-pick="none">Keine</button>
                    <?php foreach ($byCategory as $catName => $catContacts): ?>
                        <button type="button" class="linkish" data-pick-category="<?= e($catName) ?>">+ <?= e($catName) ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="participant-list">
                    <?php foreach ($byCategory as $catName => $catContacts): ?>
                        <p class="participant-group"><?= e($catName) ?></p>
                        <?php foreach ($catContacts as $contact): ?>
                            <label class="participant-option" data-category="<?= e($catName) ?>">
                                <input type="checkbox" name="contact_ids[]" value="<?= e((string) $contact['id']) ?>" <?= in_array((int) $contact['id'], $participantIds, true) ? 'checked' : '' ?>>
                                <span><?= e(trim($contact['vorname'] . ' ' . $contact['nachname'])) ?><?php if (($contact['emails'] ?? []) === []): ?> <span class="status-chip is-warn">keine Mail</span><?php endif; ?></span>
                            </label>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
                <div class="toolbar-actions">
                    <button type="submit">Teilnehmerkreis speichern</button>
                </div>
            </div>
        </details>
    </form>
</section>

<?php if ($participants !== [] && $options !== []): ?>
    <section class="detail-card">
        <h2><?= $isPoll ? 'Ergebnis' : 'Abstimmungsstand' ?></h2>
        <p class="muted">Wer hat wie geantwortet.<?= $kind === 'date_poll' ? ' Wähle unten das Ergebnis.' : '' ?></p>

        <form method="post" action="<?= e(url('/termine/ergebnis')) ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= e((string) $event['id']) ?>">

            <div class="vote-matrix-wrap">
                <table class="vote-matrix">
                    <thead>
                        <tr>
                            <th scope="col">Person</th>
                            <?php foreach ($options as $option): ?>
                                <th scope="col">
                                    <span class="vote-col-date"><?= e($optionTitle($option)) ?></span>
                                    <span class="vote-col-tally">
                                        <span class="is-yes"><?= (int) $tally[(int) $option['id']]['yes'] ?> ✓</span>
                                        <span class="is-maybe"><?= (int) $tally[(int) $option['id']]['maybe'] ?> ?</span>
                                        <span class="is-no"><?= (int) $tally[(int) $option['id']]['no'] ?> ✗</span>
                                    </span>
                                    <?php if ($kind === 'date_poll'): ?>
                                        <label class="vote-pick">
                                            <input type="radio" name="option_id" value="<?= e((string) $option['id']) ?>" <?= (int) $option['id'] === $decidedId ? 'checked' : '' ?>>
                                            <span>als Ergebnis</span>
                                        </label>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($participants as $participant): ?>
                            <tr>
                                <th scope="row">
                                    <?= e(trim($participant['vorname'] . ' ' . $participant['nachname'])) ?>
                                    <?php
                                    $stat = $tokenStats[(int) $participant['id']] ?? null;
                                    if ($stat !== null && $stat['sources'] > 1):
                                    ?>
                                        <span class="vote-warn" title="Über diesen Link haben verschiedene Geräte abgestimmt">⚠ <?= (int) $stat['sources'] ?> Quellen</span>
                                    <?php endif; ?>
                                </th>
                                <?php foreach ($options as $option): ?>
                                    <?php $a = $participant['answers'][(int) $option['id']] ?? ''; ?>
                                    <td class="vote-cell is-<?= $a !== '' ? e($a) : 'none' ?>"><?= $a !== '' ? e($answerShort[$a]) : '–' ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($kind === 'date_poll'): ?>
                <div class="toolbar-actions">
                    <button type="submit"><?= icon('check') ?><span>Als Termin festlegen</span></button>
                </div>
            <?php endif; ?>
        </form>
    </section>
<?php endif; ?>

<?php if ($participants !== []): ?>
    <section class="detail-card">
        <h2>Teilnehmer erreichen</h2>
        <p class="field-hint">Jede Person hat einen eigenen Abstimmungs-Link. Wer über einen fremden Link abstimmt, sieht eine Warnung.</p>

        <?php if (can('mail.send')): ?>
            <form method="post" action="<?= e(url('/termine/nachricht')) ?>" class="event-message-actions">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" value="<?= e((string) $event['id']) ?>">
                <p class="field-hint">Nachricht mit Platzhalter <code>{Abstimmungslink}</code> – der wird je Person durch den persönlichen Link ersetzt.</p>
                <div class="toolbar-actions">
                    <button type="submit" name="filter" value="all" class="button-link"><?= icon('mail') ?><span>An alle Teilnehmer</span></button>
                    <?php if ($event['status'] === 'decided'): ?>
                        <button type="submit" name="filter" value="confirmed" class="ghost-button">Nur an Zusagen</button>
                    <?php endif; ?>
                    <button type="submit" name="filter" value="pending" class="ghost-button">Nur an Offene</button>
                </div>
            </form>
        <?php endif; ?>

        <details class="admin-drawer">
            <summary><span><?= icon('link') ?></span><span>Links einzeln kopieren</span></summary>
            <div class="admin-drawer-body">
                <div class="toolbar-actions">
                    <button type="button" class="ghost-button" data-copy="#allVoteLinks"><?= icon('copy') ?><span>Alle Links kopieren</span></button>
                </div>
                <textarea id="allVoteLinks" rows="6" readonly spellcheck="false"><?php foreach ($participants as $p): ?><?= e(trim($p['vorname'] . ' ' . $p['nachname'])) ?>: <?= e($voteBaseUrl . '?token=' . $p['token']) ?><?= "\n" ?><?php endforeach; ?></textarea>
            </div>
        </details>
    </section>

    <?php if (!empty($event['response_log'])): ?>
        <section class="detail-card">
            <h2>Verlauf der Abstimmung</h2>
            <p class="muted">Jede gespeicherte Rückmeldung – neueste zuerst. Nur für die Verwaltung sichtbar.</p>
            <ol class="event-log">
                <?php foreach ($event['response_log'] as $entry): ?>
                    <li>
                        <span class="event-log-when"><?= e(format_datetime($entry['created_at'])) ?></span>
                        <span class="event-log-who"><?= e(trim($entry['vorname'] . ' ' . $entry['nachname'])) ?></span>
                        <span class="event-log-what">
                            <?= e(format_weekday_date($entry['option_date'])) ?><?= trim((string) ($entry['option_time'] ?? '')) !== '' ? ', ' . e($entry['option_time']) : '' ?>:
                            <strong class="event-log-answer is-<?= e($entry['answer']) ?>"><?= e($answerShort[$entry['answer']]) ?></strong>
                            <?php if ($entry['via'] === 'token'): ?><span class="muted">(Link)</span><?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endif; ?>
<?php endif; ?>

<section class="detail-card detail-danger">
    <h2>Termin abschließen</h2>
    <p class="muted">Archivierte Termine verschwinden aus der Übersicht, bleiben aber im Archiv. Löschen entfernt alles unwiderruflich.</p>
    <div class="toolbar-actions">
        <?php if ($event['status'] === 'open' && !$isFixed): ?>
            <form method="post" action="<?= e(url('/termine/status')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" value="<?= e((string) $event['id']) ?>">
                <input type="hidden" name="status" value="closed">
                <button type="submit" class="ghost-button"><?= icon('lock') ?><span>Abstimmung jetzt schließen</span></button>
            </form>
        <?php elseif ($event['status'] === 'closed'): ?>
            <form method="post" action="<?= e(url('/termine/status')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" value="<?= e((string) $event['id']) ?>">
                <input type="hidden" name="status" value="open">
                <button type="submit" class="ghost-button"><?= icon('unlock') ?><span>Wieder öffnen</span></button>
            </form>
        <?php endif; ?>
        <?php if ($event['status'] !== 'archived'): ?>
            <form method="post" action="<?= e(url('/termine/status')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" value="<?= e((string) $event['id']) ?>">
                <input type="hidden" name="status" value="archived">
                <button type="submit" class="ghost-button"><?= icon('archive') ?><span>Archivieren</span></button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= e(url('/termine/status')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" value="<?= e((string) $event['id']) ?>">
                <input type="hidden" name="status" value="open">
                <button type="submit" class="ghost-button">Wieder öffnen</button>
            </form>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/termine/loeschen')) ?>" data-confirm="Termin „<?= e($event['title']) ?>“ endgültig löschen?">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= e((string) $event['id']) ?>">
            <button type="submit" class="danger-button"><?= icon('trash') ?><span>Löschen</span></button>
        </form>
    </div>
</section>

<?php
$statusLabel = ['open' => 'Abstimmung läuft', 'decided' => 'Termin steht', 'archived' => 'Archiviert'];
$today = (new DateTimeImmutable('now'))->format('Y-m-d');
$options = $event['options'];
$participants = $event['participants'];
$tally = $event['tally'];
$tokenStats = $event['token_stats'];
$decidedId = (int) ($event['decided_option_id'] ?? 0);

$answerShort = ['yes' => 'Ja', 'maybe' => 'Vielleicht', 'no' => 'Nein'];
$optionTitle = static function (array $option): string {
    $label = format_weekday_date($option['option_date']);
    $time = trim((string) ($option['option_time'] ?? ''));

    return $time !== '' ? $label . ', ' . $time : $label;
};

// Kontakte für den Teilnehmer-Picker nach Kategorie gruppieren.
$byCategory = [];
foreach ($contacts as $contact) {
    $byCategory[(string) ($contact['category_name'] ?: 'Ohne Kategorie')][] = $contact;
}
ksort($byCategory);
?>
<p class="detail-backlink"><a href="<?= e(url('/termine')) ?>"><?= icon('chevron-right') ?>Zurück zu den Terminen</a></p>

<header class="contact-detail-head">
    <p class="eyebrow">Termin</p>
    <h1><?= e($event['title']) ?></h1>
    <div class="contact-detail-meta">
        <span class="events-status is-<?= e($event['status']) ?>"><?= e($statusLabel[$event['status']] ?? $event['status']) ?></span>
        <span class="muted">angelegt von <?= e($event['creator_name']) ?></span>
    </div>
</header>

<?php if ($event['status'] === 'decided'): ?>
    <?php foreach ($options as $option): ?>
        <?php if ((int) $option['id'] === $decidedId): ?>
            <section class="detail-card event-decided">
                <h2>Festgelegter Termin</h2>
                <p class="event-decided-date"><?= e($optionTitle($option)) ?></p>
                <?php
                $yesList = array_values(array_filter($participants, static fn (array $p): bool => ($p['answers'][(int) $option['id']] ?? '') === 'yes'));
                $maybeList = array_values(array_filter($participants, static fn (array $p): bool => ($p['answers'][(int) $option['id']] ?? '') === 'maybe'));
                ?>
                <p class="muted"><?= count($yesList) ?> Zusagen, <?= count($maybeList) ?> Vielleicht.</p>
                <?php if ($yesList !== []): ?>
                    <p class="event-decided-names"><strong>Dabei:</strong> <?= e(implode(', ', array_map(static fn (array $p): string => trim($p['vorname'] . ' ' . $p['nachname']), $yesList))) ?></p>
                <?php endif; ?>
                <form method="post" action="<?= e(url('/termine/ergebnis')) ?>" class="event-inline-form">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="id" value="<?= e((string) $event['id']) ?>">
                    <input type="hidden" name="option_id" value="0">
                    <button type="submit" class="ghost-button">Festlegung aufheben</button>
                </form>
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

    <section class="detail-card">
        <h2>Eckdaten</h2>
        <div class="form-grid">
            <label><span>Ort</span><input type="text" name="location" value="<?= e((string) ($event['location'] ?? '')) ?>"></label>
            <label><span>Uhrzeit</span><input type="text" name="time_note" value="<?= e((string) ($event['time_note'] ?? '')) ?>" placeholder="z. B. ab 18 Uhr"></label>
            <label><span>Kosten</span><input type="text" name="cost_note" value="<?= e((string) ($event['cost_note'] ?? '')) ?>"></label>
            <label><span>Mitbringen</span><input type="text" name="bring_note" value="<?= e((string) ($event['bring_note'] ?? '')) ?>"></label>
        </div>
    </section>

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
        <h2>Abstimmungsstand</h2>
        <p class="muted">Wer hat wie geantwortet. Wähle unten das Ergebnis.</p>

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
                                    <label class="vote-pick">
                                        <input type="radio" name="option_id" value="<?= e((string) $option['id']) ?>" <?= (int) $option['id'] === $decidedId ? 'checked' : '' ?>>
                                        <span>als Ergebnis</span>
                                    </label>
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

            <div class="toolbar-actions">
                <button type="submit"><?= icon('check') ?><span>Als Termin festlegen</span></button>
            </div>
        </form>
    </section>
<?php endif; ?>

<?php if ($participants !== []): ?>
    <section class="detail-card">
        <h2>Abstimmungs-Links</h2>
        <p class="field-hint">Jede Person hat einen eigenen Link. Wer über einen fremden Link abstimmt, sieht eine Warnung. In v0.32 lassen sich die Links direkt per Nachricht verschicken.</p>
        <div class="toolbar-actions">
            <button type="button" class="ghost-button" data-copy="#allVoteLinks"><?= icon('copy') ?><span>Alle Links kopieren</span></button>
        </div>
        <textarea id="allVoteLinks" rows="6" readonly spellcheck="false"><?php foreach ($participants as $p): ?><?= trim($p['vorname'] . ' ' . $p['nachname']) ?>: <?= $voteBaseUrl . '?token=' . $p['token'] ?><?= "\n" ?><?php endforeach; ?></textarea>
    </section>
<?php endif; ?>

<section class="detail-card detail-danger">
    <h2>Termin abschließen</h2>
    <p class="muted">Archivierte Termine verschwinden aus der Übersicht, bleiben aber im Archiv. Löschen entfernt alles unwiderruflich.</p>
    <div class="toolbar-actions">
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
        <form method="post" action="<?= e(url('/termine/loeschen')) ?>" onsubmit="return confirm('Termin „<?= e($event['title']) ?>“ endgültig löschen?');">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= e((string) $event['id']) ?>">
            <button type="submit" class="danger-button"><?= icon('trash') ?><span>Löschen</span></button>
        </form>
    </div>
</section>

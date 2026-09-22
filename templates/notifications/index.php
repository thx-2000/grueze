<?php
/** @var list<array<string,mixed>> $subscriptions */
/** @var array<string,string> $frequencies */
/** @var list<array<string,mixed>> $contacts */
/** @var list<array<string,mixed>> $groups */
$eventLabel = static fn (string $e): string => $e === 'login' ? 'Login' : 'Änderung';
$targetLabel = static function (array $s): string {
    return match ($s['scope']) {
        'all' => 'Alle',
        'contact' => trim((string) ($s['contact_name'] ?? '')) !== '' ? (string) $s['contact_name'] : 'Gelöschte Person',
        'group' => trim((string) ($s['group_name'] ?? '')) !== '' ? (string) $s['group_name'] : 'Gelöschte Gruppe',
        default => '–',
    };
};
?>
<header class="page-head">
    <p class="eyebrow">Verwaltung</p>
    <h1>Meine Benachrichtigungen</h1>
    <p class="muted">Per Mail informiert werden, wenn sich jemand einloggt oder Daten geändert werden – wahlweise für alle, für eine einzelne Person oder für eine Gruppe, mit eigenem Zeitrahmen. „Sofort" heißt: beim nächsten Cron-Lauf, also mit rund einer Minute Verzögerung.</p>
</header>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Aktuelle Abos</h2>
            <p class="muted"><?= count($subscriptions) ?> <?= count($subscriptions) === 1 ? 'Abo' : 'Abos' ?></p>
        </div>
    </div>
    <?php if ($subscriptions === []): ?>
        <p class="completeness-clear"><?= icon('check') ?><span>Noch keine Benachrichtigung eingerichtet.</span></p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Ereignis</th>
                        <th>Ziel</th>
                        <th>Zeitrahmen</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscriptions as $s): ?>
                        <tr>
                            <td><?= e($eventLabel((string) $s['event_type'])) ?></td>
                            <td><?= e($targetLabel($s)) ?></td>
                            <td>
                                <form method="post" action="<?= e(url('/verwaltung/benachrichtigungen/rhythmus')) ?>" class="inline-form">
                                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                    <input type="hidden" name="id" value="<?= e((string) $s['id']) ?>">
                                    <select name="frequency">
                                        <?php foreach ($frequencies as $key => $label): ?>
                                            <option value="<?= e($key) ?>" <?= $key === $s['frequency'] ? 'selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="ghost-button">Speichern</button>
                                </form>
                            </td>
                            <td>
                                <form method="post" action="<?= e(url('/verwaltung/benachrichtigungen/entfernen')) ?>">
                                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                    <input type="hidden" name="id" value="<?= e((string) $s['id']) ?>">
                                    <button type="submit" class="ghost-button"><?= icon('trash') ?><span class="visually-hidden">Entfernen</span></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-head">
        <div><h2>Neues Abo hinzufügen</h2></div>
    </div>
    <form method="post" action="<?= e(url('/verwaltung/benachrichtigungen/abonnieren')) ?>" class="form-grid">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <label>
            <span>Ereignis</span>
            <select name="event_type">
                <option value="login">Login</option>
                <option value="change">Änderung</option>
            </select>
        </label>
        <label>
            <span>Ziel</span>
            <select name="target">
                <option value="all">Alle</option>
                <optgroup label="Person">
                    <?php foreach ($contacts as $c): ?>
                        <option value="contact:<?= (int) $c['id'] ?>"><?= e(trim((string) $c['vorname'] . ' ' . (string) $c['nachname'])) ?></option>
                    <?php endforeach; ?>
                </optgroup>
                <optgroup label="Gruppe">
                    <?php foreach ($groups as $g): ?>
                        <option value="group:<?= (int) $g['id'] ?>"><?= e((string) $g['name']) ?></option>
                    <?php endforeach; ?>
                </optgroup>
            </select>
        </label>
        <label>
            <span>Zeitrahmen</span>
            <select name="frequency">
                <?php foreach ($frequencies as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $key === 'sofort' ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="form-actions full-width">
            <button type="submit">Hinzufügen</button>
        </div>
    </form>
</section>

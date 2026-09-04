<?php
/**
 * @var list<array<string,mixed>> $active
 * @var list<array<string,mixed>> $history
 * @var string $currentHash
 * @var int $windowMinutes
 * @var bool $showIp
 */

// Grobe Geräteerkennung aus dem User-Agent – nur fürs schnelle Wiedererkennen.
$deviceLabel = static function (string $ua): string {
    $ua = trim($ua);
    if ($ua === '') {
        return 'unbekannt';
    }
    $os = match (true) {
        str_contains($ua, 'iPhone'), str_contains($ua, 'iPad') => 'iOS',
        str_contains($ua, 'Android') => 'Android',
        str_contains($ua, 'Windows') => 'Windows',
        str_contains($ua, 'Mac OS') => 'Mac',
        str_contains($ua, 'Linux') => 'Linux',
        default => '',
    };
    $browser = match (true) {
        str_contains($ua, 'Edg/') => 'Edge',
        str_contains($ua, 'Chrome/') => 'Chrome',
        str_contains($ua, 'Firefox/') => 'Firefox',
        str_contains($ua, 'Safari/') && !str_contains($ua, 'Chrome/') => 'Safari',
        default => '',
    };

    return trim(($browser !== '' ? $browser : 'Browser') . ($os !== '' ? ' · ' . $os : '')) ?: 'unbekannt';
};

$retentionDays = (int) config('security.session_retention_days', 90);

/** Eine Zeile rendern (aktiv oder Verlauf). */
$row = static function (array $r) use ($deviceLabel, $showIp): void {
    ?>
    <td><?= e(format_datetime((string) $r['created_at'])) ?></td>
    <td><?= e(format_datetime((string) $r['last_seen_at'])) ?></td>
    <?php if ($showIp): ?><td><?= e((string) ($r['ip_address'] ?: '—')) ?></td><?php endif; ?>
    <td><?= e($deviceLabel((string) $r['user_agent'])) ?></td>
    <?php
};
?>
<header class="page-head">
    <p class="eyebrow">Verwaltung</p>
    <h1>Anmeldungen</h1>
    <p class="muted">Wer ist gerade angemeldet und wer hat sich zuletzt angemeldet. „Online" heißt: in den letzten <?= e((string) $windowMinutes) ?> Minuten aktiv.</p>
    <?php if (!$showIp): ?>
        <p class="field-hint"><?= icon('lock') ?><span>IP-Adressen werden in dieser Installation nicht gespeichert (<code>security.store_ip</code>).</span></p>
    <?php endif; ?>
</header>

<section class="panel stack">
    <div class="panel-head">
        <div>
            <h2>Gerade online</h2>
            <p class="muted"><?= count($active) === 1 ? 'Eine aktive Sitzung' : e((string) count($active)) . ' aktive Sitzungen' ?>.</p>
        </div>
    </div>

    <?php if ($active === []): ?>
        <p class="muted">Zurzeit ist niemand angemeldet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Wer</th>
                        <th>Angemeldet seit</th>
                        <th>Zuletzt aktiv</th>
                        <?php if ($showIp): ?><th>Von wo</th><?php endif; ?>
                        <th>Gerät</th>
                        <th><span class="visually-hidden">Aktion</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active as $entry): ?>
                        <?php $isCurrent = hash_equals($currentHash, (string) $entry['session_hash']); ?>
                        <tr>
                            <td>
                                <strong><?= e((string) $entry['user_name']) ?></strong>
                                <span class="muted"><?= e(role_label((string) $entry['role_name'])) ?></span>
                                <?php if ($isCurrent): ?><span class="status-chip is-ok">diese Sitzung</span><?php endif; ?>
                            </td>
                            <?php $row($entry); ?>
                            <td>
                                <?php if (!$isCurrent): ?>
                                    <form method="post" action="<?= e(url('/verwaltung/anmeldungen/beenden')) ?>" data-confirm="Diese Sitzung wirklich beenden? Die Person muss sich neu anmelden.">
                                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="id" value="<?= e((string) $entry['id']) ?>">
                                        <button type="submit" class="ghost-button"><?= icon('close') ?><span>Beenden</span></button>
                                    </form>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel stack">
    <div class="panel-head">
        <div>
            <h2>Anmelde-Verlauf</h2>
            <p class="muted">Die letzten Sitzungen. Ältere Einträge werden nach <?= e((string) $retentionDays) ?> Tagen automatisch entfernt.</p>
        </div>
    </div>

    <?php if ($history === []): ?>
        <p class="muted">Noch keine Anmeldungen aufgezeichnet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Wer</th>
                        <th>Angemeldet</th>
                        <th>Zuletzt aktiv</th>
                        <?php if ($showIp): ?><th>Von wo</th><?php endif; ?>
                        <th>Gerät</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $entry): ?>
                        <?php
                        $status = match (true) {
                            $entry['revoked_at'] !== null => ['warn', 'aus der Ferne beendet'],
                            $entry['ended_at'] !== null => ['muted', 'abgemeldet'],
                            strtotime((string) $entry['last_seen_at']) >= time() - (int) config('app.session_timeout', 1800) => ['ok', 'online'],
                            default => ['muted', 'abgelaufen'],
                        };
                        ?>
                        <tr>
                            <td>
                                <strong><?= e((string) $entry['user_name']) ?></strong>
                                <span class="muted"><?= e(role_label((string) $entry['role_name'])) ?></span>
                            </td>
                            <?php $row($entry); ?>
                            <td><span class="status-chip is-<?= e($status[0]) ?>"><?= e($status[1]) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

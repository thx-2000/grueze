<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SettingRepository;
use App\Repositories\StorageReferenceRepository;
use App\Repositories\UserRepository;

/**
 * Dateiprüfung: Sind alle Dateien noch da, auf die die Datenbank verweist?
 *
 * Fehlen Dateien, geht eine Alarm-Mail an alle aktiven Admins – bewusst
 * direkt und nicht über die Benachrichtigungs-Abos, weil ein Alarm nicht davon
 * abhängen darf, ob ihn jemand abonniert hat. Gemeldet wird nur, wenn eine
 * Datei neu fehlt: Bleibt die Lage gleich oder bessert sie sich (teilweise
 * Wiederherstellung), kommt keine weitere Mail. Taucht eine Datei wieder auf
 * und fehlt später erneut, gilt das wieder als neu.
 *
 * Läuft über `/intern/cron` höchstens stündlich, zusätzlich per Knopf auf der
 * Datensicherungs-Seite.
 */
final class StorageCheckService
{
    private const INTERVAL_SECONDS = 3600;
    private const SAMPLE_LIMIT = 25;
    private const MAIL_EXAMPLES = 15;

    private const AREA_LABELS = [
        'uploads' => 'Profilbilder & Logo',
        'media' => 'Galerie-Medien',
        'documents' => 'Dokumente',
    ];

    public function __construct(
        private StorageReferenceRepository $references,
        private SettingRepository $settings,
        private UserRepository $users,
        private MailService $mailer,
        private UploadService $uploads,
        private MediaService $media,
        private DocumentStorageService $documents,
    ) {
    }

    public static function areaLabels(): array
    {
        return self::AREA_LABELS;
    }

    /** @return array{ran: bool, checked?: int, missing?: int, alerted?: int} */
    public function runScheduled(): array
    {
        $last = (int) $this->settings->get('storage_check_last_run', '0');
        if ($last > 0 && time() - $last < self::INTERVAL_SECONDS) {
            return ['ran' => false];
        }

        return ['ran' => true] + $this->runNow();
    }

    /** @return array{checked: int, missing: int, alerted: int} */
    public function runNow(): array
    {
        $result = $this->check();
        $missing = $result['missing'];

        $byArea = array_fill_keys(array_keys(self::AREA_LABELS), 0);
        foreach ($missing as $item) {
            $byArea[$item['area']] = ($byArea[$item['area']] ?? 0) + 1;
        }

        $this->settings->set('storage_check_last_run', (string) time());
        $this->settings->set('storage_check_result', (string) json_encode([
            'checked_at' => date('Y-m-d H:i:s'),
            'checked' => $result['checked'],
            'missing_count' => count($missing),
            'by_area' => $byArea,
            'sample' => array_slice($missing, 0, self::SAMPLE_LIMIT),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return [
            'checked' => $result['checked'],
            'missing' => count($missing),
            'alerted' => $this->alertIfNew($missing, $result['checked'], $byArea),
        ];
    }

    /** Letztes gespeichertes Ergebnis für die Oberfläche, oder null vor der ersten Prüfung. */
    public function lastResult(): ?array
    {
        $raw = (string) $this->settings->get('storage_check_result', '');
        $decoded = $raw !== '' ? json_decode($raw, true) : null;

        return is_array($decoded) ? $decoded : null;
    }

    /** @return array{checked: int, missing: list<array{area: string, label: string, path: string}>} */
    public function check(): array
    {
        $checked = 0;
        $missing = [];
        $seen = [];

        foreach ($this->references->fileReferences() as $ref) {
            // Gedenkseiten übernehmen oft das Foto des Kontakts – dieselbe Datei nur einmal zählen.
            $key = $ref['area'] . '|' . $ref['path'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $checked++;

            if (!$this->exists($ref['area'], $ref['path'])) {
                $missing[] = $ref;
            }
        }

        return ['checked' => $checked, 'missing' => $missing];
    }

    private function exists(string $area, string $path): bool
    {
        if ($area === 'uploads') {
            return $this->uploads->publicFileExists($path);
        }

        $absolute = $area === 'media'
            ? $this->media->absolutePath($path)
            : $this->documents->absolutePath($path);

        return $absolute !== null && is_file($absolute);
    }

    /**
     * @param list<array{area: string, label: string, path: string}> $missing
     * @param array<string, int> $byArea
     */
    private function alertIfNew(array $missing, int $checked, array $byArea): int
    {
        $current = [];
        foreach ($missing as $item) {
            $current[substr(sha1($item['area'] . '|' . $item['path']), 0, 16)] = true;
        }

        $stored = json_decode((string) $this->settings->get('storage_check_alerted', '[]'), true);
        $alerted = is_array($stored) ? array_fill_keys($stored, true) : [];

        // Wieder vorhandene Dateien vergessen – fehlen sie später erneut, ist das ein neuer Befund.
        $stillMissing = array_intersect_key($alerted, $current);
        $new = array_diff_key($current, $alerted);

        if ($new === []) {
            $this->rememberAlerted($stillMissing);

            return 0;
        }

        $identity = $this->settings->mailIdentity();
        $subject = $this->subject(count($missing));
        $body = $this->body($missing, $checked, $byArea, count($new));

        $sent = 0;
        foreach ($this->users->activeByRoleNames(['admin']) as $admin) {
            try {
                $this->mailer->sendSystemMail($identity, (string) $admin['email'], $subject, $body);
                $sent++;
            } catch (\Throwable) {
                // Nächster Admin. Ohne eine einzige erfolgreiche Mail bleibt der Befund neu und wird beim nächsten Lauf erneut versucht.
            }
        }

        $this->rememberAlerted($sent > 0 ? $current : $stillMissing);

        return $sent;
    }

    /** @param array<string, true> $keys */
    private function rememberAlerted(array $keys): void
    {
        $this->settings->set('storage_check_alerted', (string) json_encode(array_keys($keys)));
    }

    private function subject(int $count): string
    {
        $short = trim(apply_branding_placeholders('{kurzname}'));
        $prefix = $short !== '' ? '[' . $short . '] ' : '';

        return $prefix . 'Achtung: ' . $count . ($count === 1 ? ' Datei fehlt' : ' Dateien fehlen') . ' auf dem Server';
    }

    /**
     * @param list<array{area: string, label: string, path: string}> $missing
     * @param array<string, int> $byArea
     */
    private function body(array $missing, int $checked, array $byArea, int $newCount): string
    {
        $lines = [];
        $lines[] = 'Die automatische Dateiprüfung hat festgestellt, dass ' . count($missing) . ' von ' . $checked
            . ' Dateien ' . (count($missing) === 1 ? 'fehlt' : 'fehlen') . ', auf die die Datenbank verweist.';
        if ($newCount < count($missing)) {
            $lines[] = 'Davon neu seit der letzten Meldung: ' . $newCount . '.';
        }
        $lines[] = '';
        foreach (self::AREA_LABELS as $area => $label) {
            if (($byArea[$area] ?? 0) > 0) {
                $lines[] = '– ' . $label . ': ' . $byArea[$area];
            }
        }
        $lines[] = '';
        $lines[] = 'Beispiele:';
        foreach (array_slice($missing, 0, self::MAIL_EXAMPLES) as $item) {
            $lines[] = '– ' . $item['label'] . ' (' . $item['path'] . ')';
        }
        if (count($missing) > self::MAIL_EXAMPLES) {
            $lines[] = '– … und ' . (count($missing) - self::MAIL_EXAMPLES) . ' weitere';
        }
        $lines[] = '';
        $lines[] = 'Die Einträge selbst sind noch vorhanden, nur die Dateien fehlen. Häufigste Ursache: Ein Deployment '
            . 'oder eine Wiederherstellung hat Ordner auf dem Server überschrieben. Bitte zeitnah prüfen und die '
            . 'Dateien aus einer Sicherung des Webspace zurückholen – je früher, desto eher ist der alte Stand noch '
            . 'in den Sicherungen enthalten.';
        $lines[] = '';
        $lines[] = 'Details und erneute Prüfung: ' . url('/admin/backup');
        $lines[] = '';
        $lines[] = 'Diese Meldung kommt nur, wenn Dateien neu fehlen. Bleibt die Lage gleich oder bessert sie sich, wird sie nicht wiederholt.';

        return implode("\n", $lines);
    }
}

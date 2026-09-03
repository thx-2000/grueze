<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Erzeugt eine iCalendar-Datei (.ics) für einen festgelegten Termin, damit
 * Teilnehmer ihn mit einem Klick in ihren Kalender übernehmen können.
 */
final class IcalService
{
    /**
     * @param array<string,mixed> $event  aus EventRepository::findByIcalUid()
     *   (mit `options` und `decided_option_id`)
     * @return string|null  null, wenn der Termin (noch) kein festes Datum hat
     */
    public function forEvent(array $event, string $host): ?string
    {
        $decidedId = (int) ($event['decided_option_id'] ?? 0);
        $option = null;
        foreach ((array) ($event['options'] ?? []) as $o) {
            if ((int) $o['id'] === $decidedId) {
                $option = $o;
            }
        }
        $date = trim((string) ($option['option_date'] ?? ''));
        if ($option === null || $date === '') {
            return null;
        }

        $time = trim((string) ($option['option_time'] ?? ''));
        [$start, $end, $allDay] = $this->window($date, $time);

        $uid = preg_replace('/[^a-f0-9]/i', '', (string) ($event['ical_uid'] ?? '')) ?: bin2hex(random_bytes(8));
        $stamp = gmdate('Ymd\THis\Z');

        $descParts = array_filter([
            trim((string) ($event['description'] ?? '')),
            $this->kv('Uhrzeit', $event['time_note'] ?? ''),
            $this->kv('Kosten', $event['cost_note'] ?? ''),
            $this->kv('Mitbringen', $event['bring_note'] ?? ''),
        ], static fn (string $s): bool => $s !== '');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//GRUEZE//Termine//DE',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . $uid . '@' . $host,
            'DTSTAMP:' . $stamp,
            $allDay ? 'DTSTART;VALUE=DATE:' . $start : 'DTSTART:' . $start,
            $allDay ? 'DTEND;VALUE=DATE:' . $end : 'DTEND:' . $end,
            'SUMMARY:' . $this->esc((string) ($event['title'] ?? 'Termin')),
        ];
        if (trim((string) ($event['location'] ?? '')) !== '') {
            $lines[] = 'LOCATION:' . $this->esc((string) $event['location']);
        }
        if ($descParts !== []) {
            $lines[] = 'DESCRIPTION:' . $this->esc(implode("\n", $descParts));
        }
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([$this, 'fold'], $lines)) . "\r\n";
    }

    /**
     * @return array{0:string,1:string,2:bool}  [dtstart, dtend, allDay]
     */
    private function window(string $date, string $time): array
    {
        $hm = null;
        if (preg_match('/(\d{1,2})[:.](\d{2})/', $time, $m)) {
            $hm = [min(23, (int) $m[1]), min(59, (int) $m[2])];
        }

        try {
            $day = new \DateTimeImmutable($date);
        } catch (\Throwable) {
            $day = new \DateTimeImmutable('today');
        }

        if ($hm === null) {
            return [$day->format('Ymd'), $day->modify('+1 day')->format('Ymd'), true];
        }

        $start = $day->setTime($hm[0], $hm[1]);
        $end = $start->modify('+2 hours');

        return [$start->format('Ymd\THis'), $end->format('Ymd\THis'), false];
    }

    private function kv(string $label, mixed $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '' : $label . ': ' . $value;
    }

    private function esc(string $value): string
    {
        return str_replace(
            ["\\", "\r\n", "\n", ",", ";"],
            ['\\\\', '\\n', '\\n', '\\,', '\\;'],
            $value
        );
    }

    /** RFC-5545-Zeilenfaltung bei 75 Oktetten. */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }
        $out = '';
        $chunk = 75;
        while (strlen($line) > $chunk) {
            $out .= substr($line, 0, $chunk) . "\r\n ";
            $line = substr($line, $chunk);
            $chunk = 74;
        }

        return $out . $line;
    }
}

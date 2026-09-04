<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Minimaler ZIP-Writer, der eine ZIP-Datei direkt in den HTTP-Ausgabe-Stream
 * schreibt (STORE-Methode, unkomprimiert) – ohne komplette temporäre
 * ZIP-Datei auf der Platte. Für die Medien-Sicherung: der Download beginnt
 * sofort, und es wird nie doppelt so viel Plattenplatz wie die Original-
 * dateien selbst gebraucht.
 *
 * Bewusst kein ZIP64 – die Größen sind ohnehin durch `media.backup_max_bytes`
 * gedeckelt (Standard 2 GiB), weit unter der 4-GiB-Grenze von ZIP32.
 * Bewusst keine Kompression – Fotos/Videos sind eh schon komprimiert,
 * `deflate` würde kaum etwas sparen, aber CPU und Zeit kosten.
 */
final class StreamZip
{
    private int $offset = 0;

    /** @var list<array{name:string,crc:int,size:int,offset:int,time:int,date:int}> */
    private array $entries = [];

    public function addFile(string $absPath, string $entryName): void
    {
        $size = @filesize($absPath);
        $crcHex = @hash_file('crc32b', $absPath);
        if ($size === false || $crcHex === false) {
            return;
        }
        $mtime = @filemtime($absPath) ?: time();
        $this->writeLocalHeader($entryName, (int) hexdec($crcHex), $size, $mtime);

        $handle = fopen($absPath, 'rb');
        if ($handle !== false) {
            fpassthru($handle);
            fclose($handle);
            $this->offset += $size;
        }
    }

    public function addFromString(string $entryName, string $content): void
    {
        $crc = (int) hexdec(hash('crc32b', $content));
        $size = strlen($content);
        $this->writeLocalHeader($entryName, $crc, $size, time());
        echo $content;
        $this->offset += $size;
    }

    private function writeLocalHeader(string $name, int $crc, int $size, int $mtime): void
    {
        [$dosTime, $dosDate] = self::dosDateTime($mtime);
        $header = pack(
            'VvvvvvVVVvv',
            0x04034b50, // Signatur „lokaler Datei-Header"
            20,         // benötigte Version (2.0 – reicht für STORE)
            0,          // Flags
            0,          // Methode: 0 = STORE (unkomprimiert)
            $dosTime,
            $dosDate,
            $crc,
            $size,      // komprimierte Größe = unkomprimierte Größe (STORE)
            $size,
            strlen($name),
            0           // Extra-Feld-Länge
        );
        echo $header . $name;
        $this->entries[] = ['name' => $name, 'crc' => $crc, 'size' => $size, 'offset' => $this->offset, 'time' => $dosTime, 'date' => $dosDate];
        $this->offset += strlen($header) + strlen($name);
    }

    /** Zentralverzeichnis schreiben und die ZIP-Datei damit abschließen. */
    public function finish(): void
    {
        $cdStart = $this->offset;
        foreach ($this->entries as $e) {
            $header = pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50, // Signatur „zentraler Datei-Header"
                20, 20,     // erstellt mit / benötigt Version 2.0
                0, 0,       // Flags, Methode (STORE)
                $e['time'], $e['date'],
                $e['crc'], $e['size'], $e['size'],
                strlen($e['name']), 0, 0, // Name-/Extra-/Kommentar-Länge
                0, 0,       // Datenträger-Nummer, interne Attribute
                0,          // externe Attribute
                $e['offset']
            );
            echo $header . $e['name'];
            $this->offset += strlen($header) + strlen($e['name']);
        }
        $cdSize = $this->offset - $cdStart;

        echo pack(
            'VvvvvVVv',
            0x06054b50, // Signatur „Ende des Zentralverzeichnisses"
            0, 0,
            count($this->entries), count($this->entries),
            $cdSize, $cdStart,
            0 // Kommentar-Länge
        );
    }

    /** @return array{0:int,1:int} [DOS-Zeit, DOS-Datum] */
    private static function dosDateTime(int $timestamp): array
    {
        $d = getdate($timestamp);
        $year = max(1980, (int) $d['year']);
        $dosTime = ($d['hours'] << 11) | ($d['minutes'] << 5) | intdiv((int) $d['seconds'], 2);
        $dosDate = (($year - 1980) << 9) | ($d['mon'] << 5) | $d['mday'];

        return [$dosTime, $dosDate];
    }
}

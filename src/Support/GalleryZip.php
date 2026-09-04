<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Gemeinsame Helfer für die Galerie-ZIPs (Einzel-Galerie und Gesamt-Sicherung).
 */
final class GalleryZip
{
    /** Dateisystemtauglicher Slug aus einem Galerie-Titel. */
    public static function slug(string $text): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', mb_strtolower(trim($text))) ?? '';

        return trim($slug, '-') ?: 'galerie';
    }

    /**
     * Eindeutiger, laufend nummerierter Eintragsname (`001_foto.jpg`).
     *
     * @param array<string,bool> $used
     */
    public static function entryName(int $n, string $originalName, array &$used): string
    {
        $base = $originalName !== ''
            ? (preg_replace('/[^A-Za-z0-9._-]+/', '_', $originalName) ?: 'datei')
            : 'datei';
        $entry = sprintf('%03d_%s', $n, $base);
        $i = $n;
        while (isset($used[$entry])) {
            $entry = sprintf('%03d_%s', ++$i, $base);
        }
        $used[$entry] = true;

        return $entry;
    }

    public static function noticeText(): string
    {
        return "Hinweis zur Nutzung\n===================\n\n" . gallery_usage_notice()
            . "\n\nHeruntergeladen am " . date('d.m.Y') . ".\n";
    }
}

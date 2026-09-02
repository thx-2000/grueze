<?php

// „Grün" – das Standard-Theme (Slug bleibt aus Kompatibilität „signalfarbe").
// Ruhiges Waldgrün als Aktionsfarbe auf viel Weiß, Lindgrün nur noch als
// kleiner Marken-Funke. Fraunces für Überschriften, Hanken Grotesk für alles
// andere (lokal eingebettet, siehe public/assets/css/fonts.css).
// Zugleich die Basis, auf die nicht gesetzte Tokens zurückfallen.
// Aufbau eines Themes: siehe themes/README.md

return [
    'name' => 'Grün',
    'description' => 'Waldgrün auf viel Weiß, ruhig und wertig. Der Standard.',
    'tokens' => [
        'font_display'         => '"Fraunces", "Iowan Old Style", Georgia, serif',
        'font_body'            => '"Hanken Grotesk", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
        'color_bg'             => '#f5f7f3',
        'color_bg_alt'         => '#eef1ea',
        'color_surface'        => '#ffffff',
        'color_surface_strong' => '#ffffff',
        'color_surface_soft'   => '#f0f3ee',
        'color_text'           => '#19231d',
        'color_muted'          => '#586159',
        'color_border'         => '#e0e5db',
        'color_primary'        => '#2e6b41',
        'color_primary_strong' => '#245334',
        'color_secondary'      => '#2e6b41',
        'color_accent'         => '#2e6b41',
        'color_highlight'      => '#e6efe7',
        'color_danger'         => '#a8532b',
        'color_success'        => '#2f6d43',
        'radius_sm'            => '0.5rem',
        'radius_md'            => '0.75rem',
        'radius_lg'            => '1rem',
        'radius_xl'            => '1.25rem',
    ],
];

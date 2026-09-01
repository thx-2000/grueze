<?php

// Dunkle, warmneutrale Oberfläche mit Bernstein-Akzent. Gedacht für Arbeit
// bei wenig Umgebungslicht. Kontraste sind auf WCAG-AA ausgelegt.
// Aufbau eines Themes: siehe themes/README.md

return [
    'name' => 'Dunkel',
    'description' => 'Warmneutrale Dunkelfläche mit Bernstein-Akzent – schont die Augen bei wenig Licht.',
    'tokens' => [
        'font_display'         => '-apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", sans-serif',
        'font_body'            => '-apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", sans-serif',
        'color_bg'             => '#15171b',
        'color_bg_alt'         => '#1d2026',
        'color_surface'        => '#23262d',
        'color_surface_strong' => '#2b2f37',
        'color_surface_soft'   => '#1b1e23',
        'color_text'           => '#e9e7e1',
        'color_muted'          => '#a6a298',
        'color_border'         => 'rgba(233, 231, 225, 0.15)',
        'color_primary'        => '#e0a35c',
        'color_primary_strong' => '#f1c690',
        'color_secondary'      => '#86b7d8',
        'color_accent'         => '#e0a35c',
        'color_highlight'      => '#38301f',
        'color_danger'         => '#e6765d',
        'color_success'        => '#68c49b',
        'radius_sm'            => '0.45rem',
        'radius_md'            => '0.7rem',
        'radius_lg'            => '0.95rem',
        'radius_xl'            => '1.2rem',
    ],
];

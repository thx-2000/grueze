<?php

// „Terrakotta" – warme Erdtöne (Sand, Ton, Espresso) auf hellem Grund. Die
// Aktionsfarbe ist ein gedecktes Ton-/Ziegelrot (nicht mehr orange), Ocker
// nur als leiser Akzent. Ruhig und wertig. Kontraste auf WCAG-AA ausgelegt
// (Fließtext >= 4.5:1, Buttons weiß auf Primär >= 6:1).
// Aufbau eines Themes: siehe themes/README.md

return [
    'name' => 'Terrakotta',
    'description' => 'Warme Erdtöne mit gedecktem Ziegelrot als Aktionsfarbe – zurückhaltend, mit Charakter.',
    'tokens' => [
        'font_display'         => '"Fraunces", "Iowan Old Style", Georgia, serif',
        'font_body'            => '"Hanken Grotesk", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
        'color_bg'             => '#f3ede6',
        'color_bg_alt'         => '#e8e0d5',
        'color_surface'        => '#fbf8f4',
        'color_surface_strong' => '#ffffff',
        'color_surface_soft'   => '#f1eae1',
        'color_text'           => '#29221d',
        'color_muted'          => '#6d6154',
        'color_border'         => '#e1d6c8',
        'color_primary'        => '#9c4a34',
        'color_primary_strong' => '#7c3826',
        'color_secondary'      => '#5f5346',
        'color_accent'         => '#cd8a52',
        'color_highlight'      => '#f2e7d8',
        'color_danger'         => '#8f2417',
        'color_success'        => '#4d6b3c',
        'radius_sm'            => '0.5rem',
        'radius_md'            => '0.75rem',
        'radius_lg'            => '1rem',
        'radius_xl'            => '1.25rem',
    ],
];

<?php

// „Terrakotta" – warme Erdtöne (Sand, Ton, Espresso) auf hellem Grund, dazu
// ein gebranntes Orange als einzige Signalfarbe für Aktionen. Ruhig und
// wertig, mit einem Funken. Kontraste auf WCAG-AA ausgelegt
// (Fließtext >= 4.5:1, Buttons weiß auf Primär >= 4.5:1).
// Aufbau eines Themes: siehe themes/README.md

return [
    'name' => 'Terrakotta',
    'description' => 'Warme Erdtöne mit gebranntem Orange als Signalfarbe – zurückhaltend, aber mit Charakter.',
    'tokens' => [
        'font_display'         => '"Fraunces", "Iowan Old Style", Georgia, serif',
        'font_body'            => '"Hanken Grotesk", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
        'color_bg'             => '#f3ede6',
        'color_bg_alt'         => '#e9e0d5',
        'color_surface'        => '#fbf8f4',
        'color_surface_strong' => '#ffffff',
        'color_surface_soft'   => '#f1eae1',
        'color_text'           => '#29221d',
        'color_muted'          => '#6d6154',
        'color_border'         => '#e2d7c9',
        'color_primary'        => '#b5541f',
        'color_primary_strong' => '#8f3f14',
        'color_secondary'      => '#5f5346',
        'color_accent'         => '#e0954a',
        'color_highlight'      => '#f6e6d3',
        'color_danger'         => '#a32b1c',
        'color_success'        => '#4d6b3c',
        'radius_sm'            => '0.5rem',
        'radius_md'            => '0.75rem',
        'radius_lg'            => '1rem',
        'radius_xl'            => '1.25rem',
    ],
];

<?php

// Standard-Theme für neue Installationen: viel Weiß, warmer Orange-Akzent,
// Teal als Zweitfarbe, kräftig und kontraststark. Kontraste sind auf
// WCAG-AA-Lesbarkeit ausgelegt (Fließtext >= 4.5:1).
// Aufbau eines Themes: siehe themes/README.md

return [
    'name' => 'Hell',
    'description' => 'Viel Weiß, warmer Orange-Akzent, Teal als Zweitfarbe.',
    'tokens' => [
        'font_display'         => '"Fraunces", "Iowan Old Style", Georgia, serif',
        'font_body'            => '"Hanken Grotesk", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
        'color_bg'             => '#faf6f2',
        'color_bg_alt'         => '#f2e9e0',
        'color_surface'        => '#ffffff',
        'color_surface_strong' => '#ffffff',
        'color_surface_soft'   => '#fbf5ef',
        'color_text'           => '#1c1917',
        'color_muted'          => '#6b6259',
        'color_border'         => 'rgba(28, 25, 23, 0.14)',
        'color_primary'        => '#c2410c',
        'color_primary_strong' => '#9a3412',
        'color_secondary'      => '#0d9488',
        'color_accent'         => '#fb923c',
        'color_highlight'      => '#ffe8d4',
        'color_danger'         => '#c0341f',
        'color_success'        => '#15803d',
        'radius_sm'            => '0.35rem',
        'radius_md'            => '0.55rem',
        'radius_lg'            => '0.8rem',
        'radius_xl'            => '1.1rem',
    ],
];

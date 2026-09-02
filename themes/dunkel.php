<?php

// Dunkle Fassung von „Grün": dieselbe Designsprache, tiefe grün-schwarze
// Flächen, hellgrüne Aktionsfarbe. Für wenig Umgebungslicht.
// Aufbau eines Themes: siehe themes/README.md

return [
    'name' => 'Dunkel',
    'description' => 'Dunkle Fassung von „Grün" – tiefe grün-schwarze Flächen, schont die Augen.',
    'tokens' => [
        'font_display'         => '"Fraunces", "Iowan Old Style", Georgia, serif',
        'font_body'            => '"Hanken Grotesk", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
        'color_bg'             => '#10140f',
        'color_bg_alt'         => '#1e241b',
        'color_surface'        => '#171c15',
        'color_surface_strong' => '#1e241b',
        'color_surface_soft'   => '#141813',
        'color_text'           => '#e9ede6',
        'color_muted'          => '#a2aa9f',
        'color_border'         => '#2f362d',
        'color_primary'        => '#6bbf7e',
        'color_primary_strong' => '#82cf93',
        'color_secondary'      => '#6bbf7e',
        'color_accent'         => '#39804d',
        'color_highlight'      => '#21301f',
        'color_danger'         => '#d68a5c',
        'color_success'        => '#6bbf7e',
        'radius_sm'            => '0.5rem',
        'radius_md'            => '0.75rem',
        'radius_lg'            => '1rem',
        'radius_xl'            => '1.25rem',
    ],
];

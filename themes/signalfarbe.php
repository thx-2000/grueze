<?php

// Gedämpftes Grün-Grau mit Lindgrün als Signalfarbe, cremeweiße, leicht
// durchscheinende Flächen. Zugleich die Basis, auf die nicht gesetzte Tokens
// zurückfallen. Aufbau eines Themes: siehe themes/README.md

return [
    'name' => 'Signalfarbe',
    'description' => 'Ruhiges Grün-Grau mit kräftigem Lindgrün als Signalfarbe.',
    'tokens' => [
        'font_display'         => '-apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", sans-serif',
        'font_body'            => '-apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", sans-serif',
        'color_bg'             => '#e8ebe5',
        'color_bg_alt'         => '#dde1da',
        'color_surface'        => 'rgba(255, 255, 255, 0.82)',
        'color_surface_strong' => 'rgba(255, 255, 255, 0.92)',
        'color_surface_soft'   => 'rgba(243, 245, 240, 0.92)',
        'color_text'           => '#181a15',
        'color_muted'          => '#5d6258',
        'color_border'         => 'rgba(38, 42, 32, 0.12)',
        'color_primary'        => '#2d3128',
        'color_primary_strong' => '#141610',
        'color_secondary'      => '#f0a317',
        'color_accent'         => '#d8ef54',
        'color_highlight'      => '#eef4c5',
        'color_danger'         => '#b64521',
        'color_success'        => '#3f7558',
        'radius_sm'            => '0.45rem',
        'radius_md'            => '0.7rem',
        'radius_lg'            => '0.95rem',
        'radius_xl'            => '1.2rem',
    ],
];

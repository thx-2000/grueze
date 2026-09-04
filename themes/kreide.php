<?php

// „Kreide" – fast weiße, kühle Flächen und schwarznaher Text; wichtige
// Elemente in kräftigen, aber matten (nicht leuchtenden) Farben: tiefes
// Petrol als Aktionsfarbe, warmes Ziegelrot als Zweitfarbe, mattes Amber
// für Hervorhebungen. Etwas schärfere Ecken für einen modernen Auftritt.
// Kontraste auf WCAG-AA ausgelegt (Fließtext >= 4.5:1, weiß auf Primär >= 4.5:1).
// Aufbau eines Themes: siehe themes/README.md

return [
    'name' => 'Kreide',
    'description' => 'Fast weiße Flächen, kräftige matte Farben für wichtige Elemente – klar und modern.',
    'tokens' => [
        'font_display'         => '"Fraunces", "Iowan Old Style", Georgia, serif',
        'font_body'            => '"Hanken Grotesk", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
        'color_bg'             => '#f6f7f8',
        'color_bg_alt'         => '#eef0f2',
        'color_surface'        => '#ffffff',
        'color_surface_strong' => '#ffffff',
        'color_surface_soft'   => '#f4f5f7',
        'color_text'           => '#161719',
        'color_muted'          => '#5f636b',
        'color_border'         => '#e4e7ea',
        'color_primary'        => '#0e5a68',
        'color_primary_strong' => '#0a454f',
        'color_secondary'      => '#8a3b2e',
        'color_accent'         => '#c98a2e',
        'color_highlight'      => '#e2eef0',
        'color_danger'         => '#b3261e',
        'color_success'        => '#1f6b4a',
        'radius_sm'            => '0.35rem',
        'radius_md'            => '0.5rem',
        'radius_lg'            => '0.8rem',
        'radius_xl'            => '1.05rem',
    ],
];

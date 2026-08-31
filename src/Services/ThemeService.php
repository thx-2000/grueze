<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SettingRepository;
use App\Repositories\ThemeRepository;
use Throwable;

/**
 * Themes bündeln das Aussehen (Fonts, Farben, Eckenradien) unter einem Namen.
 *
 * Zwei Quellen:
 *  - Datei-Themes im Ordner /themes (mitgeliefert, nicht editierbar, aber
 *    duplizierbar). Neue Dateien werden automatisch angeboten.
 *  - Eigene Themes in der DB-Tabelle themes (per Oberfläche erstellt).
 *
 * Das aktive Theme steht in app_settings unter "active_theme".
 */
final class ThemeService
{
    public const FALLBACK_SLUG = 'hell';

    /**
     * Solange in app_settings kein aktives Theme steht, bleibt es beim
     * bisherigen Look. Frische Installationen setzen "hell" beim Anlegen des
     * ersten Admin-Kontos (SetupController), Bestandsinstanzen bekommen
     * ihren Wert per Theme-Migration.
     */
    private const UNSET_DEFAULT_SLUG = 'signalfarbe';

    /**
     * Kanonische Token-Liste: key => [css-Variable, Label, Gruppe, Typ].
     * Typ: color | font | length.
     */
    private const TOKENS = [
        'font_display'        => ['--font-display', 'Überschriften-Schrift', 'Schrift', 'font'],
        'font_body'           => ['--font-body', 'Text-Schrift', 'Schrift', 'font'],
        'color_bg'            => ['--color-bg', 'Seitenhintergrund', 'Flächen', 'color'],
        'color_bg_alt'        => ['--color-bg-alt', 'Hintergrund (Variante)', 'Flächen', 'color'],
        'color_surface'       => ['--color-surface', 'Kartenfläche', 'Flächen', 'color'],
        'color_surface_strong' => ['--color-surface-strong', 'Kartenfläche kräftig', 'Flächen', 'color'],
        'color_surface_soft'  => ['--color-surface-soft', 'Kartenfläche weich', 'Flächen', 'color'],
        'color_text'          => ['--color-text', 'Text', 'Text & Rahmen', 'color'],
        'color_muted'         => ['--color-muted', 'Text gedämpft', 'Text & Rahmen', 'color'],
        'color_border'        => ['--color-border', 'Rahmen', 'Text & Rahmen', 'color'],
        'color_primary'       => ['--color-primary', 'Primärfarbe (Knöpfe)', 'Aktionen', 'color'],
        'color_primary_strong' => ['--color-primary-strong', 'Primärfarbe kräftig', 'Aktionen', 'color'],
        'color_secondary'     => ['--color-secondary', 'Sekundärfarbe', 'Aktionen', 'color'],
        'color_accent'        => ['--color-accent', 'Akzent (Kopfleiste)', 'Aktionen', 'color'],
        'color_highlight'     => ['--color-highlight', 'Hervorhebung', 'Aktionen', 'color'],
        'color_danger'        => ['--color-danger', 'Warnung / Löschen', 'Status', 'color'],
        'color_success'       => ['--color-success', 'Erfolg', 'Status', 'color'],
        'radius_sm'           => ['--radius-sm', 'Ecken klein', 'Ecken', 'length'],
        'radius_md'           => ['--radius-md', 'Ecken mittel', 'Ecken', 'length'],
        'radius_lg'           => ['--radius-lg', 'Ecken groß', 'Ecken', 'length'],
        'radius_xl'           => ['--radius-xl', 'Ecken sehr groß', 'Ecken', 'length'],
    ];

    /** Ultimativer Fallback, falls ein Theme einen Token nicht setzt (= Instanzwerte). */
    private const DEFAULTS = [
        'font_display'        => '-apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", sans-serif',
        'font_body'          => '-apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", sans-serif',
        'color_bg'            => '#e8ebe5',
        'color_bg_alt'        => '#dde1da',
        'color_surface'       => 'rgba(255, 255, 255, 0.82)',
        'color_surface_strong' => 'rgba(255, 255, 255, 0.92)',
        'color_surface_soft'  => 'rgba(243, 245, 240, 0.92)',
        'color_text'          => '#181a15',
        'color_muted'         => '#5d6258',
        'color_border'        => 'rgba(38, 42, 32, 0.12)',
        'color_primary'       => '#2d3128',
        'color_primary_strong' => '#141610',
        'color_secondary'     => '#f0a317',
        'color_accent'        => '#d8ef54',
        'color_highlight'     => '#eef4c5',
        'color_danger'        => '#b64521',
        'color_success'       => '#3f7558',
        'radius_sm'           => '0.45rem',
        'radius_md'           => '0.7rem',
        'radius_lg'           => '0.95rem',
        'radius_xl'           => '1.2rem',
    ];

    private string $themesDir;

    public function __construct(
        private SettingRepository $settings,
        private ThemeRepository $themes
    ) {
        $this->themesDir = dirname(__DIR__, 2) . '/themes';
    }

    /** @return array<string,array{key:string,css:string,label:string,group:string,type:string}> */
    public function tokenDefinitions(): array
    {
        $defs = [];
        foreach (self::TOKENS as $key => [$css, $label, $group, $type]) {
            $defs[$key] = ['key' => $key, 'css' => $css, 'label' => $label, 'group' => $group, 'type' => $type];
        }

        return $defs;
    }

    public function defaultTokens(): array
    {
        return self::DEFAULTS;
    }

    /** @return array<string,string> Token-Werte, gültige Keys, Fallback aufgefüllt. */
    public function normalizeTokens(array $tokens): array
    {
        $clean = [];
        foreach (array_keys(self::TOKENS) as $key) {
            $value = trim((string) ($tokens[$key] ?? ''));
            $clean[$key] = $value !== '' ? $value : self::DEFAULTS[$key];
        }

        return $clean;
    }

    /**
     * Alle Themes: Datei-Themes + eigene. slug => [name, description, tokens, source].
     * source: 'file' | 'db'
     */
    public function allThemes(): array
    {
        $result = [];

        foreach ($this->fileThemes() as $slug => $theme) {
            $result[$slug] = $theme + ['source' => 'file'];
        }

        foreach ($this->themes->all() as $row) {
            $slug = (string) $row['slug'];
            $result[$slug] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'description' => (string) $row['description'],
                'tokens' => $this->normalizeTokens((array) json_decode((string) $row['tokens'], true)),
                'based_on' => (string) $row['based_on'],
                'source' => 'db',
            ];
        }

        uasort($result, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $result;
    }

    public function theme(string $slug): ?array
    {
        return $this->allThemes()[$slug] ?? null;
    }

    public function activeSlug(): string
    {
        $all = $this->allThemes();
        $slug = (string) ($this->settings->get('active_theme') ?? '');

        if ($slug === '') {
            $slug = self::UNSET_DEFAULT_SLUG;
        }

        if (isset($all[$slug])) {
            return $slug;
        }

        return isset($all[self::FALLBACK_SLUG]) ? self::FALLBACK_SLUG : (string) array_key_first($all);
    }

    public function setActive(string $slug): void
    {
        if (isset($this->allThemes()[$slug])) {
            $this->settings->set('active_theme', $slug);
        }
    }

    public function activeTokens(): array
    {
        $theme = $this->theme($this->activeSlug());

        return $this->normalizeTokens($theme['tokens'] ?? []);
    }

    /** @return array<string,string> CSS-Variable => Wert, für den Inline-Style im <head>. */
    public function cssVariables(): array
    {
        $tokens = $this->activeTokens();
        $vars = [];
        foreach (self::TOKENS as $key => [$css]) {
            $vars[$css] = $tokens[$key];
        }

        // Textfarbe auf farbigen Flächen automatisch nach Kontrast wählen.
        $vars['--color-on-primary'] = readable_ink($tokens['color_primary']);
        $vars['--color-on-danger'] = readable_ink($tokens['color_danger']);
        $vars['--color-on-accent'] = readable_ink($tokens['color_accent']);

        return $vars;
    }

    /** @return array<string,array{name:string,description:string,tokens:array}> */
    private function fileThemes(): array
    {
        $themes = [];
        foreach (glob($this->themesDir . '/*.php') ?: [] as $path) {
            $slug = basename($path, '.php');
            try {
                $data = require $path;
            } catch (Throwable) {
                continue;
            }
            if (!is_array($data) || !isset($data['tokens']) || !is_array($data['tokens'])) {
                continue;
            }
            $themes[$slug] = [
                'name' => (string) ($data['name'] ?? ucfirst($slug)),
                'description' => (string) ($data['description'] ?? ''),
                'tokens' => $this->normalizeTokens($data['tokens']),
            ];
        }

        return $themes;
    }
}

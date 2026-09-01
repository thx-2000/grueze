<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\ThemeRepository;
use App\Services\ThemeService;
use App\Support\Redirect;

final class ThemeController extends BaseController
{
    private const BASE = '/settings/themes';

    public function __construct(
        Auth $auth,
        private ThemeService $themes,
        private ThemeRepository $themeRepo
    ) {
        parent::__construct($auth);
    }

    public function index(): void
    {
        $this->requirePermission('users.manage');

        $this->render('settings/themes', [
            'themes' => $this->themes->allThemes(),
            'activeSlug' => $this->themes->activeSlug(),
            'tokenDefs' => $this->themes->tokenDefinitions(),
        ]);
    }

    public function activate(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));

        $slug = trim((string) $request->input('slug'));
        if ($this->themes->theme($slug) === null) {
            flash('error', 'Theme nicht gefunden.');
            Redirect::to(self::BASE);
        }

        $this->themes->setActive($slug);
        flash('success', 'Theme aktiviert.');
        Redirect::to(self::BASE);
    }

    public function duplicate(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));

        $sourceSlug = trim((string) $request->input('slug'));
        $source = $this->themes->theme($sourceSlug);
        if ($source === null) {
            flash('error', 'Vorlage nicht gefunden.');
            Redirect::to(self::BASE);
        }

        $name = trim((string) $request->input('name')) ?: ($source['name'] . ' Kopie');
        $slug = $this->uniqueSlug($name);

        $this->themeRepo->create(
            $slug,
            $name,
            'Kopie von ' . $source['name'],
            $this->themes->normalizeTokens($source['tokens']),
            $sourceSlug
        );

        flash('success', 'Theme „' . $name . '" angelegt. Jetzt anpassen.');
        Redirect::to(self::BASE . '/bearbeiten?slug=' . rawurlencode($slug));
    }

    public function rename(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));

        $theme = $this->ownTheme((string) $request->input('slug'));
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            flash('error', 'Bitte einen Namen angeben.');
            Redirect::to(self::BASE);
        }

        $this->themeRepo->rename((int) $theme['id'], $name);
        flash('success', 'Theme umbenannt.');
        Redirect::to(self::BASE);
    }

    public function edit(Request $request): void
    {
        $this->requirePermission('users.manage');

        $theme = $this->ownTheme((string) $request->input('slug'));

        $this->render('settings/theme-edit', [
            'theme' => $theme,
            'tokenDefs' => $this->themes->tokenDefinitions(),
            'defaults' => $this->themes->defaultTokens(),
        ]);
    }

    public function save(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));

        $theme = $this->ownTheme((string) $request->input('slug'));

        // Leeres Feld = diesen Token unverändert lassen (nicht auf den globalen
        // Standard zurücksetzen). Der Platzhalter zeigt den Standard nur an.
        $tokens = $this->themes->normalizeTokens($theme['tokens'] ?? []);
        foreach (array_keys($this->themes->tokenDefinitions()) as $key) {
            $value = trim((string) $request->input('token_' . $key, ''));
            if ($value !== '') {
                $tokens[$key] = $value;
            }
        }

        $this->themeRepo->updateTokens((int) $theme['id'], $this->themes->normalizeTokens($tokens));
        flash('success', 'Theme gespeichert.');
        Redirect::to(self::BASE . '/bearbeiten?slug=' . rawurlencode($theme['slug'] ?? ''));
    }

    public function delete(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));

        $slug = (string) $request->input('slug');
        $theme = $this->ownTheme($slug);

        if ($this->themes->activeSlug() === $slug) {
            $this->themes->setActive(ThemeService::FALLBACK_SLUG);
        }

        $this->themeRepo->delete((int) $theme['id']);
        flash('success', 'Theme gelöscht.');
        Redirect::to(self::BASE);
    }

    /** Eigenes (DB-)Theme laden oder mit Fehler abbrechen. */
    private function ownTheme(string $slug): array
    {
        $theme = $this->themes->theme($slug);
        if ($theme === null || ($theme['source'] ?? '') !== 'db') {
            flash('error', 'Datei-Themes können nicht bearbeitet werden. Bitte zuerst duplizieren.');
            Redirect::to(self::BASE);
        }

        return ['slug' => $slug] + $theme;
    }

    private function uniqueSlug(string $name): string
    {
        $base = preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $name) ?: $name));
        $base = trim((string) $base, '-') ?: 'theme';
        $slug = $base;
        $i = 2;
        while ($this->themes->theme($slug) !== null) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\CategoryRepository;
use App\Repositories\TagRepository;
use App\Support\Redirect;

/**
 * Verwaltungsseite für Kategorien und Tags: anlegen, umbenennen, löschen.
 * Das Zuweisen passiert weiterhin im Kontaktformular bzw. der Sammelbearbeitung.
 */
final class TaxonomyController extends BaseController
{
    private const RETURN_PATH = '/verwaltung/kategorien-tags';

    public function __construct(
        Auth $auth,
        private CategoryRepository $categories,
        private TagRepository $tags
    ) {
        parent::__construct($auth);
    }

    public function index(): void
    {
        $this->requirePermission('categories.manage');

        $this->render('settings/taxonomy', [
            'categories' => $this->categories->allWithCounts(),
            'tags' => $this->tags->allWithCounts(),
        ]);
    }

    public function saveCategory(Request $request): void
    {
        $this->requirePermission('categories.manage');
        Csrf::validate($request->input('_csrf'));

        $id = (int) $request->input('id');
        $name = trim((string) $request->input('name'));

        if ($name === '') {
            flash('error', 'Bitte einen Namen angeben.');
            Redirect::to(self::RETURN_PATH);
        }
        if ($this->categories->nameExists($name, $id > 0 ? $id : null)) {
            flash('error', 'Eine Kategorie mit diesem Namen gibt es schon.');
            Redirect::to(self::RETURN_PATH);
        }

        if ($id > 0) {
            $this->categories->rename($id, $name);
            flash('success', 'Kategorie umbenannt.');
        } else {
            $this->categories->create($name);
            flash('success', 'Kategorie angelegt.');
        }

        Redirect::to(self::RETURN_PATH);
    }

    public function deleteCategory(Request $request): void
    {
        $this->requirePermission('categories.manage');
        Csrf::validate($request->input('_csrf'));

        $id = (int) $request->input('id');
        if ($id > 0) {
            $this->categories->delete($id);
            flash('success', 'Kategorie gelöscht. Betroffene Kontakte haben jetzt keine Kategorie mehr.');
        }

        Redirect::to(self::RETURN_PATH);
    }

    public function saveTag(Request $request): void
    {
        $this->requirePermission('categories.manage');
        Csrf::validate($request->input('_csrf'));

        $id = (int) $request->input('id');
        $name = trim((string) $request->input('name'));

        if ($name === '') {
            flash('error', 'Bitte einen Namen angeben.');
            Redirect::to(self::RETURN_PATH);
        }
        if ($this->tags->nameExists($name, $id > 0 ? $id : null)) {
            flash('error', 'Einen Tag mit diesem Namen gibt es schon.');
            Redirect::to(self::RETURN_PATH);
        }

        if ($id > 0) {
            $this->tags->rename($id, $name);
            flash('success', 'Tag umbenannt.');
        } else {
            $this->tags->create($name);
            flash('success', 'Tag angelegt.');
        }

        Redirect::to(self::RETURN_PATH);
    }

    public function deleteTag(Request $request): void
    {
        $this->requirePermission('categories.manage');
        Csrf::validate($request->input('_csrf'));

        $id = (int) $request->input('id');
        if ($id > 0) {
            $this->tags->delete($id);
            flash('success', 'Tag gelöscht. Die Kontakte bleiben unverändert.');
        }

        Redirect::to(self::RETURN_PATH);
    }
}

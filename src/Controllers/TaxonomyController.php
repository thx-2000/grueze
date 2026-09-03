<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\CategoryRepository;
use App\Repositories\GroupRepository;
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
        private TagRepository $tags,
        private GroupRepository $groups
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

    /**
     * Aus einem Tag eine Gruppe machen: legt eine Gruppe mit dem Tag-Namen an
     * und nimmt alle Kontakte mit diesem Tag auf. Optional wird der Tag danach
     * gelöscht. Braucht zusätzlich `groups.manage`.
     */
    public function tagToGroup(Request $request): void
    {
        $this->requirePermission('categories.manage');
        if (!$this->auth->can('groups.manage')) {
            flash('error', 'Zum Anlegen von Gruppen fehlt dir die Berechtigung.');
            Redirect::to(self::RETURN_PATH);
        }
        Csrf::validate($request->input('_csrf'));

        $tag = $this->tags->find((int) $request->input('id'));
        if ($tag === null) {
            Redirect::to(self::RETURN_PATH);
        }

        $name = (string) $tag['name'];
        if ($this->groups->nameExists($name)) {
            flash('error', 'Eine Gruppe „' . $name . '" gibt es schon. Bitte den Tag erst umbenennen.');
            Redirect::to(self::RETURN_PATH);
        }

        $contactIds = $this->tags->contactIdsForTag((int) $tag['id']);
        $groupId = $this->groups->create(
            ['name' => $name, 'description' => 'Aus dem Tag „' . $name . '" erstellt.', 'is_open' => false],
            (int) ($this->auth->user()['id'] ?? 0) ?: null
        );
        $this->groups->syncMembers($groupId, $contactIds);

        $deleted = false;
        if ($request->input('delete_tag') === '1') {
            $this->tags->delete((int) $tag['id']);
            $deleted = true;
        }

        flash('success', sprintf(
            'Gruppe „%s" mit %d %s angelegt%s.',
            $name,
            count($contactIds),
            count($contactIds) === 1 ? 'Mitglied' : 'Mitgliedern',
            $deleted ? ' – Tag gelöscht' : ''
        ));
        Redirect::to('/verwaltung/gruppen/detail?id=' . $groupId);
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\RoleRepository;
use App\Repositories\SettingRepository;
use App\Support\Redirect;

/**
 * Rollen verwalten: anlegen, umbenennen (Anzeigename + Beschreibung), löschen.
 * Der interne Schlüssel bleibt nach dem Anlegen fix. „admin" ist geschützt.
 */
final class RoleController extends BaseController
{
    private const RETURN_PATH = '/settings/roles';

    public function __construct(
        Auth $auth,
        private RoleRepository $roles,
        private SettingRepository $settings,
    ) {
        parent::__construct($auth);
    }

    public function index(): void
    {
        $this->requirePermission('users.manage');

        $roles = array_map(function (array $role): array {
            $role['user_count'] = $this->roles->userCount((int) $role['id']);
            $role['protected'] = $role['name'] === RoleRepository::PROTECTED_NAME;

            return $role;
        }, $this->roles->all());

        $this->render('settings/roles', ['roles' => $roles]);
    }

    public function store(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));

        $label = trim((string) $request->input('label'));
        $description = trim((string) $request->input('description'));

        if ($label === '') {
            flash('error', 'Bitte einen Anzeigenamen angeben.');
            Redirect::to(self::RETURN_PATH);
        }
        if ($this->roles->labelExists($label)) {
            flash('error', 'Eine Rolle mit diesem Namen gibt es schon.');
            Redirect::to(self::RETURN_PATH);
        }

        $this->roles->create($label, $description);
        flash('success', 'Rolle „' . $label . '" angelegt. Rechte und Sichtbarkeit lassen sich jetzt für sie festlegen.');
        Redirect::to(self::RETURN_PATH);
    }

    public function update(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));

        $id = (int) $request->input('id');
        $role = $id > 0 ? $this->roles->find($id) : null;
        if ($role === null) {
            flash('error', 'Rolle nicht gefunden.');
            Redirect::to(self::RETURN_PATH);
        }

        $label = trim((string) $request->input('label'));
        $description = trim((string) $request->input('description'));

        if ($label === '') {
            flash('error', 'Bitte einen Anzeigenamen angeben.');
            Redirect::to(self::RETURN_PATH);
        }
        if ($this->roles->labelExists($label, $id)) {
            flash('error', 'Eine Rolle mit diesem Namen gibt es schon.');
            Redirect::to(self::RETURN_PATH);
        }

        $this->roles->updateMeta($id, $label, $description);
        flash('success', 'Rolle aktualisiert.');
        Redirect::to(self::RETURN_PATH);
    }

    /**
     * Den internen Schlüssel einer Rolle ändern. „admin" bleibt fix. Rechte-,
     * Sichtbarkeits- und Registrierungs-Einstellungen ziehen automatisch mit.
     */
    public function renameSlug(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));

        $id = (int) $request->input('id');
        $role = $id > 0 ? $this->roles->find($id) : null;
        if ($role === null) {
            flash('error', 'Rolle nicht gefunden.');
            Redirect::to(self::RETURN_PATH);
        }
        if ($role['name'] === RoleRepository::PROTECTED_NAME) {
            flash('error', 'Der Schlüssel der Admin-Rolle ist fest.');
            Redirect::to(self::RETURN_PATH);
        }

        $desired = trim((string) $request->input('slug'));
        $result = $this->roles->renameSlug($id, $desired);
        if ($result === null) {
            flash('error', 'Ungültiger Schlüssel. Erlaubt sind Kleinbuchstaben, Ziffern und Bindestriche.');
            Redirect::to(self::RETURN_PATH);
        }

        if ($result['old'] !== $result['new']) {
            $this->settings->renameRoleEverywhere($result['old'], $result['new']);
        }
        flash('success', 'Schlüssel geändert: ' . $result['old'] . ' → ' . $result['new'] . '.');
        Redirect::to(self::RETURN_PATH);
    }

    public function delete(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));

        $id = (int) $request->input('id');
        $role = $id > 0 ? $this->roles->find($id) : null;
        if ($role === null) {
            flash('error', 'Rolle nicht gefunden.');
            Redirect::to(self::RETURN_PATH);
        }

        if ($role['name'] === RoleRepository::PROTECTED_NAME) {
            flash('error', 'Die Admin-Rolle kann nicht gelöscht werden.');
            Redirect::to(self::RETURN_PATH);
        }
        if ($this->roles->userCount($id) > 0) {
            flash('error', 'Dieser Rolle sind noch Benutzer zugeordnet. Weise sie zuerst einer anderen Rolle zu.');
            Redirect::to(self::RETURN_PATH);
        }

        $this->roles->delete($id);
        $this->settings->pruneRole((string) $role['name']);
        flash('success', 'Rolle „' . ($role['label'] ?: $role['name']) . '" gelöscht.');
        Redirect::to(self::RETURN_PATH);
    }
}

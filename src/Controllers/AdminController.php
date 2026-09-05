<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Services\MigrationService;
use App\Services\ReleaseCheckService;
use App\Services\UpdateService;
use App\Support\Redirect;

final class AdminController extends BaseController
{
    public function __construct(
        \App\Core\Auth $auth,
        private MigrationService $migrations,
        private UpdateService $updates,
        private ReleaseCheckService $releases,
    ) {
        parent::__construct($auth);
    }

    public function hub(): void
    {
        $this->requireAuth();
        if (!(can('users.manage') || can('settings.manage') || can('audit.view') || can('mail.view_log'))) {
            throw new \RuntimeException('Für diesen Bereich fehlt die Berechtigung.');
        }

        if (can('users.manage')) {
            $this->releases->refresh();
        }

        $this->render('admin/hub', []);
    }

    public function update(): void
    {
        $this->requirePermission('users.manage');
        $this->updates->syncVersionIfClean();
        $this->releases->refresh();

        $this->render('admin/update', [
            'installedVersion' => $this->updates->installedVersion(),
            'codeVersion' => $this->updates->codeVersion(),
            'lastUpdatedAt' => $this->updates->lastUpdatedAt(),
            'updatePending' => $this->updates->updatePending(),
            'pendingMigrations' => $this->updates->pendingMigrations(),
            'applied' => $this->migrations->applied(),
            'locked' => $this->updates->locked(),
            'changelog' => $this->updates->changelogExcerpt(),
            'release' => $this->releases->status(),
        ]);
    }

    public function runUpdate(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));

        $withBackup = (string) $request->input('with_backup', '1') === '1';
        $result = $this->updates->run($withBackup);

        if ($result['ok']) {
            $applied = count($result['applied']);
            $message = $applied === 0
                ? 'Es waren keine Migrationen offen – die Instanz ist auf dem aktuellen Stand.'
                : sprintf('Update abgeschlossen: %d Migration(en) angewendet.', $applied);
            if ($result['backup'] !== null) {
                $message .= ' Sicherung abgelegt unter storage/backups/' . $result['backup'] . '.';
            }
            flash('success', $message);
        } else {
            flash('error', 'Update nicht vollständig: ' . ($result['error'] ?? 'unbekannter Fehler')
                . ($result['failed'] !== null ? ' (Migration ' . $result['failed'] . ')' : ''));
        }

        Redirect::to('/admin/aktualisieren');
    }

    public function migrations(): void
    {
        Redirect::to('/admin/aktualisieren');
    }

    public function applyMigration(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));

        $name = trim((string) $request->input('migration'));
        if ($name === '') {
            flash('error', 'Kein Migrations-Name angegeben.');
            Redirect::to('/admin/aktualisieren');
        }

        $result = $this->migrations->applyOne($name);

        if ($result === 'OK') {
            flash('success', 'Migration erfolgreich angewendet: ' . $name);
        } else {
            flash('error', $result);
        }

        Redirect::to('/admin/aktualisieren');
    }
}

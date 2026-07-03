<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Services\MigrationService;
use App\Support\Redirect;

final class AdminController extends BaseController
{
    public function __construct(
        \App\Core\Auth $auth,
        private MigrationService $migrations
    ) {
        parent::__construct($auth);
    }

    public function migrations(): void
    {
        $this->requirePermission('users.manage');

        $this->render('admin/migrations', [
            'applied' => $this->migrations->applied(),
            'pending' => $this->migrations->pending(),
        ]);
    }

    public function applyMigration(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));

        $name = trim((string) $request->input('migration'));
        if ($name === '') {
            flash('error', 'Kein Migrations-Name angegeben.');
            Redirect::to('/admin/migrations');
        }

        $result = $this->migrations->applyOne($name);

        if ($result === 'OK') {
            flash('success', 'Migration erfolgreich angewendet: ' . $name);
        } else {
            flash('error', $result);
        }

        Redirect::to('/admin/migrations');
    }
}

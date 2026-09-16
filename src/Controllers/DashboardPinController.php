<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\DashboardPinRepository;
use App\Support\JsonResponse;
use App\Support\Redirect;

/**
 * Persönliche Kacheln auf der Startseite: anpinnen (aus dem Einstellungen-
 * Hub oder generisch von jeder Seite aus), lösen, per Ziehen umsortieren.
 * Braucht `dashboard.pins` – Standard nur Admin, über die normale
 * Berechtigungsverwaltung später auch für andere Rollen freischaltbar.
 */
final class DashboardPinController extends BaseController
{
    public function __construct(
        Auth $auth,
        private DashboardPinRepository $pins,
    ) {
        parent::__construct($auth);
    }

    public function pin(Request $request): void
    {
        $this->requirePermission('dashboard.pins');
        Csrf::validate($request->input('_csrf'));

        $path = trim((string) $request->input('path'));
        $label = trim((string) $request->input('label'));
        if ($path !== '' && $label !== '') {
            $this->pins->pin(
                (int) $this->auth->user()['id'],
                $path,
                $label,
                trim((string) $request->input('icon')) ?: null,
                trim((string) $request->input('description')) ?: null
            );
        }

        Redirect::to((string) $request->input('back', '/'));
    }

    public function unpin(Request $request): void
    {
        $this->requirePermission('dashboard.pins');
        Csrf::validate($request->input('_csrf'));

        $this->pins->unpin((int) $request->input('id'), (int) $this->auth->user()['id']);

        Redirect::to((string) $request->input('back', '/'));
    }

    /** Per Ziehen umsortiert – Antwort als JSON (per fetch() von der Startseite aus). */
    public function reorder(Request $request): void
    {
        ob_start();
        $this->requirePermission('dashboard.pins');
        Csrf::validate($request->input('_csrf'));

        $ids = array_values(array_filter(array_map('intval', (array) $request->input('order', []))));
        $this->pins->reorder((int) $this->auth->user()['id'], $ids);

        JsonResponse::send(['ok' => true]);
    }
}

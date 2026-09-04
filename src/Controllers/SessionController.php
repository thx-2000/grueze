<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\UserSessionRepository;
use App\Support\Redirect;

/**
 * Anmelde-Übersicht für die Verwaltung: wer ist gerade online, wer hat sich
 * wann angemeldet – und eine einzelne Sitzung aus der Ferne beenden.
 */
final class SessionController extends BaseController
{
    /** Fenster (Sekunden), innerhalb dessen eine Sitzung als „online" gilt. */
    private const ONLINE_WINDOW = 1800;

    public function __construct(\App\Core\Auth $auth, private UserSessionRepository $sessions)
    {
        parent::__construct($auth);
    }

    public function index(): void
    {
        $this->requirePermission('users.manage');

        $window = (int) config('app.session_timeout', self::ONLINE_WINDOW);
        $currentHash = hash('sha256', session_id());

        $this->render('admin/sessions', [
            'active' => $this->sessions->active($window),
            'history' => $this->sessions->history(120),
            'currentHash' => $currentHash,
            'windowMinutes' => (int) round($window / 60),
            'showIp' => (bool) config('security.store_ip', false),
        ]);
    }

    public function revoke(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));

        $this->sessions->revoke((int) $request->input('id'));
        flash('success', 'Die Sitzung wurde beendet. Beim nächsten Aufruf wird sie abgemeldet.');
        Redirect::to('/verwaltung/anmeldungen');
    }
}

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

    /**
     * Wählbare Verlaufs-Zeiträume: entweder eine feste Anzahl Sitzungen
     * (`limit`) oder ein Zeitfenster in Tagen (`days`). `short` ist das
     * Kürzel im Umschalter, `label` der erklärende Satz überm Verlauf.
     */
    private const RANGES = [
        '20' => ['limit' => 20, 'short' => '20', 'label' => 'Die letzten 20 Sitzungen.'],
        '100' => ['limit' => 100, 'short' => '100', 'label' => 'Die letzten 100 Sitzungen.'],
        '200' => ['limit' => 200, 'short' => '200', 'label' => 'Die letzten 200 Sitzungen.'],
        '500' => ['limit' => 500, 'short' => '500', 'label' => 'Die letzten 500 Sitzungen.'],
        '7d' => ['days' => 7, 'short' => '7 Tage', 'label' => 'Sitzungen der letzten 7 Tage.'],
        '14d' => ['days' => 14, 'short' => '14 Tage', 'label' => 'Sitzungen der letzten 14 Tage.'],
        '30d' => ['days' => 30, 'short' => '1 Monat', 'label' => 'Sitzungen des letzten Monats.'],
        '90d' => ['days' => 90, 'short' => '3 Monate', 'label' => 'Sitzungen der letzten 3 Monate.'],
    ];
    private const DEFAULT_RANGE = '20';

    public function __construct(\App\Core\Auth $auth, private UserSessionRepository $sessions)
    {
        parent::__construct($auth);
    }

    public function index(Request $request): void
    {
        $this->requirePermission('users.manage');

        $window = (int) config('app.session_timeout', self::ONLINE_WINDOW);
        $currentHash = hash('sha256', session_id());

        $rangeKey = (string) $request->input('range', self::DEFAULT_RANGE);
        if (!array_key_exists($rangeKey, self::RANGES)) {
            $rangeKey = self::DEFAULT_RANGE;
        }
        $range = self::RANGES[$rangeKey];
        $history = isset($range['days'])
            ? $this->sessions->historySince($range['days'])
            : $this->sessions->history($range['limit']);

        $this->render('admin/sessions', [
            'active' => $this->sessions->active($window),
            'history' => $history,
            'ranges' => self::RANGES,
            'rangeKey' => $rangeKey,
            'rangeLabel' => $range['label'],
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

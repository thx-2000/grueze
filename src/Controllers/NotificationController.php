<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\ContactRepository;
use App\Repositories\GroupRepository;
use App\Repositories\NotificationRepository;
use App\Support\Redirect;

/**
 * „Meine Benachrichtigungen" – Admin-Abos für Login-/Änderungs-Mails, je
 * Ziel (alle / eine Person / eine Gruppe) mit eigenem Zeitrahmen. Fest an
 * die Rolle „admin" gebunden (nicht über die Rechte-Matrix vergebbar), da es
 * um Einsicht in Logins/Änderungen der gesamten Instanz geht.
 */
final class NotificationController extends BaseController
{
    private const FREQUENCIES = ['sofort', 'alle_5_min', 'alle_10_min', 'stuendlich', 'alle_6_stunden', 'taeglich', 'woechentlich', 'monatlich'];

    public function __construct(
        Auth $auth,
        private NotificationRepository $notifications,
        private ContactRepository $contacts,
        private GroupRepository $groups,
    ) {
        parent::__construct($auth);
    }

    public function index(): void
    {
        $this->requireAdmin();

        $this->render('notifications/index', [
            'subscriptions' => $this->notifications->forUser((int) $this->auth->user()['id']),
            'frequencies' => NotificationRepository::frequencyLabels(),
            'contacts' => $this->contacts->search([]),
            'groups' => $this->groups->all(),
        ]);
    }

    /** `target` bündelt Ziel-Art und -ID in einem Auswahlfeld: `all`, `contact:<id>`, `group:<id>`. */
    public function subscribe(Request $request): void
    {
        $this->requireAdmin();
        Csrf::validate($request->input('_csrf'));

        $eventType = (string) $request->input('event_type') === 'login' ? 'login' : 'change';
        $frequency = $this->normalizeFrequency((string) $request->input('frequency'));
        $target = (string) $request->input('target', 'all');

        [$scope, $targetId] = match (true) {
            $target === 'all' => ['all', null],
            str_starts_with($target, 'contact:') => ['contact', (int) substr($target, 8) ?: null],
            str_starts_with($target, 'group:') => ['group', (int) substr($target, 6) ?: null],
            default => [null, null],
        };

        if ($scope !== null && ($scope === 'all' || $targetId !== null)) {
            $this->notifications->subscribe((int) $this->auth->user()['id'], $eventType, $scope, $targetId, $frequency);
            flash('success', 'Benachrichtigung eingerichtet.');
        } else {
            flash('error', 'Bitte ein gültiges Ziel auswählen.');
        }

        Redirect::to('/verwaltung/benachrichtigungen');
    }

    public function updateFrequency(Request $request): void
    {
        $this->requireAdmin();
        Csrf::validate($request->input('_csrf'));

        $this->notifications->updateFrequency(
            (int) $request->input('id'),
            (int) $this->auth->user()['id'],
            $this->normalizeFrequency((string) $request->input('frequency'))
        );
        flash('success', 'Zeitrahmen angepasst.');

        Redirect::to('/verwaltung/benachrichtigungen');
    }

    public function unsubscribe(Request $request): void
    {
        $this->requireAdmin();
        Csrf::validate($request->input('_csrf'));

        $this->notifications->unsubscribe((int) $request->input('id'), (int) $this->auth->user()['id']);
        flash('success', 'Benachrichtigung entfernt.');

        Redirect::to('/verwaltung/benachrichtigungen');
    }

    private function normalizeFrequency(string $value): string
    {
        return in_array($value, self::FREQUENCIES, true) ? $value : 'sofort';
    }
}

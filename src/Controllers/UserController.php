<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\LogRepository;
use App\Repositories\PasskeyRepository;
use App\Repositories\UserRepository;
use App\Services\PasswordResetService;
use App\Services\Validator;
use App\Support\Redirect;

final class UserController extends BaseController
{
    public function __construct(
        \App\Core\Auth $auth,
        private UserRepository $users,
        private LogRepository $logs,
        private PasswordResetService $passwordResets,
        private PasskeyRepository $passkeys,
        private \App\Repositories\EventRepository $events,
        private \App\Repositories\ContactRepository $contacts,
        private \App\Repositories\UserSessionRepository $sessions
    ) {
        parent::__construct($auth);
    }

    public function index(): void
    {
        $this->requirePermission('users.manage');
        $users = $this->users->all();

        $this->render('users/index', [
            'users' => $users,
            'roles' => $this->users->roles(),
            'passkeysAvailable' => $this->passkeys->isAvailable(),
            'passkeyCounts' => $this->passkeys->countByUserIds(array_map(
                static fn (array $user): int => (int) $user['id'],
                $users
            )),
            'canImpersonateUsers' => $this->auth->canAsOriginal('users.manage'),
            'originalUserId' => (int) (($this->auth->originalUser()['id'] ?? 0)),
            'currentUserId' => (int) (($this->auth->user()['id'] ?? 0)),
        ]);
    }

    public function account(): void
    {
        $this->requireAuth();
        $user = $this->auth->user();

        if (!$user) {
            Redirect::to('/login');
        }

        $contactId = (int) ($user['contact_id'] ?? 0);
        $ownContact = $contactId > 0 ? $this->contacts->find($contactId) : null;
        $canEditOwn = $ownContact !== null
            && ($this->auth->can('contacts.manage') || $this->auth->canViewContactField('address', $ownContact));

        $this->render('account/index', [
            'accountUser' => $user,
            'ownContact' => $ownContact,
            'canEditOwn' => $canEditOwn,
            'phoneLabels' => config('defaults.phone_labels', []),
            'passkeysAvailable' => $this->passkeys->isAvailable(),
            'passkeys' => $this->passkeys->byUserId((int) $user['id']),
            'openEvents' => $contactId > 0 ? $this->events->openEventsForContact($contactId) : [],
        ]);
    }

    public function store(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));
        $data = [
            'name' => trim((string) $request->input('name')),
            'email' => trim((string) $request->input('email')),
            'role_id' => (int) $request->input('role_id'),
        ];

        $errors = Validator::validate($data, [
            'name' => ['required'],
            'email' => ['required', 'email'],
        ]);

        if ($errors !== []) {
            $_SESSION['_errors'] = $errors;
            Redirect::to('/users');
        }

        $password = $this->generatePassword();
        $this->users->create([
            ...$data,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'is_active' => 1,
        ]);

        flash('success', 'Zugang angelegt. Erstpasswort: ' . $password);
        Redirect::to('/users');
    }

    public function impersonate(Request $request): void
    {
        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));

        if (!$this->auth->canAsOriginal('users.manage')) {
            flash('error', 'Dafür fehlen die nötigen Rechte.');
            Redirect::to('/users');
        }

        $targetUserId = (int) $request->input('user_id');
        $targetUser = $this->users->findById($targetUserId);
        $originalUser = $this->auth->originalUser();
        if (!$targetUser || !$originalUser || !$this->auth->startImpersonation($targetUserId)) {
            flash('error', 'Die Anmeldung als andere Person konnte nicht gestartet werden.');
            Redirect::to('/users');
        }

        $this->logs->addAudit(
            (int) $originalUser['id'],
            null,
            'impersonation_started',
            sprintf('Admin %s hat die Sitzung als %s gestartet.', $originalUser['name'], $targetUser['name'])
        );
        flash('success', 'Du bist jetzt als ' . $targetUser['name'] . ' angemeldet.');
        Redirect::to('/');
    }

    public function stopImpersonation(Request $request): void
    {
        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));

        if (!$this->auth->isImpersonating() || !$this->auth->canAsOriginal('users.manage')) {
            Redirect::to('/');
        }

        $impersonatedUser = $this->auth->user();
        $originalUser = $this->auth->originalUser();
        $this->auth->stopImpersonation();

        if ($originalUser) {
            $this->logs->addAudit(
                (int) $originalUser['id'],
                null,
                'impersonation_stopped',
                sprintf(
                    'Admin %s hat die Sitzung als %s beendet.',
                    $originalUser['name'],
                    $impersonatedUser['name'] ?? 'unbekannt'
                )
            );
            flash('success', 'Du bist wieder als ' . $originalUser['name'] . ' angemeldet.');
        }

        Redirect::to('/users');
    }

    public function setPassword(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));

        $targetUser = $this->users->findById((int) $request->input('user_id'));
        if (!$targetUser) {
            flash('error', 'Zugang nicht gefunden.');
            Redirect::to('/users');
        }

        $password = trim((string) $request->input('new_password'));
        if (mb_strlen($password) < 12) {
            flash('error', 'Das neue Passwort muss mindestens 12 Zeichen lang sein.');
            Redirect::to('/users#user-' . $targetUser['id']);
        }

        $this->users->updatePasswordHash((int) $targetUser['id'], password_hash($password, PASSWORD_DEFAULT));
        // Alle laufenden Sitzungen des Kontos beenden – das neue Passwort soll
        // sofort greifen, auch wenn woanders noch jemand angemeldet ist.
        $this->sessions->revokeAllForUser((int) $targetUser['id']);
        $this->logs->addAudit(
            (int) ($this->auth->originalUser()['id'] ?? $this->auth->user()['id'] ?? 0),
            null,
            'updated',
            'Passwort für den Zugang von „' . $targetUser['name'] . '" wurde durch Admin neu gesetzt.'
        );
        flash('success', 'Passwort für ' . $targetUser['name'] . ' wurde geändert.');
        Redirect::to('/users#user-' . $targetUser['id']);
    }

    public function updateOwnPassword(Request $request): void
    {
        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));

        $user = $this->auth->user();
        if (!$user) {
            Redirect::to('/login');
        }

        $currentPassword = (string) $request->input('current_password');
        $newPassword = trim((string) $request->input('new_password'));
        $newPasswordRepeat = trim((string) $request->input('new_password_repeat'));

        if (!$this->auth->verifyPassword($currentPassword)) {
            flash('error', 'Das aktuelle Passwort stimmt nicht.');
            Redirect::to('/account#password');
        }

        if (mb_strlen($newPassword) < 12) {
            flash('error', 'Das neue Passwort muss mindestens 12 Zeichen lang sein.');
            Redirect::to('/account#password');
        }

        if ($newPassword !== $newPasswordRepeat) {
            flash('error', 'Die beiden neuen Passwörter stimmen nicht überein.');
            Redirect::to('/account#password');
        }

        $this->users->updatePasswordHash((int) $user['id'], password_hash($newPassword, PASSWORD_DEFAULT));
        // Andere Geräte/Browser dieser Person abmelden – nach einem
        // Passwortwechsel soll eine evtl. übernommene Sitzung nicht weiterlaufen.
        // Die eigene Sitzung bleibt aktiv.
        $this->sessions->revokeAllForUser((int) $user['id'], session_id());
        \App\Core\Session::regenerate();
        $this->logs->addAudit(
            (int) ($this->auth->originalUser()['id'] ?? $this->auth->user()['id'] ?? 0),
            null,
            'updated',
            '„' . $user['name'] . '" hat das eigene Passwort geändert.'
        );

        flash('success', 'Dein Passwort wurde aktualisiert.');
        Redirect::to('/account#password');
    }

    public function sendReset(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));

        $targetUser = $this->users->findById((int) $request->input('user_id'));
        if (!$targetUser) {
            flash('error', 'Zugang nicht gefunden.');
            Redirect::to('/users');
        }

        if (!$this->passwordResets->createForUserId((int) $targetUser['id'])) {
            flash('error', 'Für diesen Zugang konnte kein Reset ausgelöst werden.');
            Redirect::to('/users#user-' . $targetUser['id']);
        }

        $this->logs->addAudit(
            (int) ($this->auth->originalUser()['id'] ?? $this->auth->user()['id'] ?? 0),
            null,
            'updated',
            'Reset-Mail für den Zugang von „' . $targetUser['name'] . '" wurde durch Admin ausgelöst.'
        );
        flash('success', 'Reset-Mail an ' . $targetUser['name'] . ' wurde versendet.');
        Redirect::to('/users#user-' . $targetUser['id']);
    }

    public function toggleActive(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));

        $targetUser = $this->users->findById((int) $request->input('user_id'));
        if (!$targetUser) {
            flash('error', 'Zugang nicht gefunden.');
            Redirect::to('/users');
        }

        $shouldActivate = (string) $request->input('set_active') === '1';
        $originalUserId = (int) ($this->auth->originalUser()['id'] ?? 0);

        if (!$shouldActivate && (int) $targetUser['id'] === $originalUserId) {
            flash('error', 'Das aktuell steuernde Admin-Konto kann nicht gesperrt werden.');
            Redirect::to('/users#user-' . $targetUser['id']);
        }

        if (
            !$shouldActivate
            && (string) ($targetUser['role_name'] ?? '') === 'admin'
            && (bool) ($targetUser['is_active'] ?? false)
            && $this->users->activeAdminCount() <= 1
        ) {
            flash('error', 'Der letzte aktive Admin kann nicht gesperrt werden.');
            Redirect::to('/users#user-' . $targetUser['id']);
        }

        $this->users->setActive((int) $targetUser['id'], $shouldActivate);
        $this->logs->addAudit(
            $originalUserId > 0 ? $originalUserId : (int) ($this->auth->user()['id'] ?? 0),
            null,
            'updated',
            sprintf(
                'Zugang von „%s" wurde durch Admin %s.',
                $targetUser['name'],
                $shouldActivate ? 'entsperrt' : 'gesperrt'
            )
        );

        flash('success', $targetUser['name'] . ($shouldActivate ? ' wurde entsperrt.' : ' wurde gesperrt.'));
        Redirect::to('/users#user-' . $targetUser['id']);
    }

    public function resetPasskeys(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));

        if (!$this->passkeys->isAvailable()) {
            flash('error', 'Passkeys sind erst nach der Datenbank-Migration verfügbar.');
            Redirect::to('/users');
        }

        $targetUser = $this->users->findById((int) $request->input('user_id'));
        if (!$targetUser) {
            flash('error', 'Zugang nicht gefunden.');
            Redirect::to('/users');
        }

        $deleted = $this->passkeys->deleteAllForUserId((int) $targetUser['id']);
        $this->logs->addAudit(
            (int) ($this->auth->originalUser()['id'] ?? $this->auth->user()['id'] ?? 0),
            null,
            'updated',
            'Alle Passkeys für den Zugang von „' . $targetUser['name'] . '" wurden durch Admin entfernt.'
        );

        flash(
            'success',
            $deleted > 0
                ? 'Alle Passkeys für ' . $targetUser['name'] . ' wurden entfernt.'
                : 'Für ' . $targetUser['name'] . ' waren keine Passkeys hinterlegt.'
        );
        Redirect::to('/users#user-' . $targetUser['id']);
    }

    private function generatePassword(): string
    {
        return substr(strtr(base64_encode(random_bytes(12)), '+/', 'AZ'), 0, 16);
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\LogRepository;
use App\Repositories\PasskeyRepository;
use App\Repositories\UserSessionRepository;
use App\Services\PasswordResetService;
use App\Services\Validator;
use App\Support\PasswordPolicy;
use App\Support\Redirect;

final class AuthController extends BaseController
{
    public function __construct(
        \App\Core\Auth $auth,
        private LogRepository $logs,
        private PasswordResetService $passwordResets,
        private PasskeyRepository $passkeys,
        private UserSessionRepository $sessions
    ) {
        parent::__construct($auth);
    }

    public function showLogin(): void
    {
        $this->render('auth/login', [
            'adminExists' => \App\Core\Container::get(\App\Repositories\UserRepository::class)->adminExists(),
            'passkeysAvailable' => $this->passkeys->isAvailable(),
        ]);
    }

    public function login(Request $request): void
    {
        Csrf::validate($request->input('_csrf'));
        $email = trim((string) $request->input('email'));
        $password = (string) $request->input('password');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $lockMinutes = (int) config('security.login_lock_minutes', 10);
        $attempts = $this->logs->recentFailedAttempts($email, $ip, $lockMinutes);
        $ipAttempts = $this->logs->recentFailedAttemptsByIp($ip, $lockMinutes);

        if (
            $attempts >= (int) config('security.login_max_attempts', 5)
            || $ipAttempts >= (int) config('security.login_max_attempts_ip', 20)
        ) {
            flash('error', 'Zu viele Fehlversuche. Bitte später erneut versuchen.');
            Redirect::to('/login');
        }

        if (!$this->auth->attempt($email, $password)) {
            $this->logs->addLoginAttempt($email, $ip, false);
            flash('error', 'Die Anmeldedaten stimmen nicht.');
            Redirect::to('/login');
        }

        $this->logs->addLoginAttempt($email, $ip, true);
        flash('success', 'Willkommen zurück.');
        Redirect::to('/');
    }

    public function logout(Request $request): void
    {
        Csrf::validate($request->input('_csrf'));
        $this->sessions->end(session_id());
        $this->auth->logout();
        Redirect::to('/login');
    }

    public function showForgotPassword(): void
    {
        $this->render('auth/forgot-password');
    }

    public function sendReset(Request $request): void
    {
        Csrf::validate($request->input('_csrf'));
        $errors = Validator::validate($request->all(), ['email' => ['required', 'email']]);

        if ($errors !== []) {
            $_SESSION['_errors'] = $errors;
            Redirect::to('/forgot-password');
        }

        $this->passwordResets->create((string) $request->input('email'));
        flash('success', 'Wenn ein Konto vorhanden ist, wurde ein Reset-Link verschickt.');
        Redirect::to('/login');
    }

    public function showResetPassword(Request $request, string $token = ''): void
    {
        // Alt-Links (/reset-password?token=…&email=…) auf die neue Pfad-Form
        // umleiten – der Query verschwindet damit aus dem Browser-Verlauf.
        if ($token === '') {
            $queryToken = trim((string) $request->input('token', ''));
            if ($queryToken !== '') {
                Redirect::to('/passwort-neu/' . rawurlencode($queryToken));
            }
            flash('error', 'Der Reset-Link ist unvollständig. Bitte fordere einen neuen an.');
            Redirect::to('/forgot-password');
        }

        $this->render('auth/reset-password', ['token' => $token, 'pageTitle' => 'Neues Passwort']);
    }

    public function resetPassword(Request $request): void
    {
        Csrf::validate($request->input('_csrf'));
        $token = (string) $request->input('token');
        $password = (string) $request->input('password');

        $passwordError = PasswordPolicy::validate($password);
        if ($passwordError !== null) {
            flash('error', $passwordError);
            Redirect::to('/passwort-neu/' . rawurlencode($token));
        }

        if (!$this->passwordResets->reset($token, $password)) {
            flash('error', 'Der Reset-Link ist ungültig oder abgelaufen.');
            Redirect::to('/forgot-password');
        }

        flash('success', 'Das Passwort wurde aktualisiert.');
        Redirect::to('/login');
    }
}

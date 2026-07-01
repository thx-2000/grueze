<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\LogRepository;
use App\Services\PasswordResetService;
use App\Services\Validator;
use App\Support\Redirect;

final class AuthController extends BaseController
{
    public function __construct(
        \App\Core\Auth $auth,
        private LogRepository $logs,
        private PasswordResetService $passwordResets
    ) {
        parent::__construct($auth);
    }

    public function showLogin(): void
    {
        $this->render('auth/login');
    }

    public function login(Request $request): void
    {
        Csrf::validate($request->input('_csrf'));
        $email = trim((string) $request->input('email'));
        $password = (string) $request->input('password');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $attempts = $this->logs->recentFailedAttempts(
            $email,
            $ip,
            (int) config('security.login_lock_minutes', 10)
        );

        if ($attempts >= (int) config('security.login_max_attempts', 5)) {
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

    public function showResetPassword(Request $request): void
    {
        $this->render('auth/reset-password', [
            'email' => (string) $request->input('email', ''),
            'token' => (string) $request->input('token', ''),
        ]);
    }

    public function resetPassword(Request $request): void
    {
        Csrf::validate($request->input('_csrf'));
        $email = (string) $request->input('email');
        $token = (string) $request->input('token');
        $password = (string) $request->input('password');

        if (mb_strlen($password) < 12) {
            flash('error', 'Das Passwort muss mindestens 12 Zeichen lang sein.');
            Redirect::to('/reset-password?email=' . urlencode($email) . '&token=' . urlencode($token));
        }

        if (!$this->passwordResets->reset($email, $token, $password)) {
            flash('error', 'Der Reset-Link ist ungültig oder abgelaufen.');
            Redirect::to('/forgot-password');
        }

        flash('success', 'Das Passwort wurde aktualisiert.');
        Redirect::to('/login');
    }
}


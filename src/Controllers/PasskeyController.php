<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\LogRepository;
use App\Repositories\PasskeyRepository;
use App\Services\WebAuthnService;
use App\Support\Redirect;
use JsonException;
use RuntimeException;

final class PasskeyController extends BaseController
{
    public function __construct(
        Auth $auth,
        private PasskeyRepository $passkeys,
        private WebAuthnService $webauthn,
        private LogRepository $logs
    ) {
        parent::__construct($auth);
    }

    public function index(): void
    {
        $this->requireAuth();
        Redirect::to('/account#passkeys');
    }

    public function registrationOptions(): void
    {
        try {
            $user = $this->auth->user();
            if (!$user) {
                throw new RuntimeException('Bitte zuerst anmelden.');
            }

            if (!$this->passkeys->isAvailable()) {
                throw new RuntimeException('Die Passkey-Funktion ist erst nach der Datenbank-Migration verfügbar.');
            }

            $payload = $this->jsonPayload();
            Csrf::validate((string) ($payload['_csrf'] ?? ''));

            $this->json([
                'ok' => true,
                'options' => $this->webauthn->beginRegistration(
                    $user,
                    $this->passkeys->byUserId((int) $user['id'])
                ),
            ]);
        } catch (\Throwable $exception) {
            $this->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function register(): void
    {
        try {
            $user = $this->auth->user();
            if (!$user) {
                throw new RuntimeException('Bitte zuerst anmelden.');
            }

            if (!$this->passkeys->isAvailable()) {
                throw new RuntimeException('Die Passkey-Funktion ist erst nach der Datenbank-Migration verfügbar.');
            }

            $payload = $this->jsonPayload();
            Csrf::validate((string) ($payload['_csrf'] ?? ''));

            $registration = $this->webauthn->finishRegistration($payload);
            $registration['label'] = trim((string) ($payload['label'] ?? ''));
            if ($registration['label'] === '') {
                $registration['label'] = 'Passkey ' . date('d.m.Y H:i');
            }

            $this->passkeys->create($registration);
            $this->logs->addAudit(
                (int) $user['id'],
                null,
                'updated',
                'Passkey für den Zugang von „' . $user['name'] . '" hinzugefügt.'
            );

            $this->json([
                'ok' => true,
                'message' => 'Passkey gespeichert.',
            ]);
        } catch (\Throwable $exception) {
            $this->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function authenticationOptions(): void
    {
        try {
            if (!$this->passkeys->isAvailable()) {
                throw new RuntimeException('Passkeys sind hier noch nicht aktiviert.');
            }

            $payload = $this->jsonPayload();
            Csrf::validate((string) ($payload['_csrf'] ?? ''));

            $this->json([
                'ok' => true,
                'options' => $this->webauthn->beginAuthentication(),
            ]);
        } catch (\Throwable $exception) {
            $this->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function authenticate(): void
    {
        try {
            if (!$this->passkeys->isAvailable()) {
                throw new RuntimeException('Passkeys sind hier noch nicht aktiviert.');
            }

            $payload = $this->jsonPayload();
            Csrf::validate((string) ($payload['_csrf'] ?? ''));

            $credentialId = WebAuthnService::base64urlDecode((string) ($payload['id'] ?? ''));
            $storedCredential = $this->passkeys->findByCredentialId($credentialId);
            if (!$storedCredential || !(bool) ($storedCredential['is_active'] ?? false)) {
                throw new RuntimeException('Zu diesem Passkey wurde kein aktiver Zugang gefunden.');
            }

            $verification = $this->webauthn->finishAuthentication($storedCredential, $payload);
            if (!$this->auth->loginUsingId((int) $verification['user_id'])) {
                throw new RuntimeException('Die Passkey-Anmeldung konnte nicht abgeschlossen werden.');
            }

            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $this->passkeys->updateUsage((int) $verification['passkey_id'], (int) $verification['sign_count'], $ip);
            $this->logs->addLoginAttempt((string) ($storedCredential['user_email'] ?? ''), $ip, true);
            $this->logs->addAudit(
                (int) $verification['user_id'],
                null,
                'updated',
                'Passkey-Anmeldung erfolgreich.'
            );

            $this->json([
                'ok' => true,
                'redirect' => url('/'),
            ]);
        } catch (\Throwable $exception) {
            $this->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function delete(Request $request): void
    {
        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));

        $user = $this->auth->user();
        if (!$user || !$this->passkeys->isAvailable()) {
            Redirect::to('/security/passkeys');
        }

        $passkeyId = (int) $request->input('passkey_id');
        $this->passkeys->deleteForUser($passkeyId, (int) $user['id']);
        $this->logs->addAudit(
            (int) $user['id'],
            null,
            'updated',
            'Ein Passkey für den Zugang von „' . $user['name'] . '" wurde entfernt.'
        );

        flash('success', 'Passkey entfernt.');
        Redirect::to('/account#passkeys');
    }

    private function jsonPayload(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('Die Anfrage an die Passkey-Schnittstelle ist ungültig.');
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_THROW_ON_ERROR);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\LogRepository;
use App\Repositories\SettingRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSessionRepository;
use PDO;

final class PasswordResetService
{
    public function __construct(
        private PDO $pdo,
        private UserRepository $users,
        private MailService $mailer,
        private SettingRepository $settings,
        private LogRepository $logs,
        private UserSessionRepository $sessions
    )
    {
    }

    public function create(string $email): void
    {
        $user = $this->users->findByEmail($email);
        if (!$user) {
            // Etwas Arbeit auch für unbekannte Adressen, damit die Antwortzeit
            // nicht verrät, ob ein Konto existiert.
            password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

            return;
        }

        // Anti-Spam: pro Konto höchstens alle paar Minuten eine neue Reset-Mail.
        $recent = $this->pdo->prepare(
            'SELECT COUNT(*) FROM password_resets
             WHERE user_id = :id AND used_at IS NULL
             AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)'
        );
        $recent->execute(['id' => $user['id']]);
        if ((int) $recent->fetchColumn() > 0) {
            return;
        }

        $this->createForUser($user);
    }

    public function createForUserId(int $userId): bool
    {
        $user = $this->users->findById($userId);
        if (!$user || !(bool) ($user['is_active'] ?? false) || empty($user['email'])) {
            return false;
        }

        $this->createForUser($user);

        return true;
    }

    private function createForUser(array $user): void
    {
        $token = bin2hex(random_bytes(32));
        $hash = password_hash($token, PASSWORD_DEFAULT);
        $expiresAt = date('Y-m-d H:i:s', time() + ((int) config('security.password_reset_expires_minutes', 60) * 60));

        $stmt = $this->pdo->prepare(
            'INSERT INTO password_resets (user_id, token_hash, token_sha, expires_at)
             VALUES (:user_id, :token_hash, :token_sha, :expires_at)'
        );
        $stmt->execute([
            'user_id' => $user['id'],
            'token_hash' => $hash,
            'token_sha' => hash('sha256', $token),
            'expires_at' => $expiresAt,
        ]);

        // Token im Pfad, nicht im Query – landet so nicht in Server-Logs,
        // Browser-Verlauf oder Referrer-Headern.
        $link = url('/passwort-neu/' . rawurlencode($token));
        $body = "Hallo {$user['name']},\n\nüber diesen Link kannst du dein Passwort neu setzen:\n{$link}\n\nDer Link ist 60 Minuten gültig.";
        $identity = $this->settings->mailIdentity();
        $this->mailer->sendSystemMail($identity, $user['email'], 'Passwort zurücksetzen', $body);
    }

    public function reset(string $token, string $password): bool
    {
        $token = trim($token);
        if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
            return false;
        }

        // Schneller Zugriff über den SHA-Index; die eigentliche Gültigkeit
        // entscheidet danach der bcrypt-Vergleich (konstantzeitig).
        $stmt = $this->pdo->prepare(
            'SELECT * FROM password_resets
             WHERE token_sha = :sha AND used_at IS NULL AND expires_at >= NOW()
             LIMIT 1'
        );
        $stmt->execute(['sha' => hash('sha256', $token)]);
        $reset = $stmt->fetch();

        if (!$reset || !password_verify($token, (string) $reset['token_hash'])) {
            return false;
        }

        $userId = (int) $reset['user_id'];
        $this->pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
            ->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $userId]);

        // Diesen und alle anderen offenen Tokens des Kontos verbrauchen.
        $this->pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = :id AND used_at IS NULL')
            ->execute(['id' => $userId]);

        // Laufende Sitzungen des Kontos beenden – nach „Passwort vergessen" soll
        // keine alte (womöglich übernommene) Sitzung weiterlaufen.
        $this->sessions->revokeAllForUser($userId);

        $this->logs->addAudit(
            $userId,
            null,
            'updated',
            'Passwort über den „Passwort vergessen"-Link neu gesetzt.'
        );

        return true;
    }
}

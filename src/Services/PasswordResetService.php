<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SettingRepository;
use App\Repositories\UserRepository;
use PDO;

final class PasswordResetService
{
    public function __construct(
        private PDO $pdo,
        private UserRepository $users,
        private MailService $mailer,
        private SettingRepository $settings
    )
    {
    }

    public function create(string $email): void
    {
        $user = $this->users->findByEmail($email);
        if (!$user) {
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
        $email = (string) $user['email'];

        $token = bin2hex(random_bytes(32));
        $hash = password_hash($token, PASSWORD_DEFAULT);
        $expiresAt = date('Y-m-d H:i:s', time() + ((int) config('security.password_reset_expires_minutes', 60) * 60));

        $stmt = $this->pdo->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, :expires_at)'
        );
        $stmt->execute([
            'user_id' => $user['id'],
            'token_hash' => $hash,
            'expires_at' => $expiresAt,
        ]);

        $link = url('/reset-password?token=' . urlencode($token) . '&email=' . urlencode($email));
        $body = "Hallo {$user['name']},\n\nüber diesen Link kannst du dein Passwort neu setzen:\n{$link}\n\nDer Link ist 60 Minuten gültig.";
        $identity = $this->settings->mailIdentity();
        $this->mailer->sendSystemMail($identity, $user['email'], 'Passwort zurücksetzen', $body);
    }

    public function reset(string $email, string $token, string $password): bool
    {
        $user = $this->users->findByEmail($email);
        if (!$user) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM password_resets
             WHERE user_id = :user_id AND used_at IS NULL AND expires_at >= NOW()
             ORDER BY id DESC'
        );
        $stmt->execute(['user_id' => $user['id']]);

        foreach ($stmt->fetchAll() as $reset) {
            if (password_verify($token, $reset['token_hash'])) {
                $update = $this->pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
                $update->execute([
                    'hash' => password_hash($password, PASSWORD_DEFAULT),
                    'id' => $user['id'],
                ]);

                $this->pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id')
                    ->execute(['id' => $reset['id']]);

                return true;
            }
        }

        return false;
    }
}

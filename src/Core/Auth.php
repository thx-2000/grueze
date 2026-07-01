<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\UserRepository;

final class Auth
{
    public function __construct(private UserRepository $users)
    {
    }

    public function user(): ?array
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return null;
        }

        return $this->users->findById((int) $userId);
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->users->findByEmail($email);
        if (!$user || !(bool) $user['is_active']) {
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        $_SESSION['user_id'] = $user['id'];
        Session::regenerate();
        $this->users->touchLogin((int) $user['id']);

        return true;
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public function can(string $permission): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        $matrix = [
            'contacts.manage' => ['admin', 'orga', 'stufenmitglied'],
            'contacts.delete' => ['admin', 'orga'],
            'categories.manage' => ['admin', 'orga'],
            'contacts.export' => ['admin', 'orga'],
            'contacts.copy_emails' => ['admin', 'orga', 'stufenmitglied', 'betrachter'],
            'audit.view' => ['admin', 'orga'],
            'users.manage' => ['admin'],
            'mail.send' => ['admin', 'orga'],
            'mail.view_log' => ['admin', 'orga'],
        ];

        return in_array($user['role_name'], $matrix[$permission] ?? [], true);
    }
}


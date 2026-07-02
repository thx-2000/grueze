<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\UserRepository;

final class Auth
{
    private const CONTACT_DETAIL_FIELDS = ['address', 'birthday', 'emails', 'phones', 'notes', 'login'];

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

        if ($permission === 'contacts.view_private_details') {
            foreach (self::CONTACT_DETAIL_FIELDS as $field) {
                if ($this->canViewContactField($field)) {
                    return true;
                }
            }

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
            'settings.manage' => ['admin', 'orga'],
        ];

        return in_array($user['role_name'], $matrix[$permission] ?? [], true);
    }

    public function canViewContactField(string $field): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        $legacyRoles = (array) config('security.private_contact_detail_roles', ['admin', 'orga', 'stufenmitglied']);
        $defaultVisibility = array_fill_keys(self::CONTACT_DETAIL_FIELDS, $legacyRoles);
        $configuredVisibility = (array) config('security.contact_detail_visibility', []);
        $allowedRoles = (array) ($configuredVisibility[$field] ?? $defaultVisibility[$field] ?? []);

        return in_array((string) $user['role_name'], $allowedRoles, true);
    }
}

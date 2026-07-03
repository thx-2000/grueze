<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\SettingRepository;
use App\Repositories\UserRepository;

final class Auth
{
    private const CONTACT_DETAIL_FIELDS = ['address', 'birthday', 'emails', 'phones', 'notes', 'login'];

    public function __construct(private UserRepository $users, private SettingRepository $settings)
    {
    }

    public function user(): ?array
    {
        $userId = $this->activeUserId();
        if ($userId === null) {
            return null;
        }

        $user = $this->users->findById($userId);
        if ($user && (bool) $user['is_active']) {
            return $user;
        }

        if ($this->isImpersonating()) {
            $this->stopImpersonation();
            return $this->originalUser();
        }

        return null;
    }

    public function originalUser(): ?array
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return null;
        }

        $user = $this->users->findById((int) $userId);

        return $user && (bool) $user['is_active'] ? $user : null;
    }

    public function isImpersonating(): bool
    {
        return !empty($_SESSION['impersonated_user_id']);
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

    public function loginUsingId(int $userId): bool
    {
        $user = $this->users->findById($userId);
        if (!$user || !(bool) $user['is_active']) {
            return false;
        }

        $_SESSION['user_id'] = (int) $user['id'];
        unset($_SESSION['impersonated_user_id']);
        Session::regenerate();
        $this->users->touchLogin((int) $user['id']);

        return true;
    }

    public function startImpersonation(int $targetUserId): bool
    {
        if (!$this->canAsOriginal('users.manage')) {
            return false;
        }

        $original = $this->originalUser();
        $target = $this->users->findById($targetUserId);
        if (!$original || !$target || !(bool) $target['is_active'] || (int) $original['id'] === (int) $target['id']) {
            return false;
        }

        $_SESSION['impersonated_user_id'] = (int) $target['id'];
        Session::regenerate();

        return true;
    }

    public function stopImpersonation(): void
    {
        unset($_SESSION['impersonated_user_id']);
        Session::regenerate();
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public function can(string $permission): bool
    {
        return $this->resolvePermission($this->user(), $permission);
    }

    public function canAsOriginal(string $permission): bool
    {
        return $this->resolvePermission($this->originalUser(), $permission);
    }

    public function canViewContactField(string $field): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        $visibility = $this->settings->fieldVisibility();
        if (array_key_exists($field, $visibility)) {
            return in_array((string) $user['role_name'], $visibility[$field], true);
        }

        $legacyRoles = (array) config('security.private_contact_detail_roles', ['admin', 'orga']);
        $defaultVisibility = array_fill_keys(self::CONTACT_DETAIL_FIELDS, $legacyRoles);
        $configuredVisibility = (array) config('security.contact_detail_visibility', []);
        $allowedRoles = (array) ($configuredVisibility[$field] ?? $defaultVisibility[$field] ?? []);

        return in_array((string) $user['role_name'], $allowedRoles, true);
    }

    private function activeUserId(): ?int
    {
        $impersonated = $_SESSION['impersonated_user_id'] ?? null;
        if ($impersonated) {
            return (int) $impersonated;
        }

        $userId = $_SESSION['user_id'] ?? null;

        return $userId ? (int) $userId : null;
    }

    private function resolvePermission(?array $user, string $permission): bool
    {
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
            'contacts.manage' => ['admin', 'orga'],
            'contacts.delete' => ['admin', 'orga'],
            'categories.manage' => ['admin', 'orga'],
            'contacts.export' => ['admin'],
            'contacts.copy_emails' => ['admin', 'orga'],
            'audit.view' => ['admin'],
            'users.manage' => ['admin'],
            'mail.send' => ['admin', 'orga'],
            'mail.contact_single' => ['stufenmitglied'],
            'mail.view_log' => ['admin', 'orga'],
            'settings.manage' => ['admin', 'orga'],
        ];

        return in_array((string) $user['role_name'], $matrix[$permission] ?? [], true);
    }
}

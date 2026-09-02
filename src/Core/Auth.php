<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\SettingRepository;
use App\Repositories\UserRepository;

final class Auth
{
    private const CONTACT_DETAIL_FIELDS = ['address', 'birthday', 'emails', 'phones', 'notes', 'login'];

    /** Pro Request gecacht: [aktive User-ID => User-Array|null]. */
    private array $userCache = [];

    public function __construct(private UserRepository $users, private SettingRepository $settings)
    {
    }

    public function user(): ?array
    {
        $userId = $this->activeUserId();
        if ($userId === null) {
            return null;
        }

        if (array_key_exists($userId, $this->userCache)) {
            return $this->userCache[$userId];
        }

        $user = $this->users->findById($userId);
        if ($user && (bool) $user['is_active']) {
            return $this->userCache[$userId] = $user;
        }

        if ($this->isImpersonating()) {
            $this->stopImpersonation();
            return $this->originalUser();
        }

        return $this->userCache[$userId] = null;
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
            // Gegen Benutzer-Enumeration über die Antwortzeit: immer einen
            // Hash-Vergleich rechnen, auch wenn es das Konto nicht gibt.
            password_verify($password, '$2y$10$PIEi38KRWEQrrJHRr/syo.Nz0axOmZksjfptdM7XJ7o6NNyESXg3K');

            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        $_SESSION['user_id'] = $user['id'];
        $this->userCache = [];
        unset($_SESSION['_csrf']);
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
        unset($_SESSION['impersonated_user_id'], $_SESSION['_csrf']);
        $this->userCache = [];
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
        $this->userCache = [];
        Session::regenerate();

        return true;
    }

    public function stopImpersonation(): void
    {
        unset($_SESSION['impersonated_user_id']);
        $this->userCache = [];
        Session::regenerate();
    }

    public function logout(): void
    {
        $this->userCache = [];
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

    /**
     * @param array<string,mixed>|null $contact Wenn übergeben, greift zusätzlich
     *   die „eigener verknüpfter Kontakt"-Ausnahme: Nutzer:innen sehen die
     *   Daten ihres eigenen Kontakts – Notizen bleiben davon ausgenommen und
     *   folgen weiter der Rollenregel.
     */
    public function canViewContactField(string $field, ?array $contact = null): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        if ((string) $user['role_name'] === 'admin') {
            return true;
        }

        if ($this->roleAllowsContactField($user, $field)) {
            return true;
        }

        return $field !== 'notes'
            && $contact !== null
            && $this->settings->ownContactAlwaysVisible()
            && isset($user['contact_id'], $contact['id'])
            && (int) $user['contact_id'] === (int) $contact['id'];
    }

    private function roleAllowsContactField(array $user, string $field): bool
    {
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

        if ((string) $user['role_name'] === 'admin') {
            return true;
        }

        if ($permission === 'contacts.view_private_details') {
            foreach (self::CONTACT_DETAIL_FIELDS as $field) {
                if ($this->canViewContactField($field)) {
                    return true;
                }
            }

            return false;
        }

        $matrix = $this->settings->permissionMatrix();
        if (array_key_exists($permission, $matrix)) {
            return in_array((string) $user['role_name'], $matrix[$permission], true);
        }

        $defaults = $this->settings->permissionDefaults();

        return in_array((string) $user['role_name'], $defaults[$permission] ?? [], true);
    }
}

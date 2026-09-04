<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;

/**
 * Hält den Login-Zugang synchron, der optional an einem Kontakt hängt: anlegen,
 * aktualisieren, deaktivieren oder einen bestehenden verwaisten Zugang
 * verknüpfen. Nur relevant, wenn die handelnde Person `users.manage` hat –
 * ohne dieses Recht tut der Service nichts.
 */
final class LinkedAccountService
{
    public function __construct(private UserRepository $users)
    {
    }

    /**
     * @param array<string,mixed> $data bereinigte Kontaktdaten inkl. login_enabled/login_email/role_id
     * @return string Klartext-Hinweis für die Flash-Meldung ('' = nichts passiert)
     */
    public function sync(int $contactId, array $data): string
    {
        if (!can('users.manage')) {
            return '';
        }

        $fullName = trim($data['vorname'] . ' ' . $data['nachname']);
        $linkedUser = $this->users->findByContactId($contactId);

        if (!$data['login_enabled']) {
            if ($linkedUser) {
                $this->users->updateLinkedAccount((int) $linkedUser['id'], [
                    'name' => $fullName,
                    'email' => $data['login_email'] ?: $linkedUser['email'],
                    'role_id' => $data['role_id'] ?: (int) $linkedUser['role_id'],
                    'is_active' => 0,
                    'contact_id' => $contactId,
                ]);

                return 'Der verknüpfte Login wurde deaktiviert.';
            }

            return '';
        }

        if ($linkedUser) {
            $this->users->updateLinkedAccount((int) $linkedUser['id'], [
                'name' => $fullName,
                'email' => $data['login_email'],
                'role_id' => $data['role_id'],
                'is_active' => 1,
                'contact_id' => $contactId,
            ]);

            return 'Login und Rolle wurden aktualisiert.';
        }

        $existingUser = $this->users->findByEmail($data['login_email']);
        if ($existingUser && empty($existingUser['contact_id'])) {
            $this->users->updateLinkedAccount((int) $existingUser['id'], [
                'name' => $fullName,
                'email' => $data['login_email'],
                'role_id' => $data['role_id'],
                'is_active' => 1,
                'contact_id' => $contactId,
            ]);

            return 'Bestehender Zugang wurde mit diesem Kontakt verknüpft.';
        }

        $password = $this->generatePassword();
        $this->users->create([
            'name' => $fullName,
            'email' => $data['login_email'],
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role_id' => $data['role_id'],
            'is_active' => 1,
            'contact_id' => $contactId,
        ]);

        return 'Login angelegt. Erstpasswort: ' . $password;
    }

    /**
     * Prüft, ob die gewünschte Login-Adresse schon von einem anderen Zugang
     * belegt ist.
     *
     * @param array<string,mixed> $data
     * @return array<string,string> Validierungsfehler (leer = ok)
     */
    public function validateUniqueness(array $data, ?int $contactId): array
    {
        if (($data['login_email'] ?? '') === '') {
            return [];
        }

        $existingUser = $this->users->findByEmail($data['login_email']);
        if (!$existingUser) {
            return [];
        }

        if ((int) ($existingUser['contact_id'] ?? 0) === (int) ($contactId ?? 0) || empty($existingUser['contact_id'])) {
            return [];
        }

        return [
            'login_email' => 'Diese Login-E-Mail wird bereits von einem anderen Zugang verwendet.',
        ];
    }

    private function generatePassword(): string
    {
        return substr(strtr(base64_encode(random_bytes(12)), '+/', 'AZ'), 0, 16);
    }
}

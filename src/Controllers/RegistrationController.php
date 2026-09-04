<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\ContactRepository;
use App\Repositories\RegistrationInviteRepository;
use App\Repositories\SettingRepository;
use App\Repositories\UserRepository;
use App\Services\MailService;
use App\Support\Redirect;

/**
 * Selbst-Registrierung / Account-Anlage über Einladungslink.
 *  - Eine berechtigte Person erzeugt auf einem Kontakt einen Link.
 *  - Oder (falls freigeschaltet) trägt eine Person selbst ihre bekannte
 *    Mailadresse ein; der Link geht dann an genau diese Adresse.
 * Über den Link legt die Person Name + Kennwort fest, der Account bekommt eine
 * niedrige Standardrolle und wird mit dem Kontakt verknüpft.
 */
final class RegistrationController extends BaseController
{
    public function __construct(
        \App\Core\Auth $auth,
        private RegistrationInviteRepository $invites,
        private UserRepository $users,
        private ContactRepository $contacts,
        private SettingRepository $settings,
        private MailService $mailer,
    ) {
        parent::__construct($auth);
    }

    // ------------------------------------------------------- öffentlicher Flow

    public function form(Request $request, string $token = ''): void
    {
        // Token bevorzugt aus dem Pfad; `?token=` nur als Rückfall für
        // Einladungslinks, die vor der Umstellung verschickt wurden.
        $token = trim($token) !== '' ? trim($token) : trim((string) $request->input('token', ''));
        if ($token !== '') {
            $invite = $this->invites->findValidByToken($token);
            if ($invite === null) {
                render_error_page(410, 'Link nicht mehr gültig', 'Der Einladungslink ist abgelaufen oder wurde schon benutzt. Bitte einen neuen anfordern.');

                return;
            }

            $this->render('auth/register', [
                'token' => $token,
                'email' => (string) $invite['email'],
                'suggestedName' => trim(((string) ($invite['vorname'] ?? '')) . ' ' . ((string) ($invite['nachname'] ?? ''))),
            ]);

            return;
        }

        $this->render('auth/register-request', [
            'selfEnabled' => $this->settings->registrationSettings()['self_enabled'],
        ]);
    }

    /** Ein POST-Endpunkt: mit Token → Konto anlegen, ohne → Link anfordern. */
    public function submit(Request $request, string $token = ''): void
    {
        $token = trim($token) !== '' ? trim($token) : trim((string) $request->input('token', ''));
        if ($token !== '') {
            $this->complete($request, $token);

            return;
        }

        $this->requestSelf($request);
    }

    public function requestSelf(Request $request): void
    {
        Csrf::validate($request->input('_csrf'));
        $config = $this->settings->registrationSettings();

        // Immer dieselbe neutrale Antwort – verrät nicht, ob die Adresse bekannt ist.
        $neutral = static function (): never {
            flash('success', 'Wenn diese Adresse bei uns hinterlegt ist, kommt gleich eine E-Mail mit dem Link zur Registrierung.');
            Redirect::to('/login');
        };

        if (!$config['self_enabled']) {
            flash('error', 'Die Selbst-Anmeldung ist zurzeit nicht möglich. Bitte an das Orga-Team wenden.');
            Redirect::to('/login');
        }

        $email = mb_strtolower(trim((string) $request->input('email')));
        $note = trim((string) $request->input('note'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Bitte eine gültige Mailadresse eingeben.');
            Redirect::to('/registrieren');
        }

        // Rate-Limit: max. 5 Anfragen je Quelle pro Stunde.
        $ipHash = $this->sourceHash();
        if ($this->invites->recentCountByIp($ipHash, 60) >= 5) {
            flash('error', 'Zu viele Anfragen. Bitte später erneut versuchen.');
            Redirect::to('/login');
        }

        // Schon ein aktiver Account? Schon eine offene Einladung? → neutral raus.
        $existingUser = $this->users->findByEmail($email);
        if (($existingUser && (int) $existingUser['is_active'] === 1) || $this->invites->pendingForEmail($email) !== null) {
            $neutral();
        }

        $contactId = $this->contacts->findIdByEmail($email);
        if ($contactId !== null && !($existingUser && (int) ($existingUser['contact_id'] ?? 0) === $contactId)) {
            $token = $this->invites->create($email, $contactId, null, $config['link_hours'], $note, $ipHash);
            $this->sendInviteMail($email, $token, $config['link_hours']);
        } else {
            // Unbekannte Adresse → Freigabe durch Admin/Orga nötig.
            $this->invites->createAwaitingApproval($email, $note, $ipHash, $config['link_hours']);
        }

        $neutral();
    }

    public function complete(Request $request, string $token = ''): void
    {
        Csrf::validate($request->input('_csrf'));
        $token = trim($token) !== '' ? trim($token) : trim((string) $request->input('token', ''));
        $invite = $token !== '' ? $this->invites->findValidByToken($token) : null;
        if ($invite === null) {
            render_error_page(410, 'Link nicht mehr gültig', 'Der Einladungslink ist abgelaufen oder wurde schon benutzt.');

            return;
        }

        $name = trim((string) $request->input('name'));
        $password = trim((string) $request->input('password'));
        $repeat = trim((string) $request->input('password_repeat'));
        $usePasskey = (string) $request->input('mode') === 'passkey';
        $backTo = '/registrieren/' . rawurlencode($token);

        if ($name === '') {
            flash('error', 'Bitte einen Namen angeben.');
            Redirect::to($backTo);
        }
        if (!$usePasskey && mb_strlen($password) < 12) {
            flash('error', 'Das Passwort muss mindestens 12 Zeichen lang sein.');
            Redirect::to($backTo);
        }
        if (!$usePasskey && $password !== $repeat) {
            flash('error', 'Die Passwörter stimmen nicht überein.');
            Redirect::to($backTo);
        }
        if ($this->users->findByEmail((string) $invite['email']) !== null) {
            flash('error', 'Für diese Adresse gibt es schon ein Konto. Bitte über „Passwort vergessen" anmelden.');
            Redirect::to('/login');
        }

        $roleName = $this->settings->registrationSettings()['default_role'];
        $roleId = $roleName !== 'admin' ? $this->users->roleIdByName($roleName) : null;
        $roleId ??= $this->leastPrivilegedRoleId();
        if ($roleId === null) {
            flash('error', 'Es ist keine passende Rolle hinterlegt. Bitte an das Orga-Team wenden.');
            Redirect::to('/login');
        }

        $userId = $this->users->create([
            'name' => $name,
            'email' => (string) $invite['email'],
            // Bei Passkey ein zufälliges, unbrauchbares Passwort – der Passkey
            // wird direkt danach unter „Mein Eintrag" eingerichtet.
            'password_hash' => password_hash($usePasskey ? bin2hex(random_bytes(24)) : $password, PASSWORD_DEFAULT),
            'role_id' => $roleId,
            'is_active' => 1,
            'contact_id' => $invite['contact_id'] !== null ? (int) $invite['contact_id'] : null,
        ]);
        $this->invites->markUsed((int) $invite['id']);
        $this->auth->loginUsingId($userId);

        if ($usePasskey) {
            flash('success', 'Dein Zugang ist da. Richte jetzt gleich deinen Passkey ein – danach meldest du dich einfach per Gerätefreigabe an.');
            Redirect::to('/account#passkeys');
        }

        flash('success', 'Willkommen! Dein Zugang ist eingerichtet. Einen Passkey kannst du jederzeit unter „Mein Eintrag" hinzufügen.');
        Redirect::to('/account');
    }

    // ------------------------------------------------------------- Verwaltung

    public function settingsForm(): void
    {
        $this->requirePermission('users.manage');

        $this->render('settings/registration', [
            'config' => $this->settings->registrationSettings(),
            'roles' => $this->users->roles(),
            'openInvites' => $this->invites->open(),
            'awaiting' => $this->invites->awaitingApproval(),
        ]);
    }

    public function approveRequest(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));

        $invite = $this->invites->findById((int) $request->input('id'));
        if ($invite === null || $invite['status'] !== 'awaiting_approval') {
            Redirect::to('/verwaltung/registrierung');
        }

        $config = $this->settings->registrationSettings();
        $contactId = $this->contacts->findIdByEmail((string) $invite['email']);
        $token = $this->invites->create((string) $invite['email'], $contactId, (int) $this->auth->user()['id'], $config['link_hours']);
        $this->invites->setStatus((int) $invite['id'], 'revoked');
        $mailStatus = $this->sendInviteMail((string) $invite['email'], $token, $config['link_hours']);

        flash('success', 'Freigegeben. ' . $mailStatus . ' Link: ' . $this->inviteUrl($token));
        Redirect::to('/verwaltung/registrierung');
    }

    public function rejectRequest(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));
        $this->invites->setStatus((int) $request->input('id'), 'revoked');
        flash('success', 'Anfrage abgelehnt.');
        Redirect::to('/verwaltung/registrierung');
    }

    public function updateSettings(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));

        // Standard-Rolle für neue Zugänge: muss eine echte Rolle sein und darf
        // niemals „admin" sein – sonst würde jede Selbst-Registrierung ein
        // Admin-Konto anlegen.
        $requestedRole = trim((string) $request->input('default_role'));
        $nonAdmin = array_values(array_filter(
            $this->users->roles(),
            static fn (array $r): bool => (string) $r['name'] !== 'admin'
        ));
        $validRoles = array_column($nonAdmin, 'name');
        $defaultRole = in_array($requestedRole, $validRoles, true)
            ? $requestedRole
            : (string) ($nonAdmin[0]['name'] ?? '');

        $this->settings->set('registration_self_enabled', $request->input('self_enabled') !== null ? '1' : '0');
        $this->settings->set('registration_default_role', $defaultRole);
        $this->settings->set('registration_link_hours', (string) max(1, min(720, (int) $request->input('link_hours', 72))));

        flash('success', 'Einstellungen gespeichert.');
        Redirect::to('/verwaltung/registrierung');
    }

    public function createInvite(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));

        $contactId = (int) $request->input('contact_id');
        $contact = $this->contacts->find($contactId);
        if ($contact === null) {
            flash('error', 'Kontakt nicht gefunden.');
            Redirect::to('/kontakte');
        }

        $email = mb_strtolower(trim((string) ($contact['emails'][0]['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Für diesen Kontakt ist keine Mailadresse hinterlegt.');
            Redirect::to('/contacts/edit?id=' . $contactId);
        }
        if (!empty($contact['linked_user'])) {
            flash('error', 'Dieser Kontakt hat schon einen Login.');
            Redirect::to('/contacts/edit?id=' . $contactId);
        }

        $config = $this->settings->registrationSettings();
        $token = $this->invites->create($email, $contactId, (int) $this->auth->user()['id'], $config['link_hours']);
        $link = $this->inviteUrl($token);
        $mailStatus = $this->sendInviteMail($email, $token, $config['link_hours']);

        flash('success', trim(
            'Einladungslink erstellt (gültig ' . $config['link_hours'] . ' Std.). ' . $mailStatus
            . ' Link zum Weitergeben: ' . $link
        ));
        Redirect::to('/contacts/edit?id=' . $contactId);
    }

    public function revokeInvite(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));

        $email = mb_strtolower(trim((string) $request->input('email')));
        if ($email !== '') {
            $this->invites->revokePendingForEmail($email);
            flash('success', 'Einladung zurückgenommen.');
        }
        Redirect::to('/verwaltung/registrierung');
    }

    // ------------------------------------------------------------------ intern

    private function inviteUrl(string $token): string
    {
        // Token im Pfad, nicht im Query – kein Leck über Server-Logs,
        // Browser-Verlauf oder Referrer-Header.
        return url('/registrieren/' . rawurlencode($token));
    }

    private function sourceHash(): string
    {
        return source_hash('registrierung');
    }

    private function sendInviteMail(string $email, string $token, int $hours): string
    {
        $branding = app_branding();
        $appName = (string) ($branding['branding_app_name'] ?? 'Adress-Zentrale');
        $link = $this->inviteUrl($token);

        $body = "Hallo,\n\n"
            . "über diesen Link kannst du dir einen Zugang zu " . $appName . " einrichten "
            . "(Name bestätigen, Kennwort festlegen):\n\n" . $link . "\n\n"
            . "Der Link ist " . $hours . " Stunden gültig und nur für dich.\n\n"
            . "Wenn du das nicht angefordert hast, ignoriere diese Mail einfach.";

        try {
            $this->mailer->sendSystemMail($this->settings->mailIdentity(), $email, 'Dein Zugang zu ' . $appName, $body);

            return 'Die Mail mit dem Link ist an ' . $email . ' unterwegs.';
        } catch (\Throwable) {
            return 'Die Mail konnte nicht versendet werden – bitte den Link manuell weitergeben.';
        }
    }

    /**
     * Fallback für die Standard-Registrierungsrolle: die Nicht-Admin-Rolle mit
     * den wenigsten Rechten. Nur nötig, wenn die konfigurierte Rolle nicht mehr
     * existiert (z. B. nach einer Schlüssel-Änderung, die nicht mitgezogen wurde).
     */
    private function leastPrivilegedRoleId(): ?int
    {
        $matrix = $this->settings->permissionMatrix();
        $best = null;
        $bestCount = PHP_INT_MAX;
        foreach ($this->users->roles() as $role) {
            $name = (string) $role['name'];
            if ($name === 'admin') {
                continue;
            }
            $count = 0;
            foreach ($matrix as $roles) {
                if (in_array($name, $roles, true)) {
                    $count++;
                }
            }
            if ($count < $bestCount) {
                $bestCount = $count;
                $best = (int) $role['id'];
            }
        }

        return $best;
    }
}

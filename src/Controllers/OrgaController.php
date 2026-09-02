<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\LogRepository;
use App\Repositories\SettingRepository;
use App\Repositories\UserRepository;
use App\Services\MailService;
use App\Support\Redirect;

/**
 * „Orga-Team schreiben": schneller Draht ans Organisations-Team. Für jede
 * eingeloggte Person. Ziel ist entweder eine fest hinterlegte Adresse oder –
 * ohne feste Adresse – alle aktiven Nutzer:innen mit der Rolle „Orga-Team".
 */
final class OrgaController extends BaseController
{
    public function __construct(
        \App\Core\Auth $auth,
        private UserRepository $users,
        private SettingRepository $settings,
        private MailService $mailer,
        private LogRepository $logs,
    ) {
        parent::__construct($auth);
    }

    public function form(): void
    {
        $this->requireAuth();

        $this->render('mail/orga-team', [
            'targetDescription' => $this->targetDescription(),
        ]);
    }

    public function send(Request $request): void
    {
        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));

        $subject = trim((string) $request->input('subject'));
        $message = trim((string) $request->input('message'));
        if ($subject === '' || $message === '') {
            flash('error', 'Bitte Betreff und Nachricht ausfüllen.');
            Redirect::to('/orga-team');
        }

        $recipients = $this->recipients();
        if ($recipients === []) {
            flash('error', 'Es ist kein Orga-Team hinterlegt. Bitte an eine:n Admin wenden.');
            Redirect::to('/orga-team');
        }

        $identity = $this->settings->mailIdentity();
        $user = $this->auth->user();
        $shortName = trim((string) branding_value('branding_short_name', '')) ?: 'Orga';
        $fullSubject = '[' . $shortName . '] ' . $subject;
        $body = $message . "\n\n—\nGesendet über den Orga-Knopf von "
            . (string) ($user['name'] ?? '') . ' <' . (string) ($user['email'] ?? '') . '>';

        $sent = 0;
        $failed = 0;
        foreach ($recipients as $to) {
            try {
                $this->mailer->sendSystemMail($identity, $to, $fullSubject, $body, (string) ($user['email'] ?? '') ?: null);
                $sent++;
                $status = 'gesendet';
                $error = null;
            } catch (\Throwable $exception) {
                $failed++;
                $status = 'fehlgeschlagen';
                $error = $exception->getMessage();
            }
            $this->logs->addMailLog([
                'user_id' => (int) $user['id'],
                'contact_id' => null,
                'empfaenger_email' => $to,
                'betreff' => $fullSubject,
                'status' => $status,
                'fehlermeldung' => $error,
            ]);
        }

        flash(
            $failed === 0 ? 'success' : 'error',
            $failed === 0
                ? 'Deine Nachricht ist beim Orga-Team.'
                : sprintf('Teilweise fehlgeschlagen: %d gesendet, %d nicht.', $sent, $failed)
        );
        Redirect::to('/account');
    }

    /** @return list<string> Empfänger-Mailadressen */
    private function recipients(): array
    {
        $fixed = $this->settings->orgaContactAddress();
        if ($fixed !== '') {
            return [$fixed];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (array $u): string => trim((string) $u['email']),
            $this->users->activeByRoleNames($this->settings->orgaContactRoles())
        ))));
    }

    private function targetDescription(): string
    {
        if ($this->settings->orgaContactAddress() !== '') {
            return 'Geht an die hinterlegte Orga-Adresse.';
        }

        return $this->recipients() === []
            ? 'Aktuell ist noch kein Orga-Team hinterlegt – bitte an eine:n Admin wenden.'
            : 'Geht ans gesamte Orga-Team.';
    }
}

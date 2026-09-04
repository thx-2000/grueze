<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\ContactRepository;
use App\Repositories\LogRepository;
use App\Repositories\SentMailRepository;
use App\Services\MailComposer;
use App\Services\MailService;
use App\Support\Redirect;

/**
 * „Erhaltene Mails": Wer einen verknüpften Kontakt hat, sieht die Serien-Mails
 * wieder, die an ihn gingen – aufbereitet mit aufgelöster Anrede – und kann
 * sich eine davon erneut ans eigene Postfach schicken lassen.
 */
final class ReceivedMailController extends BaseController
{
    /** Mindestabstand zwischen zwei „nochmal an mich"-Aktionen (Sekunden). */
    private const RESEND_COOLDOWN = 30;

    public function __construct(
        \App\Core\Auth $auth,
        private SentMailRepository $sentMails,
        private ContactRepository $contacts,
        private MailService $mailer,
        private MailComposer $composer,
        private LogRepository $logs,
    ) {
        parent::__construct($auth);
    }

    public function index(): void
    {
        $this->requireAuth();
        $contactId = (int) ($this->auth->user()['contact_id'] ?? 0);

        $this->render('mail/received-list', [
            'linked' => $contactId > 0,
            'entries' => $contactId > 0 ? $this->sentMails->forContact($contactId, 150) : [],
        ]);
    }

    public function show(Request $request): void
    {
        $this->requireAuth();
        $contactId = (int) ($this->auth->user()['contact_id'] ?? 0);
        $contact = $contactId > 0 ? $this->contacts->find($contactId) : null;
        $entry = $contact !== null ? $this->sentMails->findForContact((int) $request->input('id'), $contactId) : null;
        if ($entry === null) {
            flash('error', 'Diese Nachricht wurde nicht gefunden.');
            Redirect::to('/meine-nachrichten');
        }

        [$subject, $body] = $this->renderFor($entry, $contact);

        $this->render('mail/received-detail', [
            'entry' => $entry,
            'renderedSubject' => $subject,
            'renderedBody' => $body,
            'cooldownActive' => $this->cooldownRemaining() > 0,
        ]);
    }

    public function resendToSelf(Request $request): void
    {
        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));

        $user = $this->auth->user();
        $contactId = (int) ($user['contact_id'] ?? 0);
        $contact = $contactId > 0 ? $this->contacts->find($contactId) : null;
        $entry = $contact !== null ? $this->sentMails->findForContact((int) $request->input('id'), $contactId) : null;
        if ($entry === null) {
            flash('error', 'Diese Nachricht wurde nicht gefunden.');
            Redirect::to('/meine-nachrichten');
        }

        $ownEmail = trim((string) ($user['email'] ?? ''));
        if ($ownEmail === '') {
            flash('error', 'Für dein Konto ist keine Mailadresse hinterlegt.');
            Redirect::to('/meine-nachrichten/ansehen?id=' . (int) $entry['id']);
        }

        if ($this->cooldownRemaining() > 0) {
            flash('error', 'Bitte kurz warten, bevor du dir die nächste Nachricht erneut schickst.');
            Redirect::to('/meine-nachrichten/ansehen?id=' . (int) $entry['id']);
        }

        [$subject, $body] = $this->renderFor($entry, $contact);
        $identity = $this->composer->identityByKey((string) $entry['sender_key']);
        $replyTo = $this->composer->replyToByKey((string) $entry['reply_to_key'], false, $user);

        try {
            $this->mailer->sendSystemMail($identity, $ownEmail, $subject, $body, $replyTo['email'] ?? null);
        } catch (\Throwable) {
            flash('error', 'Die Nachricht konnte gerade nicht zugestellt werden. Bitte später erneut versuchen.');
            Redirect::to('/meine-nachrichten/ansehen?id=' . (int) $entry['id']);
        }

        $this->logs->addMailLog([
            'user_id' => (int) $user['id'],
            'contact_id' => $contactId,
            'empfaenger_email' => $ownEmail,
            'betreff' => '[Erneut] ' . $subject,
            'status' => 'gesendet',
            'fehlermeldung' => null,
        ]);
        $_SESSION['inbox_resend_at'] = time();

        flash('success', 'Die Nachricht ist unterwegs zu ' . $ownEmail . '.');
        Redirect::to('/meine-nachrichten/ansehen?id=' . (int) $entry['id']);
    }

    /**
     * Betreff und Text so aufbereiten, wie sie beim Empfänger ankamen:
     * Platzhalter aufgelöst, aktueller Mail-Fuß angehängt.
     *
     * @param array<string,mixed> $entry
     * @param array<string,mixed> $contact
     * @return array{0:string,1:string}
     */
    private function renderFor(array $entry, array $contact): array
    {
        $mode = (string) ($entry['salutation_mode'] ?? 'auto');
        $rawSubject = trim(
            ((string) $entry['subject_prefix'] !== '' ? apply_branding_placeholders((string) $entry['subject_prefix']) . ' ' : '')
            . (string) $entry['subject']
        );
        $subject = $this->mailer->renderMessageTemplate($contact, $rawSubject, $mode);

        $body = $this->mailer->renderMessageTemplate($contact, (string) $entry['body'], $mode);
        $footer = trim($this->composer->mailFooter(false));
        if ($footer !== '') {
            $body = rtrim($body) . "\n\n" . $footer;
        }

        return [$subject, $body];
    }

    private function cooldownRemaining(): int
    {
        $last = (int) ($_SESSION['inbox_resend_at'] ?? 0);

        return max(0, self::RESEND_COOLDOWN - (time() - $last));
    }
}

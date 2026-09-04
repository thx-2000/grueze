<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\ContactRepository;
use App\Repositories\SentMailRepository;
use App\Support\Redirect;

/**
 * „Gesendete Nachrichten": Sende-Berechtigte sehen frühere Serien-Mails (Text,
 * Empfängerkreis, Zeitpunkt, Zustellstatus) wieder und können sie ganz oder an
 * einzelne Personen erneut verschicken.
 */
final class SentMailController extends BaseController
{
    public function __construct(
        \App\Core\Auth $auth,
        private SentMailRepository $sentMails,
        private ContactRepository $contacts,
    ) {
        parent::__construct($auth);
    }

    public function index(): void
    {
        $this->requirePermission('mail.send');

        $this->render('mail/sent-list', [
            'entries' => $this->sentMails->all(150),
            'currentUserId' => (int) ($this->auth->user()['id'] ?? 0),
        ]);
    }

    public function show(Request $request): void
    {
        $this->requirePermission('mail.send');

        $entry = $this->sentMails->find((int) $request->input('id'));
        if ($entry === null) {
            flash('error', 'Diese Nachricht wurde nicht gefunden.');
            Redirect::to('/rundmail/verlauf');
        }

        // Welche Empfänger gibt es im Adressbuch noch (für „erneut senden")?
        $recipients = (array) $entry['recipients'];
        $ids = array_values(array_filter(array_map(static fn (array $r): int => (int) ($r['contact_id'] ?? 0), $recipients)));
        $live = [];
        foreach ($this->contacts->findManyByIds($ids) as $c) {
            $live[(int) $c['id']] = true;
        }

        $this->render('mail/sent-detail', [
            'entry' => $entry,
            'recipients' => $recipients,
            'liveContactIds' => $live,
            'reachableCount' => count($live),
        ]);
    }

    public function resend(Request $request): void
    {
        $this->requirePermission('mail.send');
        Csrf::validate($request->input('_csrf'));

        $entry = $this->sentMails->find((int) $request->input('id'));
        if ($entry === null) {
            flash('error', 'Diese Nachricht wurde nicht gefunden.');
            Redirect::to('/rundmail/verlauf');
        }

        $picked = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->input('recipient_ids', [])),
            static fn (int $id): bool => $id > 0
        )));
        if ($picked === []) {
            // „an alle": alle noch vorhandenen Empfänger aus dem Verlauf.
            $picked = array_values(array_filter(array_map(
                static fn (array $r): int => (int) ($r['contact_id'] ?? 0),
                (array) $entry['recipients']
            )));
        }
        // Nur noch existierende, nicht ruhende Kontakte übernehmen.
        $picked = array_map(static fn (array $c): int => (int) $c['id'], $this->contacts->findManyByIds($picked));

        if ($picked === []) {
            flash('error', 'Von dieser Nachricht ist niemand mehr im Adressbuch – nichts zu verschicken.');
            Redirect::to('/rundmail/verlauf/ansehen?id=' . (int) $entry['id']);
        }

        $_SESSION['mail_draft'] = [
            'contact_ids' => $picked,
            'recipient_mode' => 'selection',
            'subject' => (string) $entry['subject'],
            'message' => (string) $entry['body'],
            'subject_prefix' => (string) $entry['subject_prefix'],
            'salutation_mode' => (string) $entry['salutation_mode'],
            'sender_key' => (string) $entry['sender_key'],
            'reply_to_key' => (string) $entry['reply_to_key'],
        ];
        flash('success', sprintf(
            'Nachricht als Entwurf übernommen – %d %s vorausgewählt. Prüfen und dann versenden.',
            count($picked),
            count($picked) === 1 ? 'Person' : 'Personen'
        ));
        Redirect::to('/rundmail');
    }
}

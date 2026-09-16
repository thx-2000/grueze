<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Repositories\AnnouncementRepository;
use App\Repositories\ContactRepository;
use App\Repositories\EventRepository;
use App\Repositories\GroupRepository;
use App\Repositories\TagRepository;

/**
 * Startseite – rollenspezifisch:
 *  - Verwaltung (Admin/Orga): „Steht an" (Abstimmungen mit fehlenden
 *    Rückmeldungen, Frist zuerst; danach Datenlücken) + Schnellaktionen.
 *  - Mitglied: eigene offene Abstimmungen + „Meine Daten" / „Orga-Team".
 *  - Gruppenleitung: zusätzlich „Deine Gruppen" (offene Beitrittsanfragen).
 *  - Geburtstage der Woche für alle, die das Feld sehen dürfen.
 */
final class StartController extends BaseController
{
    public function __construct(
        Auth $auth,
        private ContactRepository $contacts,
        private EventRepository $events,
        private GroupRepository $groups,
        private AnnouncementRepository $announcements,
        private TagRepository $tags,
    ) {
        parent::__construct($auth);
    }

    public function index(): void
    {
        $this->requireAuth();

        $canManage = $this->auth->can('contacts.manage');
        $canEvents = $this->auth->can('events.manage');
        $contactId = (int) ($this->auth->user()['contact_id'] ?? 0);

        $stats = $canManage
            ? $this->contacts->stats()
            : ['total' => 0, 'without_email' => 0, 'without_phone' => 0];

        // „Steht an": erst Abstimmungen mit offener Rückmeldung (Frist zuerst –
        // die Repo-Query sortiert schon danach), dann die Datenlücken.
        $board = [];
        if ($canEvents) {
            foreach ($this->events->openWithPendingResponses() as $ev) {
                $deadline = trim((string) ($ev['closes_at'] ?? ''));
                $board[] = [
                    'href' => url('/abstimmungen/detail?id=' . $ev['id']),
                    'label' => $ev['title'],
                    'meta' => $ev['answered_count'] . '/' . $ev['participant_count'] . ' geantwortet'
                        . ($deadline !== '' ? ' · Frist ' . time_until_hint($deadline) : ''),
                    'urgent' => $deadline !== '' && strtotime($deadline) !== false
                        && strtotime($deadline) - time() < 3 * 86400,
                ];
            }
        }
        if ($canManage && ($stats['without_email'] ?? 0) > 0) {
            $board[] = [
                'href' => url('/vollstaendigkeit?which=email'),
                'label' => 'Personen ohne Mailadresse',
                'meta' => (int) $stats['without_email'] . ' offen – Lücken schließen',
                'urgent' => false,
            ];
        }
        if ($canManage && ($stats['without_phone'] ?? 0) > 0) {
            $board[] = [
                'href' => url('/vollstaendigkeit?which=phone'),
                'label' => 'Personen ohne Handynummer',
                'meta' => (int) $stats['without_phone'] . ' offen',
                'urgent' => false,
            ];
        }

        // Mitglieder-Sicht: eigene, noch offene Abstimmungen.
        $myOpenVotes = [];
        if (!$canEvents && $contactId > 0) {
            foreach ($this->events->openEventsForContact($contactId) as $ev) {
                if (($ev['status'] ?? '') === 'open') {
                    $myOpenVotes[] = $ev;
                }
            }
        }

        // Gruppenleitung: Gruppen, die ich leite (mit offenen Beitrittsanfragen).
        // Dieselbe Gruppenliste liefert gleich auch die Gruppen-IDs für den
        // Sichtbarkeits-Abgleich der Ankündigungen mit – keine Zweitabfrage.
        $myGroups = $contactId > 0 ? $this->groups->forContact($contactId) : [];
        $leadGroups = array_values(array_filter(
            $myGroups,
            static fn (array $g): bool => ($g['my_role'] ?? 'member') === 'lead'
        ));

        $unreadAnnouncements = [];
        if ($contactId > 0) {
            $groupIds = array_map(static fn (array $g): int => (int) $g['id'], $myGroups);
            $tagIds = $this->tags->tagIdsForContact($contactId);
            $unreadAnnouncements = $this->announcements->unreadFor($contactId, $groupIds, $tagIds);
        }

        $this->render('start/index', [
            'stats' => $stats,
            'showBoard' => $canManage || $canEvents,
            'board' => $board,
            'myOpenVotes' => $myOpenVotes,
            'leadGroups' => $leadGroups,
            'unreadAnnouncements' => $unreadAnnouncements,
            'birthdays' => can_view_contact_field('birthday') ? $this->contacts->upcomingBirthdays(7) : [],
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Repositories\ContactRepository;
use App\Repositories\EventRepository;

final class StartController extends BaseController
{
    public function __construct(
        Auth $auth,
        private ContactRepository $contacts,
        private EventRepository $events,
    ) {
        parent::__construct($auth);
    }

    public function index(): void
    {
        $this->requireAuth();

        $birthdays = can_view_contact_field('birthday')
            ? $this->contacts->upcomingBirthdays(7)
            : [];
        $pendingEvents = $this->auth->can('events.manage')
            ? $this->events->openWithPendingResponses()
            : [];

        $this->render('start/index', [
            'stats' => $this->contacts->stats(),
            'birthdays' => $birthdays,
            'pendingEvents' => $pendingEvents,
        ]);
    }
}

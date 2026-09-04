<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Repositories\ContactRepository;
use App\Repositories\UserRepository;

final class SearchController extends BaseController
{
    public function __construct(
        \App\Core\Auth $auth,
        private ContactRepository $contacts,
        private UserRepository $users
    ) {
        parent::__construct($auth);
    }

    public function index(Request $request): void
    {
        $this->requireAuth();

        $query = trim((string) $request->input('q', ''));
        $contactResults = $query === '' ? [] : $this->contacts->globalSearch($query);
        $userResults = $query !== '' && $this->auth->can('users.manage')
            ? $this->users->search($query)
            : [];

        $this->render('search/index', [
            'query' => $query,
            'contactResults' => $contactResults,
            'userResults' => $userResults,
            'signalHint' => $query === ''
                ? 'Globale Suche'
                : sprintf('%d Kontakte, %d Zugänge', count($contactResults), count($userResults)),
        ]);
    }
}

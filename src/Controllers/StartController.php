<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Repositories\ContactRepository;

final class StartController extends BaseController
{
    public function __construct(Auth $auth, private ContactRepository $contacts)
    {
        parent::__construct($auth);
    }

    public function index(): void
    {
        $this->requireAuth();

        $this->render('start/index', [
            'stats' => $this->contacts->stats(),
        ]);
    }
}

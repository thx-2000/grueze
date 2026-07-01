<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\LogRepository;

final class LogController extends BaseController
{
    public function __construct(\App\Core\Auth $auth, private LogRepository $logs)
    {
        parent::__construct($auth);
    }

    public function audit(): void
    {
        $this->requirePermission('audit.view');
        $this->render('logs/audit', ['entries' => $this->logs->auditEntries()]);
    }

    public function mail(): void
    {
        $this->requirePermission('mail.view_log');
        $this->render('logs/mail', ['entries' => $this->logs->mailEntries()]);
    }
}


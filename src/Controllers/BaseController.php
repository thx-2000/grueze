<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
use App\Support\Redirect;
use RuntimeException;

abstract class BaseController
{
    public function __construct(protected Auth $auth)
    {
    }

    protected function render(string $template, array $data = []): void
    {
        View::render($template, array_merge($data, [
            'currentUser' => $this->auth->user(),
            'originalUser' => $this->auth->originalUser(),
            'isImpersonating' => $this->auth->isImpersonating(),
            'csrfToken' => Csrf::token(),
        ]));
    }

    protected function requireAuth(): void
    {
        if (!$this->auth->check()) {
            flash('error', 'Bitte zuerst anmelden.');
            Redirect::to('/login');
        }
    }

    protected function requirePermission(string $permission): void
    {
        $this->requireAuth();
        if (!$this->auth->can($permission)) {
            throw new RuntimeException('Für diese Aktion fehlt die Berechtigung.');
        }
    }
}

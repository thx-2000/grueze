<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\TagRepository;
use App\Support\Redirect;

final class TagController extends BaseController
{
    public function __construct(\App\Core\Auth $auth, private TagRepository $tags)
    {
        parent::__construct($auth);
    }

    public function store(Request $request): void
    {
        $this->requirePermission('categories.manage');
        Csrf::validate($request->input('_csrf'));
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            flash('error', 'Bitte einen Tag-Namen angeben.');
            Redirect::to('/kontakte');
        }

        $this->tags->create($name);
        flash('success', 'Tag angelegt.');
        Redirect::to('/kontakte');
    }
}

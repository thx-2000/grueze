<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\CategoryRepository;
use App\Support\Redirect;

final class CategoryController extends BaseController
{
    public function __construct(\App\Core\Auth $auth, private CategoryRepository $categories)
    {
        parent::__construct($auth);
    }

    public function store(Request $request): void
    {
        $this->requirePermission('categories.manage');
        Csrf::validate($request->input('_csrf'));
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            flash('error', 'Bitte einen Kategorienamen angeben.');
            Redirect::to('/kontakte');
        }

        $this->categories->create($name);
        flash('success', 'Kategorie angelegt.');
        Redirect::to('/kontakte');
    }
}


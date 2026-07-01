<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\UserRepository;
use App\Services\Validator;
use App\Support\Redirect;

final class UserController extends BaseController
{
    public function __construct(\App\Core\Auth $auth, private UserRepository $users)
    {
        parent::__construct($auth);
    }

    public function index(): void
    {
        $this->requirePermission('users.manage');
        $this->render('users/index', [
            'users' => $this->users->all(),
            'roles' => $this->users->roles(),
        ]);
    }

    public function store(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));
        $data = [
            'name' => trim((string) $request->input('name')),
            'email' => trim((string) $request->input('email')),
            'role_id' => (int) $request->input('role_id'),
        ];

        $errors = Validator::validate($data, [
            'name' => ['required'],
            'email' => ['required', 'email'],
        ]);

        if ($errors !== []) {
            $_SESSION['_errors'] = $errors;
            Redirect::to('/users');
        }

        $password = $this->generatePassword();
        $this->users->create([
            ...$data,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'is_active' => 1,
        ]);

        flash('success', 'Nutzer angelegt. Erstpasswort: ' . $password);
        Redirect::to('/users');
    }

    private function generatePassword(): string
    {
        return substr(strtr(base64_encode(random_bytes(12)), '+/', 'AZ'), 0, 16);
    }
}


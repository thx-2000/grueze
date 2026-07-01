<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\UserRepository;
use App\Services\Validator;
use App\Support\Redirect;

final class SetupController extends BaseController
{
    public function __construct(\App\Core\Auth $auth, private UserRepository $users)
    {
        parent::__construct($auth);
    }

    public function showAdminForm(): void
    {
        if ($this->users->adminExists()) {
            flash('error', 'Es existiert bereits ein Admin-Konto.');
            Redirect::to('/login');
        }

        $this->render('setup/admin', [
            'hasAdmin' => false,
        ]);
    }

    public function storeAdmin(Request $request): void
    {
        if ($this->users->adminExists()) {
            flash('error', 'Es existiert bereits ein Admin-Konto.');
            Redirect::to('/login');
        }

        Csrf::validate($request->input('_csrf'));

        $data = [
            'name' => trim((string) $request->input('name')),
            'email' => trim((string) $request->input('email')),
            'password' => (string) $request->input('password'),
        ];

        $errors = Validator::validate($data, [
            'name' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (mb_strlen($data['password']) < 12) {
            $errors['password'] = 'Das Passwort muss mindestens 12 Zeichen lang sein.';
        }

        if ($errors !== []) {
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old'] = [
                'name' => $data['name'],
                'email' => $data['email'],
            ];
            Redirect::to('/setup/admin');
        }

        $adminRoleId = $this->users->findRoleIdByName('admin');
        if ($adminRoleId === null) {
            throw new \RuntimeException('Die Admin-Rolle wurde in der Datenbank nicht gefunden.');
        }

        $this->users->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role_id' => $adminRoleId,
            'is_active' => 1,
        ]);

        flash('success', 'Das erste Admin-Konto wurde angelegt. Du kannst dich jetzt anmelden.');
        Redirect::to('/login');
    }
}


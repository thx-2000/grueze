<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\RecipientListRepository;
use App\Services\MailRecipientResolver;
use App\Support\JsonResponse;
use App\Support\Redirect;

/**
 * Gespeicherte Empfängerlisten: anlegen (per fetch() aus dem Schreiben-Dialog,
 * Antwort JSON), umbenennen, löschen. Alles braucht `mail.send`.
 */
final class RecipientListController extends BaseController
{
    public function __construct(
        \App\Core\Auth $auth,
        private RecipientListRepository $recipientLists,
        private MailRecipientResolver $resolver,
    ) {
        parent::__construct($auth);
    }

    /** Wird per fetch() vom Schreiben-Dialog aufgerufen; antwortet JSON. */
    public function save(Request $request): void
    {
        $this->requirePermission('mail.send');
        Csrf::validate($request->input('_csrf'));

        $name = trim((string) $request->input('name'));
        $ids = $request->input('recipient_mode') !== null
            ? $this->resolver->resolve($request)
            : array_values(array_unique(array_filter(
                array_map('intval', (array) $request->input('contact_ids', [])),
                static fn (int $n): bool => $n > 0
            )));

        if ($name === '') {
            JsonResponse::send(['ok' => false, 'error' => 'Bitte einen Namen für die Liste angeben.'], 422);
        }
        if ($ids === []) {
            JsonResponse::send(['ok' => false, 'error' => 'Keine Empfänger zum Speichern.'], 422);
        }
        if ($this->recipientLists->nameExists($name)) {
            JsonResponse::send(['ok' => false, 'error' => 'Eine Liste mit diesem Namen gibt es schon.'], 409);
        }

        $user = $this->auth->user();
        $id = $this->recipientLists->create($name, $ids, isset($user['id']) ? (int) $user['id'] : null);

        JsonResponse::send(['ok' => true, 'name' => $name, 'count' => count($ids), 'id' => $id]);
    }

    public function rename(Request $request): void
    {
        $this->requirePermission('mail.send');
        Csrf::validate($request->input('_csrf'));

        $id = (int) $request->input('id');
        $name = trim((string) $request->input('name'));
        if ($name === '' || $this->recipientLists->find($id) === null) {
            flash('error', 'Liste oder Name fehlt.');
            Redirect::to('/rundmail');
        }
        if ($this->recipientLists->nameExists($name, $id)) {
            flash('error', 'Eine Liste mit diesem Namen gibt es schon.');
            Redirect::to('/rundmail');
        }

        $this->recipientLists->rename($id, $name);
        flash('success', 'Liste umbenannt.');
        Redirect::to('/rundmail');
    }

    public function delete(Request $request): void
    {
        $this->requirePermission('mail.send');
        Csrf::validate($request->input('_csrf'));

        $this->recipientLists->delete((int) $request->input('id'));
        flash('success', 'Liste gelöscht.');
        Redirect::to('/rundmail');
    }
}

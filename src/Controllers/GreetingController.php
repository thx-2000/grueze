<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\CategoryRepository;
use App\Repositories\ContactRepository;
use App\Repositories\GreetingRepository;
use App\Repositories\SettingRepository;
use App\Repositories\TagRepository;
use App\Support\Redirect;

/**
 * Grüße-Pool: Standardtexte pflegen (Verwaltung) und Weihnachtsgrüße als
 * gemischten Serienversand vorbereiten. Der eigentliche Versand läuft über
 * MailController::sendGreetings.
 */
final class GreetingController extends BaseController
{
    private const OCCASIONS = ['birthday', 'christmas'];

    public function __construct(
        \App\Core\Auth $auth,
        private GreetingRepository $greetings,
        private ContactRepository $contacts,
        private CategoryRepository $categories,
        private TagRepository $tags,
        private SettingRepository $settings,
    ) {
        parent::__construct($auth);
    }

    // ------------------------------------------------------------- Verwaltung

    public function manage(): void
    {
        $this->requirePermission('settings.manage');

        $this->render('settings/greetings', [
            'birthday' => $this->greetings->byOccasion('birthday'),
            'christmas' => $this->greetings->byOccasion('christmas'),
        ]);
    }

    public function store(Request $request): void
    {
        $this->requirePermission('settings.manage');
        Csrf::validate($request->input('_csrf'));

        $occasion = (string) $request->input('occasion');
        $text = trim((string) $request->input('text'));
        if (!in_array($occasion, self::OCCASIONS, true) || $text === '') {
            flash('error', 'Bitte einen Text eingeben.');
            Redirect::to('/verwaltung/gruesse');
        }

        $this->greetings->create($occasion, $text);
        flash('success', 'Gruß hinzugefügt.');
        Redirect::to('/verwaltung/gruesse');
    }

    public function update(Request $request): void
    {
        $this->requirePermission('settings.manage');
        Csrf::validate($request->input('_csrf'));

        $id = (int) $request->input('id');
        $text = trim((string) $request->input('text'));
        if ($this->greetings->find($id) === null || $text === '') {
            flash('error', 'Text fehlt.');
            Redirect::to('/verwaltung/gruesse');
        }

        $this->greetings->update($id, $text, $request->input('is_active') !== null);
        flash('success', 'Gruß gespeichert.');
        Redirect::to('/verwaltung/gruesse');
    }

    public function delete(Request $request): void
    {
        $this->requirePermission('settings.manage');
        Csrf::validate($request->input('_csrf'));
        $this->greetings->delete((int) $request->input('id'));
        flash('success', 'Gruß gelöscht.');
        Redirect::to('/verwaltung/gruesse');
    }

    // ------------------------------------------------------ Weihnachtsgrüße

    public function christmasForm(): void
    {
        $this->requirePermission('mail.send');

        $this->render('greetings/send', [
            'categories' => $this->categories->all(),
            'tags' => $this->tags->all(),
            'totalWithEmail' => count($this->contacts->recipientIds([])),
            'poolSize' => count($this->greetings->activeTexts('christmas')),
            'identities' => $this->settings->mailIdentities(),
            'replyToOptions' => $this->settings->mailReplyToOptions(),
        ]);
    }

    public function christmasPreview(Request $request): void
    {
        $this->requirePermission('mail.send');
        Csrf::validate($request->input('_csrf'));

        if ($this->greetings->activeTexts('christmas') === []) {
            flash('error', 'Es sind keine Weihnachts-Texte im Pool. Bitte zuerst welche anlegen.');
            Redirect::to('/verwaltung/gruesse');
        }

        $contactIds = $this->resolveContactIds($request);
        $contacts = $this->contacts->findManyByIds($contactIds);
        if ($contacts === []) {
            flash('error', 'In diesem Kreis hat niemand eine Mailadresse.');
            Redirect::to('/gruesse/weihnachten');
        }

        $assignments = $this->greetings->assign(
            array_map(static fn (array $c): int => (int) $c['id'], $contacts),
            'christmas'
        );

        $rows = [];
        foreach ($contacts as $contact) {
            $rows[] = [
                'id' => (int) $contact['id'],
                'name' => trim($contact['vorname'] . ' ' . $contact['nachname']),
                'email' => (string) ($contact['emails'][0]['email'] ?? ''),
                'text' => (string) ($assignments[(int) $contact['id']] ?? ''),
            ];
        }

        $_SESSION['greeting_batch'] = [
            'occasion' => 'christmas',
            'subject' => trim((string) $request->input('subject')) ?: 'Frohe Weihnachten!',
            'sender_key' => (string) $request->input('sender_key'),
            'reply_to_key' => (string) $request->input('reply_to_key'),
            'assignments' => $assignments,
        ];

        $this->render('greetings/preview', [
            'rows' => $rows,
            'subject' => $_SESSION['greeting_batch']['subject'],
            'params' => [
                'recipient_mode' => (string) $request->input('recipient_mode', 'all'),
                'category_id' => (string) $request->input('category_id', ''),
                'tag_ids' => array_map('intval', (array) $request->input('tag_ids', [])),
                'subject' => $_SESSION['greeting_batch']['subject'],
                'sender_key' => $_SESSION['greeting_batch']['sender_key'],
                'reply_to_key' => $_SESSION['greeting_batch']['reply_to_key'],
            ],
        ]);
    }

    /** @return list<int> */
    private function resolveContactIds(Request $request): array
    {
        return match ((string) $request->input('recipient_mode', 'all')) {
            'category' => (string) $request->input('category_id', '') !== ''
                ? $this->contacts->recipientIds(['category_id' => (string) $request->input('category_id')])
                : [],
            'tags' => ($t = array_values(array_filter(array_map('intval', (array) $request->input('tag_ids', []))))) !== []
                ? $this->contacts->recipientIds(['tag_ids' => $t])
                : [],
            default => $this->contacts->recipientIds([]),
        };
    }
}

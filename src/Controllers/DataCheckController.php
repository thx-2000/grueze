<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\ContactRepository;
use App\Repositories\DataCheckRepository;
use App\Repositories\LogRepository;
use App\Services\Validator;
use App\Support\ContactInput;
use App\Support\Redirect;

/**
 * Daten-Check-Link: Die Verwaltung erzeugt auf der Kontaktseite einen Link, den
 * die betreffende Person ohne Login öffnen kann, um ihre eigenen Stammdaten,
 * die Adresse und die Kontaktwege zu prüfen und zu korrigieren. Kategorie,
 * Tags, Notizen und ein verknüpfter Login bleiben unangetastet – wie beim
 * eingeloggten Selbst-Service „Mein Eintrag".
 */
final class DataCheckController extends BaseController
{
    public function __construct(
        \App\Core\Auth $auth,
        private ContactRepository $contacts,
        private DataCheckRepository $checks,
        private LogRepository $logs,
    ) {
        parent::__construct($auth);
    }

    // ---------------------------------------------------------- Verwaltung

    /** „Daten-Check-Link erzeugen" von der Kontaktseite aus. */
    public function createLink(Request $request): void
    {
        $this->requirePermission('contacts.manage');
        Csrf::validate($request->input('_csrf'));

        $contactId = (int) $request->input('id');
        $contact = $this->contacts->find($contactId);
        if (!$contact || !empty($contact['archived_at']) || !empty($contact['deleted_at'])) {
            flash('error', 'Kontakt nicht gefunden.');
            Redirect::to('/kontakte');
        }

        $days = (int) config('contacts.data_check_days', 30);
        $token = $this->checks->create($contactId, (int) $this->auth->user()['id'], $days);

        $this->logs->addAudit(
            (int) $this->auth->user()['id'],
            $contactId,
            'updated',
            'Daten-Check-Link erzeugt (gültig ' . $days . ' Tage).'
        );

        // Token im Pfad, nicht im Query – landet so nicht in Server-Logs,
        // Browser-Verlauf oder Referrer-Headern (wie beim Passwort-Reset).
        $_SESSION['data_check_link'] = [
            'contact_id' => $contactId,
            'url' => url('/meine-daten/' . rawurlencode($token)),
        ];
        flash('success', 'Der Daten-Check-Link ist erzeugt – zum Kopieren und Weitergeben unten.');
        Redirect::to('/contacts/edit?id=' . $contactId);
    }

    public function revokeLink(Request $request): void
    {
        $this->requirePermission('contacts.manage');
        Csrf::validate($request->input('_csrf'));
        $contactId = (int) $request->input('id');
        $this->checks->revokeForContact($contactId);
        $this->logs->addAudit((int) $this->auth->user()['id'], $contactId, 'updated', 'Daten-Check-Link zurückgezogen.');
        flash('success', 'Der Link ist jetzt ungültig.');
        Redirect::to('/contacts/edit?id=' . $contactId);
    }

    // -------------------------------------------------------------- Public

    public function show(Request $request, string $token = ''): void
    {
        $token = $this->resolveToken($request, $token);
        $check = $this->checks->findValidByToken($token);
        if ($check === null) {
            $this->render('contacts/data-check', ['invalid' => true]);

            return;
        }

        $contact = $this->contacts->find((int) $check['contact_id']);
        if ($contact === null) {
            $this->render('contacts/data-check', ['invalid' => true]);

            return;
        }

        $this->render('contacts/data-check', [
            'invalid' => false,
            'token' => $token,
            'contact' => $contact,
            'phoneLabels' => config('defaults.phone_labels', []),
            'expiresAt' => (string) $check['expires_at'],
            'saved' => $request->input('gespeichert') === '1',
        ]);
    }

    public function save(Request $request, string $token = ''): void
    {
        Csrf::validate($request->input('_csrf'));

        // Schutz vor versehentlichem/skript-getriebenem Mehrfach-Absenden.
        $now = time();
        if (($now - (int) ($_SESSION['data_check_save_at'] ?? 0)) < 10) {
            flash('error', 'Kurz warten – die letzte Änderung wird noch gespeichert.');
            Redirect::to('/meine-daten/' . rawurlencode($this->resolveToken($request, $token)));
        }
        $_SESSION['data_check_save_at'] = $now;

        $token = $this->resolveToken($request, $token);
        $check = $this->checks->findValidByToken($token);
        if ($check === null) {
            $this->render('contacts/data-check', ['invalid' => true]);

            return;
        }

        $contactId = (int) $check['contact_id'];
        $existing = $this->contacts->find($contactId);
        if ($existing === null) {
            $this->render('contacts/data-check', ['invalid' => true]);

            return;
        }

        $data = ContactInput::selfServiceFields($request, $existing);
        $errors = Validator::validate($data, [
            'vorname' => ['required'],
            'nachname' => ['required'],
        ]);
        if ($errors !== []) {
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old'] = $request->all();
            Redirect::to('/meine-daten/' . rawurlencode($token));
        }

        // Handelnde Person ist nicht eingeloggt – die Aktion wird der Person
        // zugeschrieben, die den Link erzeugt hat (sonst der letzten
        // bearbeitenden Person), und im Text klar als Selbst-Check markiert.
        $actorId = (int) ($check['created_by'] ?: ($existing['updated_by'] ?? $existing['created_by']));
        $this->contacts->update($contactId, $data, $actorId);
        $changes = $this->diff($existing, $data);
        $this->checks->markUsed((int) $check['id']);

        $summary = $changes === []
            ? 'Daten-Check-Link geöffnet, keine Änderung.'
            : 'Über den Daten-Check-Link selbst korrigiert: ' . implode(', ', array_keys($changes)) . '.';
        $this->logs->addAudit($actorId, $contactId, 'updated', $summary, $changes);

        Redirect::to('/meine-daten/' . rawurlencode($token) . '?gespeichert=1');
    }

    // -------------------------------------------------------------- intern

    /**
     * Token bevorzugt aus dem Pfad; `?token=`/POST-Feld nur als Rückfall für
     * Links, die vor der Umstellung verschickt wurden.
     */
    private function resolveToken(Request $request, string $fromPath): string
    {
        $fromPath = trim($fromPath);

        return $fromPath !== '' ? $fromPath : trim((string) $request->input('token', ''));
    }

    /**
     * @param array<string,mixed> $before
     * @param array<string,mixed> $after
     * @return array<string,array{from:string,to:string}>
     */
    private function diff(array $before, array $after): array
    {
        $flat = static function (array $rows, string $key): string {
            $parts = [];
            foreach ($rows as $row) {
                $label = trim((string) ($row['label'] ?? ''));
                $parts[] = ($label !== '' ? $label . ': ' : '') . (string) ($row[$key] ?? '');
            }
            sort($parts);

            return implode(', ', $parts);
        };

        $pairs = [
            'Vorname' => [(string) ($before['vorname'] ?? ''), (string) $after['vorname']],
            'Nachname' => [(string) ($before['nachname'] ?? ''), (string) $after['nachname']],
            'Geburtsname' => [(string) ($before['geburtsname'] ?? ''), (string) $after['geburtsname']],
            'Anrede' => [(string) ($before['anrede'] ?? ''), (string) ($after['anrede'] ?? '')],
            'Geburtstag' => [(string) ($before['geburtstag'] ?? ''), (string) $after['geburtstag']],
            'Beruf/Tätigkeit' => [(string) ($before['beruf'] ?? ''), (string) ($after['beruf'] ?? '')],
            'Webseite' => [(string) ($before['webseite'] ?? ''), (string) ($after['webseite'] ?? '')],
            'Straße' => [(string) ($before['strasse'] ?? ''), (string) $after['strasse']],
            'PLZ' => [(string) ($before['plz'] ?? ''), (string) $after['plz']],
            'Ort' => [(string) ($before['ort'] ?? ''), (string) $after['ort']],
            'Land' => [(string) ($before['land'] ?? ''), (string) $after['land']],
            'E-Mail' => [$flat($before['emails'] ?? [], 'email'), $flat($after['emails'], 'email')],
            'Telefon' => [$flat($before['phones'] ?? [], 'phone'), $flat($after['phones'], 'phone')],
        ];

        $changes = [];
        foreach ($pairs as $label => [$from, $to]) {
            if (trim($from) !== trim($to)) {
                $changes[$label] = ['from' => trim($from) === '' ? '—' : trim($from), 'to' => trim($to) === '' ? '—' : trim($to)];
            }
        }

        return $changes;
    }
}

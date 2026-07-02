<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\SettingRepository;
use App\Support\Redirect;

final class SettingsController extends BaseController
{
    public function __construct(
        \App\Core\Auth $auth,
        private SettingRepository $settings
    ) {
        parent::__construct($auth);
    }

    public function mailFooter(): void
    {
        $this->requirePermission('settings.manage');

        $this->render('settings/mail-footer', [
            'mailFooter' => old('mail_footer', $this->settings->mailFooter()),
            'defaultMailFooter' => $this->settings->defaultMailFooter(),
            'subjectPrefixes' => old('subject_prefixes', $this->settings->subjectPrefixesText()),
            'defaultSubjectPrefix' => $this->settings->defaultSubjectPrefix(),
            'defaultSubjectPrefixesText' => $this->settings->defaultSubjectPrefixesText(),
        ]);
    }

    public function updateMailFooter(Request $request): void
    {
        $this->requirePermission('settings.manage');
        Csrf::validate($request->input('_csrf'));

        $mailFooter = $request->input('use_default') === '1'
            ? $this->settings->defaultMailFooter()
            : trim((string) $request->input('mail_footer'));
        $subjectPrefixes = $request->input('use_default') === '1'
            ? $this->settings->defaultSubjectPrefixesText()
            : trim((string) $request->input('subject_prefixes'));

        if ($mailFooter === '') {
            $_SESSION['_errors'] = ['mail_footer' => 'Bitte einen Mail-Fuß hinterlegen oder den Standardtext einsetzen.'];
            $_SESSION['_old'] = [
                'mail_footer' => (string) $request->input('mail_footer'),
                'subject_prefixes' => (string) $request->input('subject_prefixes'),
            ];
            Redirect::to('/settings/mail-footer');
        }

        if ($subjectPrefixes === '') {
            $_SESSION['_errors'] = ['subject_prefixes' => 'Bitte mindestens einen Betreff-Präfix hinterlegen.'];
            $_SESSION['_old'] = [
                'mail_footer' => (string) $request->input('mail_footer'),
                'subject_prefixes' => (string) $request->input('subject_prefixes'),
            ];
            Redirect::to('/settings/mail-footer');
        }

        $this->settings->set('mail_footer', $mailFooter);
        $this->settings->set('subject_prefixes', $subjectPrefixes);
        flash('success', 'Die Mail-Einstellungen wurden gespeichert.');
        Redirect::to('/settings/mail-footer');
    }
}

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
        ]);
    }

    public function updateMailFooter(Request $request): void
    {
        $this->requirePermission('settings.manage');
        Csrf::validate($request->input('_csrf'));

        $mailFooter = $request->input('use_default') === '1'
            ? $this->settings->defaultMailFooter()
            : trim((string) $request->input('mail_footer'));

        if ($mailFooter === '') {
            $_SESSION['_errors'] = ['mail_footer' => 'Bitte einen Mail-Fuß hinterlegen oder den Standardtext einsetzen.'];
            $_SESSION['_old'] = ['mail_footer' => (string) $request->input('mail_footer')];
            Redirect::to('/settings/mail-footer');
        }

        $this->settings->set('mail_footer', $mailFooter);
        flash('success', 'Der Mail-Fuß wurde gespeichert.');
        Redirect::to('/settings/mail-footer');
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\SettingRepository;
use App\Services\UploadService;
use App\Support\Redirect;

final class SettingsController extends BaseController
{
    public function __construct(
        \App\Core\Auth $auth,
        private SettingRepository $settings,
        private UploadService $uploads
    ) {
        parent::__construct($auth);
    }

    public function branding(): void
    {
        $this->requirePermission('users.manage');

        $this->render('settings/branding', [
            'branding' => array_merge($this->settings->branding(), (array) ($_SESSION['_old'] ?? [])),
        ]);
    }

    public function updateBranding(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));

        $defaults = $this->settings->brandingDefaults();
        $incoming = [];
        foreach (array_keys($defaults) as $key) {
            if ($key === 'branding_logo_path') {
                continue;
            }

            $incoming[$key] = trim((string) $request->input($key, $defaults[$key]));
        }

        if ($incoming['branding_app_name'] === '' || $incoming['branding_short_name'] === '') {
            $_SESSION['_errors'] = ['branding' => 'Bitte mindestens Anwendungsname und Kurzname ausfüllen.'];
            $_SESSION['_old'] = $incoming;
            Redirect::to('/settings/branding');
        }

        if (
            $incoming['branding_public_site_url'] !== ''
            && !filter_var($incoming['branding_public_site_url'], FILTER_VALIDATE_URL)
        ) {
            $_SESSION['_errors'] = ['branding_public_site_url' => 'Bitte eine gültige URL für die öffentliche Seite angeben.'];
            $_SESSION['_old'] = $incoming;
            Redirect::to('/settings/branding');
        }

        foreach ($incoming as $key => $value) {
            if (str_starts_with($key, 'branding_color_') && !$this->isValidCssColor($value)) {
                $_SESSION['_errors'] = [$key => 'Bitte einen gültigen Farbwert angeben.'];
                $_SESSION['_old'] = $incoming;
                Redirect::to('/settings/branding');
            }
        }

        $currentBranding = $this->settings->branding();
        $logoPath = $currentBranding['branding_logo_path'] ?? '';
        $logoPath = $this->uploads->storeBrandAsset($request->file('branding_logo'), $logoPath !== '' ? $logoPath : null) ?? '';

        foreach ($incoming as $key => $value) {
            $this->settings->set($key, $value);
        }
        $this->settings->set('branding_logo_path', $logoPath);

        flash('success', 'Branding und Benennungen wurden gespeichert.');
        Redirect::to('/settings/branding');
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
            'mailSettings' => array_merge(
                $this->settings->mailSettings(),
                (array) ($_SESSION['_mail_settings_old'] ?? [])
            ),
        ]);
        unset($_SESSION['_mail_settings_old']);
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

        if ($this->auth->can('users.manage')) {
            $mailSettingsDefaults = $this->settings->mailSettingsDefaults();
            $mailSettingsInput = [];

            foreach (array_keys($mailSettingsDefaults) as $key) {
                $mailSettingsInput[$key] = trim((string) $request->input($key, $mailSettingsDefaults[$key]));
            }

            $currentMailSettings = $this->settings->mailSettings();
            if ($mailSettingsInput['mail_smtp_password'] === '') {
                $mailSettingsInput['mail_smtp_password'] = (string) ($currentMailSettings['mail_smtp_password'] ?? '');
            }
            if ($mailSettingsInput['mail_imap_password'] === '') {
                $mailSettingsInput['mail_imap_password'] = (string) ($currentMailSettings['mail_imap_password'] ?? '');
            }

            if (
                $mailSettingsInput['mail_identity_name'] === ''
                || $mailSettingsInput['mail_identity_email'] === ''
                || $mailSettingsInput['mail_smtp_host'] === ''
                || $mailSettingsInput['mail_smtp_username'] === ''
            ) {
                $_SESSION['_errors'] = ['mail_settings' => 'Bitte die Pflichtfelder für den Mailserver vollständig ausfüllen.'];
                $_SESSION['_old'] = [
                    'mail_footer' => $mailFooter,
                    'subject_prefixes' => $subjectPrefixes,
                ];
                $_SESSION['_mail_settings_old'] = $mailSettingsInput;
                Redirect::to('/settings/mail-footer');
            }

            if (!filter_var($mailSettingsInput['mail_identity_email'], FILTER_VALIDATE_EMAIL)) {
                $_SESSION['_errors'] = ['mail_identity_email' => 'Bitte eine gültige Absender-E-Mail-Adresse angeben.'];
                $_SESSION['_old'] = [
                    'mail_footer' => $mailFooter,
                    'subject_prefixes' => $subjectPrefixes,
                ];
                $_SESSION['_mail_settings_old'] = $mailSettingsInput;
                Redirect::to('/settings/mail-footer');
            }

            if (!filter_var($mailSettingsInput['mail_reply_to_email'], FILTER_VALIDATE_EMAIL)) {
                $_SESSION['_errors'] = ['mail_reply_to_email' => 'Bitte eine gültige Antwort-an-Adresse angeben.'];
                $_SESSION['_old'] = [
                    'mail_footer' => $mailFooter,
                    'subject_prefixes' => $subjectPrefixes,
                ];
                $_SESSION['_mail_settings_old'] = $mailSettingsInput;
                Redirect::to('/settings/mail-footer');
            }

            if (
                $mailSettingsInput['mail_bcc_email'] !== ''
                && !filter_var($mailSettingsInput['mail_bcc_email'], FILTER_VALIDATE_EMAIL)
            ) {
                $_SESSION['_errors'] = ['mail_bcc_email' => 'Bitte eine gültige BCC-E-Mail-Adresse angeben.'];
                $_SESSION['_old'] = [
                    'mail_footer' => $mailFooter,
                    'subject_prefixes' => $subjectPrefixes,
                ];
                $_SESSION['_mail_settings_old'] = $mailSettingsInput;
                Redirect::to('/settings/mail-footer');
            }

            if (
                !ctype_digit($mailSettingsInput['mail_smtp_port'])
                || !ctype_digit($mailSettingsInput['mail_imap_port'])
            ) {
                $_SESSION['_errors'] = ['mail_ports' => 'SMTP- und IMAP-Port müssen numerisch sein.'];
                $_SESSION['_old'] = [
                    'mail_footer' => $mailFooter,
                    'subject_prefixes' => $subjectPrefixes,
                ];
                $_SESSION['_mail_settings_old'] = $mailSettingsInput;
                Redirect::to('/settings/mail-footer');
            }

            $mailSettingsInput['mail_imap_save_sent'] = $request->input('mail_imap_save_sent') === '1' ? '1' : '0';

            foreach ($mailSettingsInput as $key => $value) {
                $this->settings->set($key, $value);
            }
        }

        flash('success', 'Die Mail-Einstellungen wurden gespeichert.');
        Redirect::to('/settings/mail-footer');
    }

    private function isValidCssColor(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        return preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value) === 1
            || preg_match('/^rgba?\(([^)]+)\)$/', $value) === 1;
    }
}

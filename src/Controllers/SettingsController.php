<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\RoleRepository;
use App\Repositories\SettingRepository;
use App\Services\UploadService;
use App\Support\Redirect;

final class SettingsController extends BaseController
{
    public function __construct(
        \App\Core\Auth $auth,
        private SettingRepository $settings,
        private UploadService $uploads,
        private RoleRepository $roles
    ) {
        parent::__construct($auth);
    }

    /** @return array{names: list<string>, configurable: list<string>, labels: array<string,string>} */
    private function roleContext(): array
    {
        $names = [];
        $labels = [];
        foreach ($this->roles->all() as $role) {
            $names[] = (string) $role['name'];
            $labels[(string) $role['name']] = trim((string) ($role['label'] ?? '')) !== ''
                ? (string) $role['label']
                : (string) $role['name'];
        }

        return [
            'names' => $names,
            'configurable' => array_values(array_filter($names, static fn (string $n): bool => $n !== RoleRepository::PROTECTED_NAME)),
            'labels' => $labels,
        ];
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

    public function visibility(): void
    {
        $this->requirePermission('users.manage');
        $roles = $this->roleContext();

        $this->render('settings/visibility', [
            'visibility' => $this->settings->fieldVisibility(),
            'defaults' => $this->settings->fieldVisibilityDefaults(),
            'ownContactVisible' => $this->settings->ownContactAlwaysVisible(),
            'roles' => $roles['names'],
            'roleLabels' => $roles['labels'],
            'fieldLabels' => [
                'address'  => 'Adresse',
                'birthday' => 'Geburtstag',
                'emails'   => 'E-Mail',
                'phones'   => 'Telefon',
                'notes'    => 'Notizen',
                'login'    => 'Login / Rolle',
            ],
        ]);
    }

    public function updateVisibility(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));

        $fields = array_keys($this->settings->fieldVisibilityDefaults());
        $allRoles = $this->roleContext()['names'];
        $submitted = (array) $request->input('visibility', []);

        foreach ($fields as $field) {
            $fieldRoles = array_values(array_intersect((array) ($submitted[$field] ?? []), $allRoles));
            $this->settings->set('security_visibility_' . $field, implode(',', $fieldRoles));
        }

        $this->settings->set('security_own_contact_visible', $request->input('own_contact_visible') === '1' ? '1' : '0');

        flash('success', 'Sichtbarkeits-Einstellungen wurden gespeichert.');
        Redirect::to('/settings/visibility');
    }

    public function permissions(): void
    {
        $this->requirePermission('users.manage');
        $roles = $this->roleContext();

        $this->render('settings/permissions', [
            'matrix' => $this->settings->permissionMatrix(),
            'defaults' => $this->settings->permissionDefaults(),
            'configurableRoles' => $roles['configurable'],
            'roleLabels' => $roles['labels'],
            'permissionGroups' => [
                'Kontakte' => [
                    'contacts.manage'      => 'Kontakte anlegen und bearbeiten',
                    'contacts.delete'      => 'Kontakte löschen',
                    'categories.manage'    => 'Kategorien und Tags verwalten',
                    'contacts.export'      => 'Kontakte als CSV exportieren',
                    'contacts.copy_emails' => 'E-Mail-Adressen kopieren',
                ],
                'Mailing' => [
                    'mail.send'           => 'Sammel-Mailings versenden',
                    'mail.contact_single' => 'Einzelne Person über interne Kontaktfunktion kontaktieren',
                    'mail.view_log'       => 'Versandprotokoll einsehen',
                ],
                'Abstimmungen' => [
                    'events.manage' => 'Abstimmungen anlegen und verwalten',
                ],
                'Termine' => [
                    'announcements.manage' => 'Termine ankündigen (Ansehen ist für alle offen)',
                ],
                'Gruppen' => [
                    'groups.manage' => 'Gruppen anlegen und Mitglieder verwalten',
                ],
                'Galerien' => [
                    'galleries.view' => 'Galerien und Medien ansehen und herunterladen',
                    'galleries.upload' => 'Fotos und Videos in Galerien hochladen (und eigene Uploads bearbeiten)',
                    'galleries.manage' => 'Galerien anlegen, bearbeiten, löschen; fremde Medien verschieben/löschen; Sicherung',
                ],
                'Dokumente' => [
                    'documents.view' => 'Ordner und Dateien ansehen und herunterladen',
                    'documents.upload' => 'Dateien in Ordner hochladen (und eigene Uploads bearbeiten/löschen)',
                    'documents.manage' => 'Ordner anlegen, bearbeiten, löschen; fremde Dateien bearbeiten/löschen',
                ],
                'Orga-Team' => [
                    'orga.contact_target' => 'Bekommt Nachrichten über den „Orga-Team schreiben"-Knopf',
                ],
                'Administration' => [
                    'users.manage'    => 'Zugänge und Admin-Einstellungen verwalten',
                    'audit.view'      => 'Audit-Log einsehen',
                    'settings.manage' => 'Mail-Fuß und Versanddaten bearbeiten',
                ],
            ],
        ]);
    }

    public function updatePermissions(Request $request): void
    {
        $this->requirePermission('users.manage');
        Csrf::validate($request->input('_csrf'));

        $allPermissions = array_keys($this->settings->permissionDefaults());
        $configurableRoles = $this->roleContext()['configurable'];
        $submitted = (array) $request->input('permissions', []);

        foreach ($allPermissions as $permission) {
            $roles = array_values(array_intersect((array) ($submitted[$permission] ?? []), $configurableRoles));
            $storageKey = 'security_permission_' . str_replace('.', '_', $permission);
            $this->settings->set($storageKey, implode(',', $roles));
        }

        flash('success', 'Berechtigungen wurden gespeichert.');
        Redirect::to('/settings/permissions');
    }
}

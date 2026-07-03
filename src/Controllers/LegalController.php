<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\SettingRepository;

final class LegalController extends BaseController
{
    public function __construct(\App\Core\Auth $auth, private SettingRepository $settings)
    {
        parent::__construct($auth);
    }

    public function impressum(): void
    {
        $this->render('legal/impressum', ['content' => $this->settings->legalText('impressum')]);
    }

    public function datenschutz(): void
    {
        $this->render('legal/datenschutz', ['content' => $this->settings->legalText('datenschutz')]);
    }

    public function editImpressum(): void
    {
        $this->renderLegalEdit('impressum', 'Impressum');
    }

    public function updateImpressum(): void
    {
        $this->updateLegal('impressum');
    }

    public function editDatenschutz(): void
    {
        $this->renderLegalEdit('datenschutz', 'Datenschutzerklärung');
    }

    public function updateDatenschutz(): void
    {
        $this->updateLegal('datenschutz');
    }

    private function renderLegalEdit(string $page, string $label): void
    {
        $this->requirePermission('users.manage');
        $this->render('admin/legal-edit', [
            'page'           => $page,
            'pageLabel'      => $label,
            'content'        => $this->settings->legalText($page),
            'defaultContent' => $this->settings->defaultLegalText($page),
        ]);
    }

    private function updateLegal(string $page): void
    {
        $this->requirePermission('users.manage');

        if (($_POST['_action'] ?? '') === 'reset') {
            $this->settings->set('legal_' . $page, '');
            flash('success', 'Standardinhalt wiederhergestellt.');
        } else {
            $content = trim((string) ($_POST['content'] ?? ''));
            $this->settings->set('legal_' . $page, $content);
            flash('success', 'Inhalt gespeichert.');
        }

        redirect(url('/admin/legal/' . $page));
    }
}

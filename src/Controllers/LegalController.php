<?php

declare(strict_types=1);

namespace App\Controllers;

final class LegalController extends BaseController
{
    public function impressum(): void
    {
        $this->render('legal/impressum');
    }

    public function datenschutz(): void
    {
        $this->render('legal/datenschutz');
    }
}

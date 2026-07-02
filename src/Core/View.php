<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $templatePath = dirname(__DIR__, 2) . '/templates/' . $template . '.php';
        require dirname(__DIR__, 2) . '/templates/layout/app.php';
        unset($_SESSION['_old']);
    }
}

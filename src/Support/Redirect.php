<?php

declare(strict_types=1);

namespace App\Support;

final class Redirect
{
    public static function to(string $path): never
    {
        header('Location: ' . url($path));
        exit;
    }
}


<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Container
{
    private static array $entries = [];
    private static array $factories = [];

    public static function set(string $id, mixed $value): void
    {
        self::$entries[$id] = $value;
    }

    public static function factory(string $id, callable $factory): void
    {
        self::$factories[$id] = $factory;
    }

    public static function get(string $id): mixed
    {
        if (array_key_exists($id, self::$entries)) {
            return self::$entries[$id];
        }

        if (array_key_exists($id, self::$factories)) {
            self::$entries[$id] = self::$factories[$id]();
            return self::$entries[$id];
        }

        throw new RuntimeException("Container-Eintrag '{$id}' wurde nicht gefunden.");
    }
}


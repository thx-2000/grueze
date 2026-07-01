<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->map('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->map('POST', $path, $handler);
    }

    private function map(string $method, string $path, callable|array $handler): void
    {
        $this->routes[$method][$path] = Closure::fromCallable(is_array($handler)
            ? [Container::get($handler[0]), $handler[1]]
            : $handler);
    }

    public function dispatch(Request $request): void
    {
        $handler = $this->routes[$request->method()][$request->path()] ?? null;

        if (!$handler) {
            http_response_code(404);
            echo 'Seite nicht gefunden.';
            return;
        }

        $handler($request);
    }
}


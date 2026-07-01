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
        if (is_array($handler)) {
            $this->routes[$method][$path] = function (...$args) use ($handler): mixed {
                [$class, $action] = $handler;
                $instance = Container::get($class);

                return $instance->{$action}(...$args);
            };

            return;
        }

        $this->routes[$method][$path] = Closure::fromCallable($handler);
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

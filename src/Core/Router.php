<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

final class Router
{
    /** @var array<string, array<string, Closure>> exakte Pfade */
    private array $routes = [];

    /** @var array<string, list<array{regex: string, handler: Closure}>> Pfade mit {param} */
    private array $dynamic = [];

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
            [$class, $action] = $handler;
            $wrapped = static function (...$args) use ($class, $action): mixed {
                return Container::get($class)->{$action}(...$args);
            };
        } else {
            $wrapped = Closure::fromCallable($handler);
        }

        if (str_contains($path, '{')) {
            // /foo/{token} → #^/foo/([^/]+)$#
            $sentinel = "\x02PARAM\x02";
            $template = preg_replace('/\{[A-Za-z_][A-Za-z0-9_]*\}/', $sentinel, $path);
            $regex = '#^' . str_replace(preg_quote($sentinel, '#'), '([^/]+)', preg_quote((string) $template, '#')) . '$#';
            $this->dynamic[$method][] = ['regex' => $regex, 'handler' => $wrapped];

            return;
        }

        $this->routes[$method][$path] = $wrapped;
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $request->path();

        $handler = $this->routes[$method][$path] ?? null;
        if ($handler !== null) {
            $handler($request);

            return;
        }

        foreach ($this->dynamic[$method] ?? [] as $route) {
            if (preg_match($route['regex'], $path, $matches) === 1) {
                array_shift($matches);
                $params = array_map(static fn (string $v): string => rawurldecode($v), $matches);
                $route['handler']($request, ...$params);

                return;
            }
        }

        \render_error_page(404, 'Seite nicht gefunden', 'Die aufgerufene Adresse existiert nicht.');
    }
}

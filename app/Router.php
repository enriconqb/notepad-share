<?php

declare(strict_types=1);

namespace App;

final class Router
{
    /** @var list<array{method:string,pattern:string,handler:callable}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            $regex = '#^' . $route['pattern'] . '$#';
            if (preg_match($regex, $path, $m)) {
                $args = [];
                foreach ($m as $k => $v) {
                    if (is_string($k)) {
                        $args[] = $v;
                    }
                }
                ($route['handler'])(...$args);
                return;
            }
        }
        if (str_starts_with($path, '/api/')) {
            Http::error('not_found', 'Endpoint tidak ditemukan', 404);
        }
        http_response_code(404);
        echo 'Not found';
    }
}

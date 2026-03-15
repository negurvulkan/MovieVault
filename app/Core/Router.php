<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];
    private array $namedRoutes = [];

    public function __construct(private readonly string $basePath = '')
    {
    }

    public function get(string $path, array $handler, string $name, array $options = []): void
    {
        $this->add('GET', $path, $handler, $name, $options);
    }

    public function post(string $path, array $handler, string $name, array $options = []): void
    {
        $this->add('POST', $path, $handler, $name, $options);
    }

    public function add(string $method, string $path, array $handler, string $name, array $options = []): void
    {
        $route = new Route($method, $path, $handler, $name, $options);
        $this->routes[] = $route;
        $this->namedRoutes[$name] = $route;
    }

    public function match(Request $request): ?array
    {
        $requestPath = $this->stripBasePath($request->path());

        foreach ($this->routes as $route) {
            if ($route->method !== $request->method()) {
                continue;
            }

            $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $route->path);
            if ($pattern === null) {
                continue;
            }

            if (!preg_match('#^' . $pattern . '$#', $requestPath, $matches)) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (!is_int($key)) {
                    $params[$key] = $value;
                }
            }

            return ['route' => $route, 'params' => $params];
        }

        return null;
    }

    public function url(string $name, array $params = [], array $query = []): string
    {
        $route = $this->namedRoutes[$name] ?? null;
        if (!$route) {
            return '#';
        }

        $path = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            static fn (array $matches): string => (string) ($params[$matches[1]] ?? $matches[0]),
            $route->path
        );

        $url = $this->basePath . ($path === '/' ? '' : $path);
        $url = $url === '' ? '/' : $url;

        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }

    private function stripBasePath(string $path): string
    {
        if ($this->basePath !== '' && str_starts_with($path, $this->basePath)) {
            $path = substr($path, strlen($this->basePath)) ?: '/';
        }

        return $path === '' ? '/' : $path;
    }
}
